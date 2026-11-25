<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * IT-managed chatbot knowledge (spec 17). Trigger syntax, one rule per line:
 *   "cara|panduan|how + ekspor|export|pdf"  → every "+" group must match; "|" separates alternatives
 *   "=lwbp"                                  → the whole message equals the term
 * Alternatives match at a word start; terms of ≤3 chars ("fo", "gm") must be whole words.
 */
class ChatbotKnowledge extends Model
{
    protected $table = 'chatbot_knowledge';

    public const TYPES = ['glossary', 'guide', 'faq'];

    private const CACHE_KEY = 'chatbot.knowledge.active';

    protected $fillable = ['slug', 'type', 'title', 'triggers', 'body_id', 'body_en', 'roles', 'priority', 'is_active'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'is_active' => 'boolean', 'priority' => 'integer', 'hits' => 'integer'];
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /** Active articles, cached until an admin edits one. */
    public static function active(): Collection
    {
        // Cache raw attribute arrays, not models: the cache store refuses to unserialize objects (serializable_classes).
        return self::hydrate(Cache::rememberForever(self::CACHE_KEY,
            fn () => self::where('is_active', true)->get()->map(fn (self $k): array => $k->getAttributes())->all()));
    }

    /** Lowercase, keep letters/digits/&/spaces; collapse whitespace. */
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}&\s]/u', ' ', Str::lower($text))));
    }

    /** Best match score for a normalized question (0 = no match). Longer matched text wins. */
    public function score(string $q): int
    {
        $best = 0;
        foreach (preg_split('/\R/', (string) $this->triggers) as $rule) {
            $rule = trim(Str::lower($rule));
            if ($rule === '') {
                continue;
            }
            if (str_starts_with($rule, '=')) {
                $term = self::normalize(substr($rule, 1));
                $best = max($best, $q === $term ? 1000 + strlen($term) : 0);

                continue;
            }
            $score = 0;
            foreach (explode('+', $rule) as $group) {
                $hit = 0;
                foreach (explode('|', $group) as $alt) {
                    $alt = self::normalize($alt);
                    if ($alt !== '' && self::contains($q, $alt)) {
                        $hit = max($hit, strlen($alt));
                    }
                }
                if ($hit === 0) {
                    $score = 0;
                    break;
                }
                $score += $hit;
            }
            $best = max($best, $score);
        }

        return $best;
    }

    public function visibleTo(User $user): bool
    {
        return empty($this->roles) || in_array($user->role, $this->roles, true);
    }

    private static function contains(string $q, string $alt): bool
    {
        $quoted = preg_quote($alt, '/');

        // Short terms must be whole words ("fo" ≠ "format"); longer ones only need a word start ("ekspor" ~ "ekspornya").
        if (preg_match(strlen($alt) <= 3 ? "/(?<![\\p{L}\\p{N}&]){$quoted}(?![\\p{L}\\p{N}&])/u" : "/(?<![\\p{L}\\p{N}&]){$quoted}/u", $q)) {
            return true;
        }

        // Typo tolerance for single-word terms ≥ 6 chars (e.g. "hosekiping", "hosskiping", "houseekeeping" → "housekeeping").
        if (strlen($alt) < 6 || str_contains($alt, ' ')) {
            return false;
        }

        $altCollapsed = preg_replace('/(.)\1+/u', '$1', $alt);
        foreach (preg_split('/\s+/u', $q) as $word) {
            if (strlen($word) < 5 || $word[0] !== $alt[0]) {
                continue;
            }
            $wordCollapsed = preg_replace('/(.)\1+/u', '$1', $word);
            $maxDist = strlen($alt) >= 9 ? 3 : 2;
            similar_text($wordCollapsed, $altCollapsed, $pct);
            if (levenshtein($wordCollapsed, $altCollapsed) <= $maxDist && $pct >= 72.0) {
                return true;
            }
        }

        return false;
    }
}
