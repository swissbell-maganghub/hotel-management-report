<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupPomecTempFiles extends Command
{
    protected $signature = 'pomec:cleanup-temp';

    protected $description = 'Remove expired temporary POMEC preview files from private storage';

    public function handle(): int
    {
        $ttlHours = max(1, (int) config('hotel.pomec_temp_ttl_hours', 2));
        $cutoff = now()->subHours($ttlHours)->getTimestamp();
        $disk = Storage::disk('local');
        $files = $disk->files('pomec/tmp');

        $deleted = 0;
        foreach ($files as $file) {
            if ($disk->lastModified($file) <= $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned up {$deleted} expired POMEC temporary preview file(s).");

        return self::SUCCESS;
    }
}
