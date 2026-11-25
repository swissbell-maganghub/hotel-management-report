<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Models\User;
use App\Support\ExcelCell;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PomecExcelService
{
    public function parseForPreview(UploadedFile $file, string $utilityType, string $periodMonth): array
    {
        $realPath = $file->getRealPath();
        $fileHash = hash_file('sha256', $realPath);
        $duplicateImport = PomecImport::where('file_hash_sha256', $fileHash)->first();

        $spreadsheet = IOFactory::load($realPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        if (count($rawRows) < 2) {
            return [
                'file_hash' => $fileHash,
                'is_duplicate_file' => (bool) $duplicateImport,
                'duplicate_batch' => $duplicateImport?->batch_code,
                'rows' => [],
                'valid_count' => 0,
                'error_count' => 1,
                'duplicate_row_count' => 0,
                'total_usage' => 0,
                'errors' => ['File Excel kosong atau tidak memiliki baris data setelah header.'],
            ];
        }

        $header = array_map(fn ($v) => strtolower(trim((string) $v)), $rawRows[0]);
        $rows = [];
        $errors = [];
        $validCount = 0;
        $errorCount = 0;
        $duplicateRowCount = 0;
        $totalUsage = 0.0;

        for ($i = 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $lineNum = $i + 1;
            $rawDate = trim((string) ($row[0] ?? ''));
            $meterName = trim((string) ($row[1] ?? ''));

            try {
                $parsedDate = is_numeric($rawDate)
                    ? Carbon::instance(Date::excelToDateTimeObject((float) $rawDate))->format('Y-m-d')
                    : Carbon::parse($rawDate)->format('Y-m-d');
            } catch (\Throwable) {
                $parsedDate = null;
            }

            $rowErrors = [];
            if (! $parsedDate) {
                $rowErrors[] = 'Tanggal tidak valid';
            }
            if ($meterName === '') {
                $rowErrors[] = 'Nama meter kosong';
            }

            $lwbp = null;
            $wbp = null;
            $waterM3 = null;
            $notes = '';
            $usage = 0.0;

            if ($utilityType === 'electricity') {
                $lwbp = $this->parseNumeric($row[2] ?? null);
                $wbp = $this->parseNumeric($row[3] ?? null);
                $notes = trim((string) ($row[4] ?? ''));

                if ($lwbp === null || $lwbp < 0) {
                    $rowErrors[] = 'LWBP harus angka >= 0';
                }
                if ($wbp === null || $wbp < 0) {
                    $rowErrors[] = 'WBP harus angka >= 0';
                }
                if ($lwbp !== null && $wbp !== null) {
                    $usage = round($lwbp + $wbp, 2);
                }
            } else {
                $waterM3 = $this->parseNumeric($row[2] ?? null);
                $notes = trim((string) ($row[3] ?? ''));

                if ($waterM3 === null || $waterM3 < 0) {
                    $rowErrors[] = 'Pemakaian air (m³) harus angka >= 0';
                } else {
                    $usage = round($waterM3, 2);
                }
            }

            $txHash = $parsedDate && $meterName !== ''
                ? hash('sha256', "{$utilityType}|{$parsedDate}|{$meterName}|{$usage}")
                : null;

            $isDuplicateRow = $txHash ? PomecRecord::where('transaction_hash', $txHash)->exists() : false;
            if ($isDuplicateRow) {
                $duplicateRowCount++;
                $rowErrors[] = 'Transaksi duplikat sudah ada di database';
            }

            $status = empty($rowErrors) ? 'valid' : ($isDuplicateRow && count($rowErrors) === 1 ? 'duplicate' : 'error');
            if ($status === 'valid') {
                $validCount++;
                $totalUsage += $usage;
            } else {
                $errorCount++;
                $errors[] = "Baris {$lineNum}: ".implode(', ', $rowErrors);
            }

            $rows[] = [
                'line' => $lineNum,
                'reading_date' => $parsedDate ?? $rawDate,
                'meter_name' => $meterName,
                'lwbp_value' => $lwbp,
                'wbp_value' => $wbp,
                'water_m3' => $waterM3,
                'total_usage' => $usage,
                'transaction_hash' => $txHash,
                'notes' => $notes,
                'status' => $status,
                'errors' => $rowErrors,
            ];
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
            'header' => $header,
            'is_duplicate_file' => (bool) $duplicateImport,
            'duplicate_batch' => $duplicateImport?->batch_code,
            'rows' => $rows,
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'duplicate_row_count' => $duplicateRowCount,
            'total_usage' => round($totalUsage, 2),
            'errors' => $errors,
        ];
    }

    public function commitPreview(array $preview, User $user): PomecImport
    {
        if ($preview['is_duplicate_file']) {
            throw new \RuntimeException('File dengan hash SHA-256 ini sudah pernah diimpor sebelumnya.');
        }

        $validRows = array_values(array_filter($preview['rows'], fn (array $r) => $r['status'] === 'valid'));
        if (empty($validRows)) {
            throw new \RuntimeException('Tidak ada baris valid yang dapat disimpan.');
        }

        return DB::transaction(function () use ($preview, $validRows, $user): PomecImport {
            $prefix = $preview['utility_type'] === 'electricity' ? 'ELC' : 'WTR';
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
            } while (PomecImport::where('batch_code', $batchCode)->exists());

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
                'status' => 'committed',
                'uploaded_by' => $user->id,
                'committed_at' => now(),
            ]);

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
                    'transaction_hash' => $row['transaction_hash'],
                    'notes' => $row['notes'],
                ]);
            }

            AuditLog::record(
                $user,
                'pomec.import.committed',
                'POMEC',
                "Impor POMEC {$batchCode} ({$preview['utility_type']}) berhasil disimpan ({$import->total_rows} baris).",
                ['batch_code' => $batchCode, 'sha256' => $preview['file_hash']]
            );

            return $import;
        });
    }

    public function downloadSampleTemplate(string $utilityType): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($utilityType === 'electricity') {
            $sheet->setTitle('POMEC Electricity');
            $headers = ['Tanggal (YYYY-MM-DD)', 'Nama Meter / Panel', 'LWBP (kWh)', 'WBP (kWh)', 'Catatan'];
            $samples = [
                ['2026-11-19', 'Main Panel MDP-01', 1945, 515, 'Shift pagi-malam normal'],
                ['2026-11-20', 'Main Panel MDP-01', 2010, 530, 'Event ballroom'],
            ];
            $filename = 'template_pomec_electricity.xlsx';
        } else {
            $sheet->setTitle('POMEC Water');
            $headers = ['Tanggal (YYYY-MM-DD)', 'Nama Meter', 'Pemakaian Air (m3)', 'Catatan'];
            $samples = [
                ['2026-11-19', 'PDAM Main Meter', 89.5, 'Normal'],
                ['2026-11-20', 'PDAM Main Meter', 94.0, 'Laundry peak'],
            ];
            $filename = 'template_pomec_water.xlsx';
        }

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($samples, null, 'A2');

        $lastCol = $utilityType === 'electricity' ? 'E' : 'D';
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2942']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportExcel(string $utilityType, ?string $periodMonth = null): StreamedResponse
    {
        $query = PomecRecord::with('import.uploader')
            ->where('utility_type', $utilityType)
            ->orderBy('reading_date');

        if ($periodMonth) {
            $query->whereHas('import', fn ($q) => $q->where('period_month', $periodMonth));
        }

        $records = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('POMEC '.ucfirst($utilityType));

        $sheet->setCellValue('A1', 'SWISS-BELHOTEL WATERFRONT MAKASSAR - POMEC '.strtoupper($utilityType).' REPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        if ($utilityType === 'electricity') {
            $headers = ['Tanggal', 'Meter / Panel', 'LWBP (kWh)', 'WBP (kWh)', 'Total (kWh)', 'Batch Import', 'Catatan'];
            $lastCol = 'G';
        } else {
            $headers = ['Tanggal', 'Meter', 'Pemakaian Air (m3)', 'Batch Import', 'Catatan'];
            $lastCol = 'E';
        }

        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2942']],
        ]);

        $rowNum = 4;
        foreach ($records as $record) {
            if ($utilityType === 'electricity') {
                $sheet->fromArray([
                    $record->reading_date->format('Y-m-d'),
                    ExcelCell::sanitize((string) $record->meter_name),
                    (float) $record->lwbp_value,
                    (float) $record->wbp_value,
                    (float) $record->total_usage,
                    ExcelCell::sanitize((string) $record->import?->batch_code),
                    ExcelCell::sanitize((string) $record->notes),
                ], null, "A{$rowNum}");
            } else {
                $sheet->fromArray([
                    $record->reading_date->format('Y-m-d'),
                    ExcelCell::sanitize((string) $record->meter_name),
                    (float) $record->water_m3,
                    ExcelCell::sanitize((string) $record->import?->batch_code),
                    ExcelCell::sanitize((string) $record->notes),
                ], null, "A{$rowNum}");
            }
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

    private function parseNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([',', ' '], ['.', ''], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
