<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentReport;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\ReportHistory;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Support\ExcelCell;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membaca file bulanan asli POMEC apa adanya. Nama sheet dan posisi kolom mengikuti file
 * yang dipakai tim engineering; kalau tata letak file berubah, ubah pembaca di bawah.
 */
class PomecExcelService
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'mei' => 5, 'jun' => 6, 'jul' => 7,
        'aug' => 8, 'agu' => 8, 'sep' => 9, 'oct' => 10, 'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12,
    ];

    /** Kolom laporan per utilitas: modal detail dan ekspor laporan memakai kolom ini. */
    private const REPORT_COLUMNS = [
        'electricity' => ['date', 'meter', 'lwbp', 'wbp', 'usage', 'cost'],
        'water' => ['date', 'meter', 'usage', 'cost'],
        'gas' => ['date', 'usage', 'received', 'full', 'empty', 'notes'],
        'solar' => ['date', 'usage', 'notes'],
    ];

    /** $replaceImportId: unggah ulang laporan yang dikembalikan; baris batch itu dianggap belum tersimpan. */
    public function parseForPreview(UploadedFile $file, string $utilityType, string $periodMonth, ?int $replaceImportId = null): array
    {
        $realPath = $file->getRealPath();
        $fileHash = hash_file('sha256', $realPath);
        $duplicateImport = PomecImport::where('file_hash_sha256', $fileHash)->first();

        $rawRows = [];
        $errors = [];

        try {
            $workbook = IOFactory::load($realPath);
            $rawRows = match ($utilityType) {
                'electricity' => $this->readElectricity($workbook, $periodMonth),
                'water' => $this->readWater($workbook, $periodMonth),
                'gas' => $this->readGas($workbook, $periodMonth),
                'solar' => $this->readSolar($workbook, $periodMonth),
            };
            if (! $rawRows) {
                $errors[] = "Tidak ada data untuk periode {$periodMonth}. Pastikan bulan yang dipilih sesuai isi file.";
            }
        } catch (Exception $e) {
            $errors[] = 'File Excel tidak dapat dibaca.';
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        $hasCost = PomecRecord::TYPES[$utilityType]['cost'];
        $today = now()->toDateString();
        // File bulanan diunggah ulang setiap hari: semua yang sudah tersimpan bulan ini diambil sekali.
        $monthStart = "{$periodMonth}-01";
        $saved = PomecRecord::with('import:id,batch_code')
            ->where('utility_type', $utilityType)
            ->when($replaceImportId, fn ($q) => $q->where('pomec_import_id', '!=', $replaceImportId))
            ->whereDate('reading_date', '>=', $monthStart)
            ->whereDate('reading_date', '<=', date('Y-m-t', strtotime($monthStart)))
            ->get()
            ->keyBy(fn (PomecRecord $r) => $r->reading_date->format('Y-m-d').'|'.$r->meter_name);
        $rows = [];
        $seen = [];
        $counts = ['valid' => 0, 'existing' => 0, 'changed' => 0, 'error' => 0];
        $totalUsage = 0.0;
        $totalCost = 0.0;

        foreach ($rawRows as $row) {
            $rowErrors = [];
            $txHash = hash('sha256', "{$utilityType}|{$row['date']}|{$row['meter']}");
            $existing = $saved["{$row['date']}|{$row['meter']}"] ?? null;

            if ($existing) {
                // Tanggal lama dilewati. Kalau angkanya berubah, hanya diberi peringatan: data tersimpan tidak ditimpa.
                $same = $row['usage'] !== null && abs((float) $existing->total_usage - $row['usage']) < 0.01
                    && (! $hasCost || ($row['cost'] !== null && abs((float) $existing->cost - $row['cost']) < 0.01));
                // Stok yang belum pernah tersimpan (data lama) tidak dihitung berbeda.
                $sameStock = collect(PomecRecord::GAS_STOCK)
                    ->every(fn (string $key) => $existing->{$key} === null || $existing->{$key} === ($row[$key] ?? null));
                $status = $same && $sameStock ? 'existing' : 'changed';
                if ($status === 'changed') {
                    $differs = $same ? 'stok atau gas masuk' : $this->qty($existing->total_usage);
                    $rowErrors[] = "Berbeda dengan yang tersimpan ({$differs} di batch {$existing->import?->batch_code})"
                        .'; tidak ditimpa. Untuk koreksi, minta HOD mengembalikan (Return) laporannya lalu unggah ulang dari laporan itu';
                }
            } else {
                if ($row['usage'] === null) {
                    $rowErrors[] = 'Pemakaian bukan angka (cek rumus di Excel)';
                } elseif (min($row['usage'], $row['lwbp'] ?? 0, $row['wbp'] ?? 0) < 0) {
                    $rowErrors[] = 'Pemakaian negatif (cek angka meter atau pengisian stok)';
                }
                if ($hasCost && $row['cost'] === null) {
                    $rowErrors[] = 'Biaya bukan angka (cek rumus di Excel)';
                }
                if ($row['date'] > $today) {
                    $rowErrors[] = 'Tanggal belum terjadi (cek tanggal di Excel)';
                }
                if (isset($seen[$txHash])) {
                    $rowErrors[] = 'Tanggal ganda di dalam file';
                }
                $status = $rowErrors ? 'error' : 'valid';
            }
            $seen[$txHash] = true;
            $counts[$status]++;

            if ($status === 'valid') {
                $totalUsage += $row['usage'];
                $totalCost += $row['cost'] ?? 0;
            } elseif ($rowErrors) {
                $errors[] = "{$row['meter']} {$row['date']}: ".implode(', ', $rowErrors);
            }

            $rows[] = [
                'reading_date' => $row['date'],
                'meter_name' => $row['meter'],
                'lwbp_value' => $row['lwbp'] ?? null,
                'wbp_value' => $row['wbp'] ?? null,
                'water_m3' => $utilityType === 'water' ? $row['usage'] : null,
                'total_usage' => $row['usage'] ?? 0,
                'cost' => $row['cost'] ?? null,
                'transaction_hash' => $txHash,
                'notes' => mb_substr($row['notes'] ?? '', 0, 255),
                ...collect(PomecRecord::GAS_STOCK)->mapWithKeys(fn (string $key) => [$key => $row[$key] ?? null])->all(),
                'status' => $status,
                'errors' => $rowErrors,
            ];
        }

        // Tanggal yang terlewat: belum tersimpan dan tidak terbaca di file, padahal tanggal sesudahnya ada.
        $covered = array_flip([
            ...array_column($rawRows, 'date'),
            ...$saved->map(fn (PomecRecord $r) => $r->reading_date->format('Y-m-d'))->all(),
        ]);
        $missingDates = [];
        for ($d = $monthStart, $last = $covered ? max(array_keys($covered)) : $monthStart; $d < $last; $d = date('Y-m-d', strtotime("{$d} +1 day"))) {
            if (! isset($covered[$d])) {
                $missingDates[] = $d;
            }
        }

        // Store temp file in private storage so user can confirm commit without re-uploading
        $tempPath = 'pomec/tmp/'.uniqid('preview_', true).'.'.$file->getClientOriginalExtension();
        Storage::disk('local')->put($tempPath, file_get_contents($realPath));

        return [
            'original_filename' => $file->getClientOriginalName(),
            'temp_path' => $tempPath,
            'file_hash' => $fileHash,
            'utility_type' => $utilityType,
            'period_month' => $periodMonth,
            'is_duplicate_file' => (bool) $duplicateImport,
            'duplicate_batch' => $duplicateImport?->batch_code,
            'rows' => $rows,
            'valid_count' => $counts['valid'],
            'error_count' => $rows ? $counts['error'] : count($errors),
            'existing_count' => $counts['existing'],
            'changed_count' => $counts['changed'],
            'duplicate_row_count' => $counts['existing'] + $counts['changed'],
            'missing_dates' => $missingDates,
            'last_saved_date' => $saved->isEmpty() ? null : $saved->max(fn (PomecRecord $r) => $r->reading_date->format('Y-m-d')),
            'total_usage' => round($totalUsage, 2),
            'total_cost' => $hasCost ? round($totalCost, 2) : null,
            'errors' => $errors,
        ];
    }

    private function readElectricity(Spreadsheet $workbook, string $period): array
    {
        $rows = [];
        // Urutan kolom LWBP/WBP di sheet Laundry terbalik dibanding sheet Gedung.
        foreach ([
            ['Electric consumtion Gedung', 'PLN Gedung', 'D', 'H'],
            ['Electric consumtion Laundry', 'PLN Laundry', 'H', 'D'],
        ] as [$sheetName, $meter, $lwbpCol, $wbpCol]) {
            $sheet = $this->sheet($workbook, $sheetName);
            for ($r = 7; $r <= $sheet->getHighestDataRow(); $r++) {
                $date = $this->date($sheet, "A{$r}", $period);
                // Angka meter awal/akhir yang masih kosong = hari belum lengkap.
                if (! $date || $this->anyUnread($sheet, ["B{$r}", "C{$r}", "F{$r}", "G{$r}"])) {
                    continue;
                }
                $lwbp = $this->number($sheet, $lwbpCol.$r);
                $wbp = $this->number($sheet, $wbpCol.$r);
                $rows[] = [
                    'date' => $date,
                    'meter' => $meter,
                    'lwbp' => $lwbp,
                    'wbp' => $wbp,
                    'usage' => $lwbp === null || $wbp === null ? null : round($lwbp + $wbp, 2),
                    'cost' => $this->number($sheet, "J{$r}"),
                ];
            }
        }

        return $rows;
    }

    private function readWater(Spreadsheet $workbook, string $period): array
    {
        $rows = [];
        foreach (['Pemakaian air gedung' => 'PDAM Gedung', 'Pemakaian air Laundry' => 'PDAM Laundry'] as $sheetName => $meter) {
            $sheet = $this->sheet($workbook, $sheetName);
            for ($r = 5; $r <= $sheet->getHighestDataRow(); $r++) {
                $date = $this->date($sheet, "B{$r}", $period);
                if (! $date || $this->anyUnread($sheet, ["C{$r}", "D{$r}"])) {
                    continue;
                }
                $rows[] = ['date' => $date, 'meter' => $meter, 'usage' => $this->number($sheet, "E{$r}"), 'cost' => $this->number($sheet, "F{$r}")];
            }
        }

        $tank = $this->sheet($workbook, 'Tangki');
        for ($r = 4; $r <= $tank->getHighestDataRow(); $r++) {
            $date = $this->date($tank, "B{$r}", $period);
            // Jumlah tangki kosong = belum diisi; angka 0 tetap dicatat.
            if (! $date || $this->isEmpty($this->value($tank, "C{$r}"))) {
                continue;
            }
            $rows[] = ['date' => $date, 'meter' => 'Air Tangki', 'usage' => $this->number($tank, "D{$r}"), 'cost' => $this->number($tank, "E{$r}")];
        }

        return $rows;
    }

    private function readGas(Spreadsheet $workbook, string $period): array
    {
        $sheet = $this->sheet($workbook, 'Sheet1');
        $title = (string) $this->value($sheet, 'A3');

        if (! preg_match('/MONTH\s*:\s*([a-z]{3})[a-z]*\s+(\d{4})/i', $title, $m) || ! isset(self::MONTHS[strtolower($m[1])])) {
            throw new \RuntimeException('Judul bulan di sel A3 tidak ditemukan. Pastikan file sesuai jenis utilitas yang dipilih.');
        }
        $fileMonth = sprintf('%s-%02d', $m[2], self::MONTHS[strtolower($m[1])]);
        if ($fileMonth !== $period) {
            throw new \RuntimeException("File gas ini untuk periode {$fileMonth}, bukan {$period}.");
        }

        $days = [];
        // Dua blok berdampingan: hari, jam, tabung terpasang, penuh, kosong, pemakaian, catatan.
        $blocks = [['A', 'B', 'F', 'G', 'H', 'J', 'K'], ['M', 'N', 'R', 'S', 'T', 'V', 'W']];
        foreach ($blocks as [$dayCol, $timeCol, $installedCol, $fullCol, $emptyCol, $usageCol, $noteCol]) {
            $day = null;
            for ($r = 6; $r <= $sheet->getHighestDataRow(); $r++) {
                $day = $this->number($sheet, $dayCol.$r) ?? $day;
                $usage = $this->value($sheet, $usageCol.$r);
                // Baris tanpa jam adalah baris total; baris tanpa isian adalah shift yang belum diisi.
                if ($day === null || ! checkdate(self::MONTHS[strtolower($m[1])], (int) $day, (int) $m[2])
                    || $this->isEmpty($this->value($sheet, $timeCol.$r))
                    || ($this->isEmpty($this->value($sheet, $installedCol.$r)) && $this->isEmpty($usage))) {
                    continue;
                }

                $date = sprintf('%s-%02d', $period, $day);
                $days[$date] ??= ['date' => $date, 'meter' => 'Gas LPG Dapur', 'usage' => 0.0, 'notes' => '',
                    'qty_received' => 0, 'stock_full' => null, 'stock_empty' => null, 'stock_installed' => null];

                // Stok akhir hari = isian shift terakhir yang terisi.
                foreach (['stock_installed' => $installedCol, 'stock_full' => $fullCol, 'stock_empty' => $emptyCol] as $key => $col) {
                    $stock = $this->number($sheet, $col.$r);
                    if ($stock !== null) {
                        $days[$date][$key] = (int) $stock;
                    }
                }

                if (! $this->isEmpty($usage)) {
                    $days[$date]['usage'] = is_numeric($usage) && $days[$date]['usage'] !== null
                        ? $days[$date]['usage'] + (float) $usage
                        : null;
                }
                $note = trim((string) $this->value($sheet, $noteCol.$r));
                if ($note !== '') {
                    $days[$date]['notes'] = ltrim($days[$date]['notes'].'; '.$note, '; ');
                }
                // Jumlah tabung yang datang hanya tertulis di catatan, mis. "Gas Masuk 16 Tbg".
                if (preg_match('/masuk\s+(\d+)/i', $note, $in)) {
                    $days[$date]['qty_received'] += (int) $in[1];
                }
            }
        }

        return array_values($days);
    }

    private function readSolar(Spreadsheet $workbook, string $period): array
    {
        $sheet = $this->sheet($workbook, 'SOLAR');
        $rows = [];

        for ($r = 5; $r <= $sheet->getHighestDataRow(); $r++) {
            $date = $this->date($sheet, "B{$r}", $period);
            $next = $r + 1;
            // Pemakaian = stok hari ini - stok besok, jadi stok besok harus sudah diisi
            // (kecuali baris terakhir tabel).
            if (! $date || ! $this->hasStock($sheet, $r)
                || ($this->number($sheet, "B{$next}") !== null && ! $this->hasStock($sheet, $next))) {
                continue;
            }
            $stock = (float) $this->number($sheet, "C{$r}") + (float) $this->number($sheet, "D{$r}");
            $remark = trim((string) $this->value($sheet, "H{$r}"));
            $rows[] = [
                'date' => $date,
                'meter' => 'Solar Genset',
                'usage' => $this->number($sheet, "F{$r}"),
                'notes' => "Stok {$stock} L".($remark !== '' ? "; {$remark}" : ''),
            ];
        }

        return $rows;
    }

    private function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    private function sheet(Spreadsheet $workbook, string $name): Worksheet
    {
        return $workbook->getSheetByName($name)
            ?? throw new \RuntimeException("Sheet \"{$name}\" tidak ditemukan. Pastikan file sesuai jenis utilitas yang dipilih.");
    }

    private function value(Worksheet $sheet, string $ref): mixed
    {
        $cell = $sheet->getCell($ref);

        try {
            return $cell->getCalculatedValue();
        } catch (\PhpOffice\PhpSpreadsheet\Calculation\Exception) {
            return $cell->getOldCalculatedValue(); // rumus yang tidak bisa dihitung ulang
        }
    }

    private function number(Worksheet $sheet, string $ref): ?float
    {
        $value = $this->value($sheet, $ref);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** Tanggal sel (serial Excel) bila berada di periode yang dipilih. */
    private function date(Worksheet $sheet, string $ref, string $period): ?string
    {
        $value = $this->value($sheet, $ref);
        if (! is_numeric($value) || $value <= 0) {
            return null;
        }
        $date = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');

        return str_starts_with($date, $period.'-') ? $date : null;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    /** Rumus yang menunjuk sel meter kosong menghasilkan 0. */
    private function anyUnread(Worksheet $sheet, array $refs): bool
    {
        foreach ($refs as $ref) {
            $value = $this->value($sheet, $ref);
            if ($this->isEmpty($value) || (is_numeric($value) && (float) $value === 0.0)) {
                return true;
            }
        }

        return false;
    }

    private function hasStock(Worksheet $sheet, int $row): bool
    {
        return ! $this->isEmpty($this->value($sheet, "C{$row}")) || ! $this->isEmpty($this->value($sheet, "D{$row}"));
    }

    /**
     * Simpan batch dan laporannya. $intent: 'draft' atau 'submit'.
     * $replace: laporan yang dikembalikan HOD; isinya diganti file ini lalu dikirim ulang.
     */
    public function commitPreview(array $preview, User $user, string $intent = 'submit', ?DepartmentReport $replace = null): PomecImport
    {
        if ($preview['is_duplicate_file']) {
            throw new \RuntimeException('File dengan hash SHA-256 ini sudah pernah diimpor sebelumnya.');
        }

        $validRows = array_values(array_filter($preview['rows'], fn (array $r) => $r['status'] === 'valid'));
        if (empty($validRows)) {
            throw new \RuntimeException('Tidak ada baris valid yang dapat disimpan.');
        }

        $oldImport = $replace?->pomecImport;

        $import = DB::transaction(function () use ($preview, $validRows, $user, $intent, $replace, $oldImport): PomecImport {
            $prefix = PomecRecord::TYPES[$preview['utility_type']]['prefix'];
            $monthCompact = str_replace('-', '', $preview['period_month']);
            $latestBatch = PomecImport::where('batch_code', 'like', "POMEC-{$prefix}-{$monthCompact}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('batch_code');
            $lastSeq = $latestBatch && preg_match('/(\d+)$/', $latestBatch, $m) ? (int) $m[1] : 0;
            $seq = max($lastSeq, PomecImport::where('utility_type', $preview['utility_type'])->count()) + 1;
            do {
                $batchCode = sprintf('POMEC-%s-%s-%02d', $prefix, $monthCompact, $seq);
                $seq++;
                // Kode batch yang sudah dihapus tidak dipakai ulang, supaya jejak audit tidak rancu.
            } while (PomecImport::where('batch_code', $batchCode)->exists()
                || AuditLog::where('event', 'pomec.import.deleted')->where('metadata->batch_code', $batchCode)->exists());

            $finalPath = null;
            if (! empty($preview['temp_path']) && Storage::disk('local')->exists($preview['temp_path'])) {
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $preview['original_filename']));
                $finalPath = 'pomec/imports/'.$batchCode.'_'.$safeName;
                Storage::disk('local')->move($preview['temp_path'], $finalPath);
            }

            $import = PomecImport::create([
                'batch_code' => $batchCode,
                'utility_type' => $preview['utility_type'],
                'period_month' => $preview['period_month'],
                'original_filename' => $preview['original_filename'],
                'file_hash_sha256' => $preview['file_hash'],
                'stored_path' => $finalPath,
                'total_rows' => count($validRows),
                'total_usage' => $preview['total_usage'],
                'total_cost' => $preview['total_cost'],
                'status' => 'committed',
                'uploaded_by' => $user->id,
                'committed_at' => now(),
            ]);

            if ($replace) {
                // Laporan dipindah ke batch baru dulu supaya tidak ikut terhapus bersama batch lama.
                $replace->update(['pomec_import_id' => $import->id]);
                $oldImport?->records()->delete();
            }

            foreach ($validRows as $row) {
                PomecRecord::create([
                    'pomec_import_id' => $import->id,
                    'utility_type' => $preview['utility_type'],
                    'reading_date' => $row['reading_date'],
                    'meter_name' => $row['meter_name'],
                    'lwbp_value' => $row['lwbp_value'],
                    'wbp_value' => $row['wbp_value'],
                    'water_m3' => $row['water_m3'],
                    'total_usage' => $row['total_usage'],
                    'cost' => $row['cost'],
                    'transaction_hash' => $row['transaction_hash'],
                    'notes' => $row['notes'],
                    ...Arr::only($row, PomecRecord::GAS_STOCK),
                ]);
            }

            AuditLog::record(
                $user,
                'pomec.import.committed',
                'POMEC',
                "Impor POMEC {$batchCode} ({$preview['utility_type']}) berhasil disimpan ({$import->total_rows} baris).",
                ['batch_code' => $batchCode, 'sha256' => $preview['file_hash']]
            );

            if ($replace) {
                $this->fillReport($replace, $import);
                $replace->fill([
                    'status' => DepartmentReport::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ])->save();
                ReportHistory::create([
                    'department_report_id' => $replace->id,
                    'user_id' => $user->id,
                    'from_status' => DepartmentReport::STATUS_RETURNED,
                    'to_status' => DepartmentReport::STATUS_SUBMITTED,
                    'action' => 'submitted',
                    'note' => "File perbaikan {$import->original_filename} diunggah ulang dan dikirim ke HOD.",
                    'created_at' => now(),
                ]);
                AuditLog::record($user, 'report.submitted', 'Reports', "Laporan {$replace->report_code} diunggah ulang dari {$batchCode}.");
            } else {
                $this->createReport($import, $user, $intent === 'draft' ? DepartmentReport::STATUS_DRAFT : DepartmentReport::STATUS_SUBMITTED);
            }

            return $import;
        });

        if ($oldImport) {
            $this->deleteImport($oldImport->fresh(), $user);
        }

        return $import;
    }

    /** Template laporan Engineering per utilitas (tidak muncul di form laporan manual). */
    public function reportTemplate(string $type): ReportTemplate
    {
        $info = PomecRecord::TYPES[$type];
        $unit = $info['unit'];
        $columns = [
            'date' => ['key' => 'reading_date', 'label' => 'Tanggal', 'label_en' => 'Date', 'type' => 'date'],
            'meter' => ['key' => 'meter_name', 'label' => 'Meter', 'label_en' => 'Meter', 'type' => 'text'],
            'lwbp' => ['key' => 'lwbp_value', 'label' => 'LWBP (kWh)', 'label_en' => 'LWBP (kWh)', 'type' => 'number', 'sum' => true],
            'wbp' => ['key' => 'wbp_value', 'label' => 'WBP (kWh)', 'label_en' => 'WBP (kWh)', 'type' => 'number', 'sum' => true],
            'usage' => ['key' => 'total_usage', 'label' => "Pemakaian ({$unit})", 'label_en' => "Usage ({$unit})", 'type' => 'number', 'sum' => true],
            'cost' => ['key' => 'cost', 'label' => 'Biaya sebelum pajak (Rp)', 'label_en' => 'Cost before tax (IDR)', 'type' => 'currency', 'sum' => true],
            'received' => ['key' => 'qty_received', 'label' => 'Gas Masuk', 'label_en' => 'Received', 'type' => 'number', 'sum' => true],
            'full' => ['key' => 'stock_full', 'label' => 'Stok Penuh', 'label_en' => 'Full Stock', 'type' => 'number'],
            'empty' => ['key' => 'stock_empty', 'label' => 'Stok Kosong', 'label_en' => 'Empty Stock', 'type' => 'number'],
            'notes' => ['key' => 'notes', 'label' => 'Catatan', 'label_en' => 'Notes', 'type' => 'text'],
        ];

        return ReportTemplate::updateOrCreate(['code' => 'ENG-POMEC-'.$info['prefix']], [
            'department_id' => Department::where('code', 'ENG')->value('id'),
            'name' => "POMEC {$info['id']}",
            'name_en' => "POMEC {$info['en']}",
            'description' => "Pemakaian {$info['id']} harian dari file Excel engineering.",
            'description_en' => "Daily {$info['en']} usage from the engineering Excel file.",
            'columns_schema' => array_map(fn (string $c) => $columns[$c], self::REPORT_COLUMNS[$type]),
            'default_rows' => [],
            'is_active' => false,
        ]);
    }

    /** Laporan untuk unggahan yang belum punya (data lama / seeder). Status: Submitted. */
    public function syncReports(): int
    {
        $imports = PomecImport::doesntHave('report')->with('uploader')->orderBy('id')->get();
        foreach ($imports as $import) {
            DB::transaction(fn () => $this->createReport($import, $import->uploader, DepartmentReport::STATUS_SUBMITTED, 'Laporan dibuat dari unggahan POMEC yang sudah ada.'));
        }

        return $imports->count();
    }

    /** Melengkapi stok gas pada unggahan lama dari file asli yang tersimpan. Hasil: jumlah baris terisi. */
    public function backfillGasStock(): int
    {
        $filled = 0;
        $imports = PomecImport::with('report')->where('utility_type', 'gas')->whereNotNull('stored_path')
            ->whereHas('records', fn ($q) => $q->whereNull('stock_full'))->get();

        foreach ($imports as $import) {
            if (! Storage::disk('local')->exists($import->stored_path)) {
                continue;
            }
            $workbook = IOFactory::load(Storage::disk('local')->path($import->stored_path));
            $days = collect($this->readGas($workbook, $import->period_month))->keyBy('date');

            DB::transaction(function () use ($import, $days, &$filled): void {
                foreach ($import->records()->whereNull('stock_full')->get() as $record) {
                    $day = $days[$record->reading_date->toDateString()] ?? null;
                    if ($day && $day['stock_full'] !== null) {
                        $record->update(Arr::only($day, PomecRecord::GAS_STOCK));
                        $filled++;
                    }
                }
                if ($import->report) {
                    $this->fillReport($import->report, $import);
                    $import->report->save();
                }
            });
        }

        return $filled;
    }

    private function createReport(PomecImport $import, User $user, string $status, ?string $note = null): DepartmentReport
    {
        $engId = Department::where('code', 'ENG')->value('id');
        $last = $import->records()->max('reading_date') ?? "{$import->period_month}-01";
        $report = new DepartmentReport([
            'report_code' => DepartmentReport::nextCode('ENG', $engId, (string) $last),
            'department_id' => $engId,
            'pomec_import_id' => $import->id,
            'status' => $status,
            'created_by' => $user->id,
            'submitted_at' => $status === DepartmentReport::STATUS_SUBMITTED ? now() : null,
        ]);
        $this->fillReport($report, $import);
        $report->save();

        $submitted = $status === DepartmentReport::STATUS_SUBMITTED;
        ReportHistory::create([
            'department_report_id' => $report->id,
            'user_id' => $user->id,
            'from_status' => null,
            'to_status' => $status,
            'action' => $submitted ? 'submitted' : 'created',
            'note' => $note ?? "Data POMEC dari {$import->original_filename} ".($submitted ? 'dikirim ke HOD.' : 'disimpan sebagai draft.'),
            'created_at' => now(),
        ]);
        AuditLog::record($user, 'report.'.$status, 'Reports', "Laporan {$report->report_code} (POMEC {$import->batch_code}) disimpan dengan status {$status}.");

        return $report;
    }

    /** Isi laporan dari baris batch: judul, periode, tabel, dan ringkasan total. */
    private function fillReport(DepartmentReport $report, PomecImport $import): void
    {
        $type = PomecRecord::TYPES[$import->utility_type];
        $records = $import->records()->get();
        $first = $records->first()?->reading_date ?? Carbon::parse("{$import->period_month}-01");
        $last = $records->last()?->reading_date ?? $first;
        $label = fn (Carbon $d, string $format) => $d->copy()->locale('id')->translatedFormat($format);
        $range = $first->isSameDay($last) ? $label($last, 'j M Y') : $first->day.'–'.$label($last, 'j M Y');
        $days = $records->pluck('reading_date')->map->toDateString()->unique()->count();
        $usage = (float) $records->sum('total_usage');
        $cost = $records->whereNotNull('cost')->sum('cost');
        $lastStock = $records->whereNotNull('stock_full')->last();
        $template = $this->reportTemplate($import->utility_type);
        $keys = array_column($template->columns_schema, 'key');

        $report->fill([
            'report_template_id' => $template->id,
            'title' => "POMEC {$type['id']} — {$range}",
            'period_type' => $days > 1 ? 'monthly' : 'daily',
            'period_date' => $last->toDateString(),
            'rows_data' => $records->map(fn (PomecRecord $r) => collect($keys)->mapWithKeys(fn (string $key) => [$key => match (true) {
                $key === 'reading_date' => $r->reading_date->toDateString(),
                $r->{$key} === null, in_array($key, ['meter_name', 'notes'], true) => $r->{$key},
                default => (float) $r->{$key},
            }])->all())->values()->all(),
            'summary_notes' => 'Total '.$this->qty($usage)." {$type['unit']}"
                .($type['cost'] ? ' · Rp '.number_format((float) $cost, 0, ',', '.').' (sebelum pajak)' : '')
                .($lastStock ? " · gas masuk {$records->sum('qty_received')} · stok penuh akhir {$lastStock->stock_full}" : '')
                ." · {$days} hari · batch {$import->batch_code}.",
        ]);
    }

    /** Menghapus batch beserta barisnya. File asli diarsipkan, tidak dibuang. */
    public function deleteImport(PomecImport $import, User $user): void
    {
        $storedPath = $import->stored_path;
        $archivePath = $storedPath ? 'pomec/deleted/'.now()->format('YmdHis').'_'.basename($storedPath) : null;

        DB::transaction(function () use ($import, $user, $archivePath): void {
            AuditLog::record(
                $user,
                'pomec.import.deleted',
                'POMEC',
                "Impor POMEC {$import->batch_code} ({$import->utility_type} {$import->period_month}) dihapus ({$import->total_rows} baris).",
                [
                    'batch_code' => $import->batch_code,
                    'sha256' => $import->file_hash_sha256,
                    'original_filename' => $import->original_filename,
                    'total_usage' => $import->total_usage,
                    'total_cost' => $import->total_cost,
                    'archived_path' => $archivePath,
                ]
            );

            $import->records()->delete();
            $import->report()->delete();
            $import->delete();
        });

        if ($storedPath && Storage::disk('local')->exists($storedPath)) {
            Storage::disk('local')->move($storedPath, $archivePath);
        }
    }

    public function exportExcel(string $utilityType, ?string $periodMonth = null): StreamedResponse
    {
        $query = PomecRecord::with('import.uploader')
            ->where('utility_type', $utilityType)
            ->orderBy('reading_date')
            ->orderBy('meter_name');

        if ($periodMonth) {
            $query->whereHas('import', fn ($q) => $q->where('period_month', $periodMonth));
        }

        $type = PomecRecord::TYPES[$utilityType];
        $isElec = $utilityType === 'electricity';
        $isGas = $utilityType === 'gas';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('POMEC '.ucfirst($utilityType));

        $sheet->setCellValue('A1', 'SWISS-BELHOTEL WATERFRONT MAKASSAR - POMEC '.strtoupper($utilityType).' REPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headers = [
            'Tanggal',
            'Meter',
            ...($isElec ? ['LWBP (kWh)', 'WBP (kWh)', 'Total (kWh)'] : ["Pemakaian ({$type['unit']})"]),
            ...($type['cost'] ? ['Biaya sebelum pajak (Rp)'] : []),
            ...($isGas ? ['Gas Masuk', 'Stok Penuh', 'Stok Kosong', 'Terpasang'] : []),
            'Batch Import',
            'Catatan',
        ];
        $lastCol = chr(ord('A') + count($headers) - 1);

        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2942']],
        ]);

        $rowNum = 4;
        foreach ($query->get() as $record) {
            $sheet->fromArray([
                $record->reading_date->format('Y-m-d'),
                ExcelCell::sanitize((string) $record->meter_name),
                ...($isElec
                    ? [(float) $record->lwbp_value, (float) $record->wbp_value, (float) $record->total_usage]
                    : [(float) $record->total_usage]),
                ...($type['cost'] ? [$record->cost === null ? null : (float) $record->cost] : []),
                ...($isGas ? array_map(fn (string $key) => $record->{$key}, PomecRecord::GAS_STOCK) : []),
                ExcelCell::sanitize((string) $record->import?->batch_code),
                ExcelCell::sanitize((string) $record->notes),
            ], null, "A{$rowNum}", true);
            $rowNum++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'pomec_'.$utilityType.'_'.($periodMonth ?: 'all').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
