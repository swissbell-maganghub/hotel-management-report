<?php

namespace Database\Seeders;

use App\Models\PomecImport;
use App\Models\PomecRecord;
use App\Services\PomecExcelService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data CONTOH POMEC November 2026, ditiru dari data Oktober yang sudah diimpor (variasi ±10%).
 * Tidak dipanggil DatabaseSeeder. Jalankan: php artisan db:seed --class=PomecNovemberSampleSeeder
 * Sebelum mengunggah file asli November, hapus dulu batch contoh lewat tombol Hapus di tab POMEC.
 */
class PomecNovemberSampleSeeder extends Seeder
{
    // Batch yang boleh diganti: data contoh lama dari DemoDataSeeder dan hasil seeder ini sendiri.
    private const REPLACEABLE = [
        'seed-electricity-2026-11', 'seed-water-2026-11',
        'sample-electricity-2026-11', 'sample-water-2026-11', 'sample-gas-2026-11', 'sample-solar-2026-11',
    ];

    // Dicatat per kiriman atau per ganti tabung, jadi angkanya disalin apa adanya.
    private const AS_IS = ['Air Tangki', 'Gas LPG Dapur'];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Data contoh tidak boleh dibuat di server produksi.');
        }

        // 1–30 saja: November tidak punya tanggal 31.
        $source = PomecRecord::with('import')
            ->whereDate('reading_date', '>=', '2026-10-01')
            ->whereDate('reading_date', '<=', '2026-10-30')
            ->orderBy('reading_date')
            ->get();

        if ($source->isEmpty()) {
            $this->command?->warn('Belum ada data Oktober 2026. Impor dulu file Oktober di tab POMEC.');

            return;
        }

        DB::transaction(function () use ($source): void {
            PomecImport::whereIn('file_hash_sha256', array_map(fn (string $key) => hash('sha256', $key), self::REPLACEABLE))
                ->get()
                ->each(function (PomecImport $import): void {
                    $import->records()->delete();
                    $import->delete();
                });

            foreach ($source->groupBy('utility_type') as $type => $records) {
                // Data November yang bukan contoh tidak boleh tercampur.
                $hasRealData = PomecRecord::where('utility_type', $type)
                    ->whereDate('reading_date', '>=', '2026-11-01')
                    ->whereDate('reading_date', '<=', '2026-11-30')
                    ->exists();
                if ($hasRealData) {
                    $this->command?->warn("{$type}: sudah ada data November, dilewati.");

                    continue;
                }

                $rows = $records->map(fn (PomecRecord $record) => $this->toNovember($record));
                $import = PomecImport::create([
                    'batch_code' => 'POMEC-'.PomecRecord::TYPES[$type]['prefix'].'-202611-CONTOH',
                    'utility_type' => $type,
                    'period_month' => '2026-11',
                    'original_filename' => "CONTOH {$type} November 2026 (seeder)",
                    'file_hash_sha256' => hash('sha256', "sample-{$type}-2026-11"),
                    'total_rows' => $rows->count(),
                    'total_usage' => round($rows->sum('total_usage'), 2),
                    'total_cost' => PomecRecord::TYPES[$type]['cost'] ? round($rows->sum('cost'), 2) : null,
                    'status' => 'committed',
                    'uploaded_by' => $records->first()->import->uploaded_by,
                    'committed_at' => now(),
                ]);
                $import->records()->createMany($rows->all());
                $this->command?->info("{$type}: {$rows->count()} baris contoh November dibuat.");
            }
        });

        app(PomecExcelService::class)->syncReports();
    }

    private function toNovember(PomecRecord $record): array
    {
        $date = '2026-11'.$record->reading_date->format('-d');
        // Faktor 0,90–1,10 yang selalu sama untuk meter dan tanggal yang sama.
        $factor = in_array($record->meter_name, self::AS_IS, true)
            ? 1
            : 0.9 + (crc32("{$record->meter_name}|{$date}") % 21) / 100;
        $scale = fn ($value) => $value === null ? null : round($value * $factor, 2);
        $lwbp = $scale($record->lwbp_value);
        $wbp = $scale($record->wbp_value);
        $usage = $lwbp === null ? $scale($record->total_usage) : round($lwbp + $wbp, 2);

        return [
            'utility_type' => $record->utility_type,
            'reading_date' => $date,
            'meter_name' => $record->meter_name,
            'lwbp_value' => $lwbp,
            'wbp_value' => $wbp,
            'water_m3' => $record->utility_type === 'water' ? $usage : null,
            'total_usage' => $usage,
            'cost' => $scale($record->cost),
            // Sama dengan rumus impor, jadi tanggal yang sama tidak bisa terhitung dua kali.
            'transaction_hash' => hash('sha256', "{$record->utility_type}|{$date}|{$record->meter_name}"),
            'notes' => 'Data contoh (seeder)',
            ...$record->only(PomecRecord::GAS_STOCK),
        ];
    }
}
