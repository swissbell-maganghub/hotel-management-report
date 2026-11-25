<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTurn extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'question_excerpt', 'intent', 'source', 'filters', 'model',
        'latency_ms', 'llm_calls', 'fallback', 'rating', 'comment',
    ];

    protected function casts(): array
    {
        return ['fallback' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Numbers for the admin "Chatbot Insights" panel. */
    public static function insights(int $days = 30): array
    {
        $base = self::where('created_at', '>=', now()->subDays($days));
        $total = (clone $base)->count();
        $latencies = (clone $base)->where('llm_calls', '>', 0)->orderBy('latency_ms')->pluck('latency_ms')->all();
        $pct = fn (float $p): int => $latencies === [] ? 0 : (int) $latencies[(int) floor((count($latencies) - 1) * $p)];

        return [
            'days' => $days,
            'total' => $total,
            'up' => (clone $base)->where('rating', 1)->count(),
            'down' => (clone $base)->where('rating', -1)->count(),
            'fallback_pct' => $total ? round((clone $base)->where('fallback', true)->count() / $total * 100, 1) : 0,
            'p50_ms' => $pct(0.5),
            'p95_ms' => $pct(0.95),
            'by_source' => (clone $base)->selectRaw('source, COUNT(*) n')->groupBy('source')->orderByDesc('n')->limit(12)->pluck('n', 'source')->all(),
            // Grouped so the same failing question shows once with its count.
            'unmatched' => (clone $base)->where('source', 'unmatched')
                ->selectRaw('question_excerpt, COUNT(*) n, MAX(created_at) last_at')
                ->groupBy('question_excerpt')->orderByDesc('n')->orderByDesc('last_at')->limit(20)->get(),
            'downvoted' => (clone $base)->where('rating', -1)->with('user:id,name')->latest('created_at')->limit(20)->get(),
        ];
    }
}
