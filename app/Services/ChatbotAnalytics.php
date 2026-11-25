<?php

namespace App\Services;

use App\Models\DepartmentReport;
use App\Models\PomecRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deterministic, RBAC-scoped queries behind the chatbot. Every figure the assistant shows comes from here (spec 16.4);
 * the LLM only picks the intent and words the conclusion.
 */
class ChatbotAnalytics
{
    public const PERIODS = ['today', 'yesterday', 'this_week', 'last_7_days', 'this_month', 'last_month', 'all'];

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: string} [from, to, label] */
    public function periodRange(?string $period, bool $isEn): array
    {
        $now = CarbonImmutable::now('Asia/Makassar');

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay(), $isEn ? 'Today' : 'Hari ini'],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay(), $isEn ? 'Yesterday' : 'Kemarin'],
            'this_week' => [$now->startOfWeek(), $now->endOfDay(), $isEn ? 'This week' : 'Minggu ini'],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), $isEn ? 'Last 7 days' : '7 hari terakhir'],
            'this_month' => [$now->startOfMonth(), $now->endOfDay(), $now->format('M Y')],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), $now->subMonthNoOverflow()->format('M Y')],
            default => [null, null, $isEn ? 'All recorded periods' : 'Semua periode tercatat'],
        };
    }

    /**
     * Base report query with the user's RBAC scope applied first; filters can only narrow it.
     *
     * @param  array{department_ids?: ?array, from?: ?CarbonImmutable, to?: ?CarbonImmutable, statuses?: ?array, period_type?: ?string}  $f
     */
    public function reportsQuery(User $user, array $f): Builder
    {
        $q = DepartmentReport::with(['department', 'template'])->latest('period_date');
        if (! $user->canAccessAllDepartments()) {
            $q->whereIn('department_id', $user->accessibleDepartmentIds() ?: [-1]);
        }
        if (! empty($f['department_ids'])) {
            $q->whereIn('department_id', $f['department_ids']);
        }
        if (! empty($f['from'])) {
            $q->whereDate('period_date', '>=', $f['from']->toDateString());
        }
        if (! empty($f['to'])) {
            $q->whereDate('period_date', '<=', $f['to']->toDateString());
        }
        if (! empty($f['statuses'])) {
            $q->whereIn('status', $f['statuses']);
        }
        if (! empty($f['period_type'])) {
            $q->where('period_type', $f['period_type']);
        }

        return $q;
    }

    /** Status counts, per-department breakdown, pending ranking and stale reviews. */
    public function reportStats(User $user, array $f): array
    {
        $reports = $this->reportsQuery($user, $f)->get();
        $pending = [DepartmentReport::STATUS_SUBMITTED, DepartmentReport::STATUS_IN_REVIEW, DepartmentReport::STATUS_RETURNED];
        $staleBefore = CarbonImmutable::now()->subDays((int) config('hotel.chatbot.thresholds.review_sla_days', 2));

        $byDept = $reports->groupBy(fn (DepartmentReport $r): string => $r->department->code)
            ->map(fn ($g): array => [
                'name' => $g->first()->department->name,
                'total' => $g->count(),
                'pending' => $g->whereIn('status', $pending)->count(),
                'statuses' => $g->countBy('status')->all(),
            ])
            ->sortByDesc('pending');

        return [
            'total' => $reports->count(),
            'by_status' => $reports->countBy('status')->all(),
            'by_department' => $byDept->all(),
            'stale' => $reports->filter(fn (DepartmentReport $r): bool => in_array($r->status, [DepartmentReport::STATUS_SUBMITTED, DepartmentReport::STATUS_IN_REVIEW], true)
                && $r->submitted_at && $r->submitted_at->lt($staleBefore))->values(),
            'returned' => $reports->where('status', DepartmentReport::STATUS_RETURNED)->values(),
        ];
    }

    public function reportDetail(User $user, string $code): ?DepartmentReport
    {
        return $this->reportsQuery($user, [])
            ->with(['histories' => fn ($h) => $h->with('user')->orderBy('created_at'), 'creator', 'reviewer'])
            ->where('report_code', $code)
            ->first();
    }

    /** Electricity/water totals plus peak day and largest meter for a window (null = all time). */
    public function pomecTotals(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $base = PomecRecord::query()
            ->when($from, fn ($q) => $q->whereDate('reading_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('reading_date', '<=', $to->toDateString()));
        $elec = (clone $base)->where('utility_type', 'electricity');
        $water = (clone $base)->where('utility_type', 'water');

        $peak = (clone $elec)->selectRaw('DATE(reading_date) d, SUM(total_usage) v')->groupBy('d')->orderByDesc('v')->first();
        $topMeter = (clone $elec)->selectRaw('meter_name, SUM(total_usage) v')->groupBy('meter_name')->orderByDesc('v')->first();
        $elecTotal = (float) (clone $elec)->sum('total_usage');
        $wbp = (float) (clone $elec)->sum('wbp_value');

        return [
            'electricity_kwh' => $elecTotal,
            'lwbp_kwh' => (float) (clone $elec)->sum('lwbp_value'),
            'wbp_kwh' => $wbp,
            'wbp_share_pct' => $elecTotal > 0 ? round($wbp / $elecTotal * 100, 1) : null,
            'water_m3' => (float) $water->sum('water_m3'),
            'readings' => (clone $base)->count(),
            'days' => (clone $base)->distinct()->count('reading_date'),
            'peak_day' => $peak ? ['date' => (string) $peak->d, 'kwh' => (float) $peak->v] : null,
            'top_meter' => $topMeter ? ['name' => $topMeter->meter_name, 'kwh' => (float) $topMeter->v] : null,
        ];
    }

    /** Current window vs the immediately preceding window of equal length. */
    public function pomecCompare(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $prevTo = $from->subDay()->endOfDay();
        $prevFrom = $prevTo->subDays($days - 1)->startOfDay();
        $cur = $this->pomecTotals($from, $to);
        $prev = $this->pomecTotals($prevFrom, $prevTo);
        $delta = fn (float $a, float $b): ?float => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

        return [
            'current' => $cur + ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'previous' => $prev + ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
            'electricity_change_pct' => $delta($cur['electricity_kwh'], $prev['electricity_kwh']),
            'water_change_pct' => $delta($cur['water_m3'], $prev['water_m3']),
            'previous_available' => $prev['readings'] > 0,
        ];
    }

    /** Daily series for one metric, oldest first. */
    public function pomecDaily(?CarbonImmutable $from, ?CarbonImmutable $to, string $metric): array
    {
        $isWater = $metric === 'water';

        return PomecRecord::where('utility_type', $isWater ? 'water' : 'electricity')
            ->when($from, fn ($q) => $q->whereDate('reading_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('reading_date', '<=', $to->toDateString()))
            ->selectRaw('DATE(reading_date) d, SUM('.($isWater ? 'water_m3' : 'total_usage').') v')
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r): array => ['date' => (string) $r->d, 'value' => (float) $r->v])->all();
    }

    /**
     * Schema-driven aggregation of dynamic `rows_data` across any ReportTemplate.
     * Adapts automatically when department templates or columns change.
     *
     * @param  iterable<int, DepartmentReport>  $reports
     * @return array<int, array<string, mixed>>
     */
    public function summarizeReportRows(iterable $reports, bool $isEn = false): array
    {
        $groups = [];
        $alertRe = '/^(critical|urgent|need repair|follow[- ]?up|investigating|lost|pending bank|rusak|bermasalah|perlu perbaikan)$/i';

        foreach ($reports as $report) {
            $template = $report->template;
            $schema = is_array($template?->columns_schema) ? $template->columns_schema : [];
            $rows = is_array($report->rows_data) ? $report->rows_data : [];
            if ($schema === [] || $rows === []) {
                continue;
            }

            $tplCode = $template?->code ?? ('DEPT-'.$report->department_id);
            if (! isset($groups[$tplCode])) {
                $groups[$tplCode] = [
                    'template_code' => $tplCode,
                    'template_name' => $template ? $template->localizedName($isEn ? 'en' : 'id') : $tplCode,
                    'department' => $isEn
                        ? ($report->department?->name_en ?: $report->department?->name ?? '')
                        : ($report->department?->name ?? ''),
                    'report_count' => 0,
                    'row_count' => 0,
                    'numeric' => [],
                    'select' => [],
                    'top_rows' => [],
                    'alert_rows' => [],
                    'sample_rows' => [],
                ];
            }

            $g = &$groups[$tplCode];
            $g['report_count']++;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $g['row_count']++;
                $sample = [];
                $isAlert = false;

                foreach ($schema as $col) {
                    $key = (string) ($col['key'] ?? '');
                    if ($key === '' || ! array_key_exists($key, $row)) {
                        continue;
                    }
                    $val = $row[$key];
                    $type = (string) ($col['type'] ?? 'text');
                    $label = $isEn ? (string) ($col['label_en'] ?? $col['label'] ?? $key) : (string) ($col['label'] ?? $key);

                    if ($val !== null && $val !== '') {
                        $sample[$label] = is_scalar($val) ? (string) $val : json_encode($val);
                    }

                    if (in_array($type, ['number', 'currency', 'percent', 'decimal', 'float', 'integer'], true) || ! empty($col['sum'])) {
                        if (is_numeric($val)) {
                            $num = (float) $val;
                            if (! isset($g['numeric'][$key])) {
                                $g['numeric'][$key] = [
                                    'key' => $key,
                                    'label' => $label,
                                    'type' => $type,
                                    'sum' => 0.0,
                                    'count' => 0,
                                    'min' => $num,
                                    'max' => $num,
                                ];
                            }
                            $g['numeric'][$key]['sum'] += $num;
                            $g['numeric'][$key]['count']++;
                            $g['numeric'][$key]['min'] = min($g['numeric'][$key]['min'], $num);
                            $g['numeric'][$key]['max'] = max($g['numeric'][$key]['max'], $num);

                            if (! isset($g['top_rows'][$key]) || $num > $g['top_rows'][$key]['value']) {
                                $g['top_rows'][$key] = [
                                    'column' => $label,
                                    'value' => $num,
                                    'report_code' => $report->report_code,
                                    'date' => $report->period_date?->format('Y-m-d'),
                                    'row' => $sample,
                                ];
                            }
                        }
                    } elseif ($type === 'select' && is_string($val) && trim($val) !== '') {
                        $opt = trim($val);
                        if (! isset($g['select'][$key])) {
                            $g['select'][$key] = ['key' => $key, 'label' => $label, 'counts' => []];
                        }
                        $g['select'][$key]['counts'][$opt] = ($g['select'][$key]['counts'][$opt] ?? 0) + 1;
                        if (preg_match($alertRe, $opt)) {
                            $isAlert = true;
                        }
                    }
                }

                if ($isAlert && $sample !== [] && count($g['alert_rows']) < 8) {
                    $g['alert_rows'][] = ['report_code' => $report->report_code, 'date' => $report->period_date?->format('Y-m-d')] + $sample;
                }
                if ($sample !== [] && count($g['sample_rows']) < 6) {
                    $g['sample_rows'][] = $sample;
                }
            }
            unset($g);
        }

        return array_values(array_map(function (array $g): array {
            $numeric = array_values(array_map(function (array $n): array {
                $n['sum'] = round($n['sum'], 2);
                $n['avg'] = $n['count'] > 0 ? round($n['sum'] / $n['count'], 2) : 0.0;

                return $n;
            }, $g['numeric']));

            return [
                'template_code' => $g['template_code'],
                'template_name' => $g['template_name'],
                'department' => $g['department'],
                'report_count' => $g['report_count'],
                'row_count' => $g['row_count'],
                'numeric_totals' => $numeric,
                'category_counts' => array_values($g['select']),
                'top_rows' => array_values(array_slice($g['top_rows'], 0, 3)),
                'alert_rows' => $g['alert_rows'],
                'sample_rows' => $g['sample_rows'],
            ];
        }, $groups));
    }

    /**
     * Find specific rows inside dynamic `rows_data` matching question keywords or alert statuses.
     *
     * @param  iterable<int, DepartmentReport>  $reports
     * @return array<int, array<string, mixed>>
     */
    public function searchReportRows(iterable $reports, string $question, bool $isEn = false): array
    {
        $stop = ['apa', 'saja', 'yang', 'mana', 'siapa', 'berapa', 'bagaimana', 'gimana', 'kapan', 'kenapa', 'mengapa',
            'dari', 'untuk', 'dengan', 'pada', 'dalam', 'atau', 'dan', 'ini', 'itu', 'ada', 'bisa', 'cek', 'cari',
            'lihat', 'tampilkan', 'laporan', 'departemen', 'dept', 'hari', 'minggu', 'bulan', 'kemarin', 'terbaru',
            'status', 'what', 'which', 'who', 'where', 'when', 'how', 'why', 'the', 'and', 'for', 'with', 'from', 'report', 'reports'];
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [])
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, $stop, true))
            ->unique()
            ->values()
            ->all();

        $wantsIssues = (bool) preg_match('/(masalah|kendala|rusak|kritis|critical|urgent|repair|follow|investigat|bermasalah|alert|anomali|issue|problem|aman|bahaya|risiko|gangguan|macet|bocor)/iu', $question);
        $wantsTop = (bool) preg_match('/(tertinggi|terbesar|terbanyak|paling ramai|paling tinggi|paling besar|paling banyak|terendah|paling kecil|highest|largest|top|lowest)/iu', $question);
        $alertRe = '/\b(critical|urgent|need repair|follow[- ]?up|investigating|lost|pending bank)\b/i';

        $matches = [];
        foreach ($reports as $report) {
            $schema = is_array($report->template?->columns_schema) ? $report->template->columns_schema : [];
            $rows = is_array($report->rows_data) ? $report->rows_data : [];
            if ($schema === [] || $rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $labeled = [];
                $haystackParts = [];
                $maxNumeric = 0.0;

                foreach ($schema as $col) {
                    $key = (string) ($col['key'] ?? '');
                    if ($key === '' || ! array_key_exists($key, $row)) {
                        continue;
                    }
                    $val = $row[$key];
                    if ($val === null || $val === '') {
                        continue;
                    }
                    $label = $isEn ? (string) ($col['label_en'] ?? $col['label'] ?? $key) : (string) ($col['label'] ?? $key);
                    $str = is_scalar($val) ? (string) $val : (string) json_encode($val);
                    $labeled[$label] = $str;
                    $haystackParts[] = mb_strtolower($label.' '.$str);
                    if (is_numeric($val)) {
                        $maxNumeric = max($maxNumeric, (float) $val);
                    }
                }

                if ($labeled === []) {
                    continue;
                }

                $haystack = implode(' ', $haystackParts);
                $score = 0;
                foreach ($words as $w) {
                    if (str_contains($haystack, $w)) {
                        $score += 10;
                    }
                }
                if ($wantsIssues && preg_match($alertRe, $haystack)) {
                    $score += 15;
                }
                if ($wantsTop && $maxNumeric > 0) {
                    $score += 1;
                }

                if ($score > 0) {
                    $matches[] = [
                        'score' => $score,
                        'numeric' => $maxNumeric,
                        'report_code' => $report->report_code,
                        'department' => $isEn ? ($report->department?->name_en ?: $report->department?->name ?? '') : ($report->department?->name ?? ''),
                        'date' => $report->period_date?->format('Y-m-d'),
                        'row' => $labeled,
                    ];
                }
            }
        }

        usort($matches, fn (array $a, array $b): int => [$b['score'], $b['numeric']] <=> [$a['score'], $a['numeric']]);

        return array_slice(array_map(function (array $m): array {
            unset($m['score'], $m['numeric']);

            return $m;
        }, $matches), 0, 10);
    }

    /**
     * Compare report counts and dynamic table metrics in $from..$to against the immediately preceding window of equal length.
     *
     * @param  array{department_ids?: ?array, statuses?: ?array, period_type?: ?string}  $f
     * @return array<string, mixed>
     */
    public function compareReports(User $user, array $f, CarbonImmutable $from, CarbonImmutable $to, bool $isEn = false): array
    {
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $prevTo = $from->subDay()->endOfDay();
        $prevFrom = $prevTo->subDays($days - 1)->startOfDay();

        $curReports = $this->reportsQuery($user, ['from' => $from, 'to' => $to] + $f)->limit(100)->get();
        $prevReports = $this->reportsQuery($user, ['from' => $prevFrom, 'to' => $prevTo] + $f)->limit(100)->get();

        $curSummaries = collect($this->summarizeReportRows($curReports, $isEn))->keyBy('template_code');
        $prevSummaries = collect($this->summarizeReportRows($prevReports, $isEn))->keyBy('template_code');

        $comparisons = [];
        foreach ($curSummaries as $tplCode => $curTpl) {
            $prevTpl = $prevSummaries->get($tplCode);
            $prevNums = collect($prevTpl['numeric_totals'] ?? [])->keyBy('key');
            $metrics = [];

            foreach ($curTpl['numeric_totals'] ?? [] as $curNum) {
                $prevNum = $prevNums->get($curNum['key']);
                $prevSum = $prevNum ? (float) $prevNum['sum'] : null;
                $curSum = (float) $curNum['sum'];
                $changePct = ($prevSum !== null && $prevSum > 0) ? round(($curSum - $prevSum) / $prevSum * 100, 1) : null;

                $metrics[] = [
                    'key' => $curNum['key'],
                    'label' => $curNum['label'],
                    'type' => $curNum['type'],
                    'current_sum' => $curSum,
                    'previous_sum' => $prevSum,
                    'change_pct' => $changePct,
                    'current_avg' => (float) $curNum['avg'],
                    'previous_avg' => $prevNum ? (float) $prevNum['avg'] : null,
                ];
            }

            if ($metrics !== []) {
                $comparisons[] = [
                    'template_code' => $tplCode,
                    'template_name' => $curTpl['template_name'],
                    'department' => $curTpl['department'],
                    'current_reports' => $curTpl['report_count'],
                    'previous_reports' => $prevTpl['report_count'] ?? 0,
                    'metrics' => $metrics,
                ];
            }
        }

        return [
            'current_window' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'report_count' => $curReports->count()],
            'previous_window' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString(), 'report_count' => $prevReports->count()],
            'previous_available' => $prevReports->isNotEmpty(),
            'templates' => $comparisons,
        ];
    }

    /**
     * Cross-module snapshot (POMEC utilities + accessible department report metrics/notes) for the same window.
     *
     * @return array<string, mixed>
     */
    public function crossModuleCorrelation(User $user, CarbonImmutable $from, CarbonImmutable $to, bool $isEn = false): array
    {
        $reports = $this->reportsQuery($user, ['from' => $from, 'to' => $to])->limit(100)->get();

        return [
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'pomec' => $user->canAccessPomec() ? $this->pomecCompare($from, $to) : null,
            'reports_count' => $reports->count(),
            'table_metrics' => $this->summarizeReportRows($reports, $isEn),
            'report_highlights' => $reports->take(10)->map(fn (DepartmentReport $r): array => [
                'code' => $r->report_code,
                'department' => $isEn ? ($r->department?->name_en ?: $r->department?->name) : $r->department?->name,
                'date' => $r->period_date?->format('Y-m-d'),
                'status' => $r->status,
                'summary_notes' => $r->summary_notes,
                'review_note' => $r->review_note,
            ])->all(),
        ];
    }
}
