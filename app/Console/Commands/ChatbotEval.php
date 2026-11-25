<?php

namespace App\Console\Commands;

use App\Models\ChatbotTurn;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Console\Command;

/**
 * Golden-set evaluation against the REAL model. Run before changing the model, prompts or routing rules:
 *   php artisan chatbot:eval                     (model from .env)
 *   php artisan chatbot:eval --model=qwen3:8b    (try another model without touching .env)
 * Cases live in tests/fixtures/chatbot_eval.json; each has a role (user email), question, optional prior
 * questions ("context"), and expectations on source / reply text. Needs the demo seed data.
 */
class ChatbotEval extends Command
{
    protected $signature = 'chatbot:eval {--model= : Model to evaluate} {--file=tests/fixtures/chatbot_eval.json} {--min=90 : Minimum pass % for exit code 0} {--only= : Run cases whose id contains this text}';

    protected $description = 'Evaluate the chatbot on the golden question set';

    public function handle(ChatbotService $chatbot): int
    {
        if ($model = $this->option('model')) {
            config(['hotel.chatbot.model' => $model]);
        }
        $cases = json_decode((string) @file_get_contents(base_path($this->option('file'))), true);
        if (! is_array($cases)) {
            $this->error('Cannot read '.$this->option('file'));

            return self::FAILURE;
        }
        if ($only = $this->option('only')) {
            $cases = array_values(array_filter($cases, fn (array $c): bool => str_contains($c['id'], $only)));
        }

        $firstTurnId = (int) ChatbotTurn::max('id'); // eval turns are removed afterwards so Insights only show real usage
        $this->info('Model: '.config('hotel.chatbot.model').' • provider: '.config('hotel.chatbot.provider').' • '.count($cases).' cases');
        $rows = [];
        $pass = 0;
        $latencies = [];
        foreach ($cases as $c) {
            $user = User::where('email', $c['user'])->first();
            if (! $user) {
                $rows[] = [$c['id'], 'SKIP', '-', "user {$c['user']} not found", '-'];

                continue;
            }

            // Build the conversation the way a user would, so follow-up rules are exercised too.
            $chatbot->resetConversation();
            $history = [];
            foreach ($c['context'] ?? [] as $prev) {
                $r = $chatbot->answer($user, $prev, 'id', $history);
                $history = array_slice([...$history, ['role' => 'user', 'content' => $prev], ['role' => 'assistant', 'content' => $r['reply']]], -6);
            }

            $t = microtime(true);
            $r = $chatbot->answer($user, $c['question'], 'id', $history);
            $latencies[] = $ms = (int) round((microtime(true) - $t) * 1000);

            $problems = [];
            $source = $r['meta']['source'] ?? '-';
            if (isset($c['expect_source']) && ! in_array($source, (array) $c['expect_source'], true)) {
                $problems[] = 'source='.$source;
            }
            foreach ((array) ($c['expect_contains'] ?? []) as $needle) {
                if (mb_stripos($r['reply'], $needle) === false) {
                    $problems[] = "missing '{$needle}'";
                }
            }
            foreach ((array) ($c['expect_not_contains'] ?? []) as $needle) {
                if (mb_stripos($r['reply'], $needle) !== false) {
                    $problems[] = "contains '{$needle}'";
                }
            }
            $ok = $problems === [];
            $pass += $ok ? 1 : 0;
            $rows[] = [$c['id'], $ok ? 'PASS' : 'FAIL', $source, $ok ? '' : implode('; ', $problems), $ms.' ms'];
        }
        $chatbot->resetConversation();
        ChatbotTurn::where('id', '>', $firstTurnId)->delete();

        $this->table(['case', 'result', 'source', 'problem', 'latency'], $rows);
        sort($latencies);
        $p = fn (float $q): int => $latencies === [] ? 0 : $latencies[(int) floor((count($latencies) - 1) * $q)];
        $pct = count($cases) ? round($pass / count($cases) * 100, 1) : 0;
        $this->line("Passed {$pass}/".count($cases)." ({$pct}%) • latency p50 {$p(0.5)} ms • p95 {$p(0.95)} ms");

        return $pct >= (float) $this->option('min') ? self::SUCCESS : self::FAILURE;
    }
}
