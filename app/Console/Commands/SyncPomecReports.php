<?php

namespace App\Console\Commands;

use App\Services\PomecExcelService;
use Illuminate\Console\Command;

class SyncPomecReports extends Command
{
    protected $signature = 'pomec:sync-reports';

    protected $description = 'Create an Engineering report (Submitted) for every POMEC upload that has none yet';

    public function handle(PomecExcelService $service): int
    {
        $created = $service->syncReports();
        $this->info("{$created} laporan POMEC dibuat.");

        return self::SUCCESS;
    }
}
