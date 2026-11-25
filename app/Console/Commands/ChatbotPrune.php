<?php

namespace App\Console\Commands;

use App\Models\ChatbotTurn;
use Illuminate\Console\Command;

/** Retention for chatbot turn logs (spec 16.4). Scheduled daily in routes/console.php. */
class ChatbotPrune extends Command
{
    protected $signature = 'chatbot:prune {--days= : Override hotel.chatbot.retention_days}';

    protected $description = 'Delete chatbot turn logs older than the retention period';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('hotel.chatbot.retention_days', 90)));
        $deleted = ChatbotTurn::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} chatbot turn(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
