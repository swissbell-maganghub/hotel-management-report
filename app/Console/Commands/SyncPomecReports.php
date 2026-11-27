<?php

namespace App\Console\Commands;

use App\Services\PomecExcelService;
use Illuminate\Console\Command;

class SyncPomecReports extends Command
{
    protected $signature = 'pomec:sync-reports';

    protected $description = 'Fill missing gas stock from stored files, then create a report (Submitted) for every POMEC upload that has none';

    public function handle(PomecExcelService $service): int
    {
        $filled = $service->backfillGasStock();
        $created = $service->syncReports();
        $this->info("Stok gas dilengkapi pada {$filled} baris. {$created} laporan POMEC dibuat.");

        return self::SUCCESS;
    }
}
