@php
    $isEn = ($locale ?? 'id') === 'en';
    $witaNow = now()->timezone('Asia/Makassar');
    $statusBadgeMap = [
        'draft' => ['bg-slate-100 text-slate-700 border-slate-200', 'Draft'],
        'submitted' => ['bg-amber-50 text-amber-800 border-amber-200', 'Submitted'],
        'in_review' => ['bg-cyan-50 text-teal border-cyan-200', 'Review HOD'],
        'returned' => ['bg-rose-50 text-crimson-dark border-rose-200', 'Returned / Revision'],
        'reviewed' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Reviewed'],
    ];

    $matrix = $departmentMatrix ?? collect();
    $deptTotal = max(1, $matrix->count());
    $reportedDeptCount = $matrix->filter(fn ($row) => $row['latest'] !== null)->count();
    $compliancePct = (int) round(($reportedDeptCount / $deptTotal) * 100);

    $fo = $hotelPulse['fo'] ?? [];
    $fnb = $hotelPulse['fnb'] ?? [];
    $hk = $hotelPulse['hk'] ?? [];
    $eng = $hotelPulse['eng'] ?? [];
    $fin = $hotelPulse['fin'] ?? [];
    $sls = $hotelPulse['sls'] ?? [];
    $hr = $hotelPulse['hr'] ?? [];

    $latestElecImport = $pomecImports->firstWhere('utility_type', 'electricity');
    $statusCounts = $gmCharts['reportStatus'] ?? [
        'reviewed' => $reviewedCount,
        'submitted' => $pendingReviewCount,
        'returned' => $returnedCount,
        'draft' => $reports->where('status', 'draft')->count(),
        'total' => $reports->count(),
    ];
@endphp

<div class="space-y-5">
    <!-- Slim Executive Header Bar -->
    <section class="bg-white border border-slate-200/80 rounded-2xl px-5 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-crimson text-white text-[10px] font-mono-num font-bold tracking-wider uppercase shadow-2xs">
                    {{ $user->isItAdmin() ? 'Executive Dashboard • Preview IT Admin' : 'General Manager Executive View' }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-mono-num font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span id="gm-wita-live-clock" data-wita-clock>{{ $witaNow->format('D, d M Y • H:i:s') }} WITA</span>
                </span>
            </div>
            <h2 class="text-base sm:text-lg font-heading font-extrabold text-navy">
                Swiss-Belhotel Makassar — Executive Daily Briefing
            </h2>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <div class="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                <span class="text-slate-500">{{ $isEn ? 'Dept. Coverage' : 'Kepatuhan Dept.' }}</span>
                <span class="font-mono-num font-bold text-navy">{{ $reportedDeptCount }}/{{ $matrix->count() }} ({{ $compliancePct }}%)</span>
            </div>
            <button type="button" onclick="switchWorkspaceTab('reports')" class="px-3 py-1.5 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                {{ $isEn ? 'All Reports' : 'Semua Laporan' }}
            </button>
            @if ($canPomec)
                <button type="button" onclick="switchWorkspaceTab('pomec')" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy text-xs font-semibold transition cursor-pointer">
                    POMEC
                </button>
            @endif
        </div>
    </section>

    <!-- Clean 4-Card Hotel Operational Pulse -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Front Office -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-mono-num font-bold tracking-wider text-navy bg-navy/8 px-2 py-0.5 rounded-md">FO • OCCUPANCY</span>
                    @if (!empty($fo['report_id']))
                        <button type="button" onclick="openReportDetail({{ $fo['report_id'] }})" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Detail &rarr;</button>
                    @endif
                </div>
                <div class="flex items-baseline justify-between">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">{{ number_format((float) ($fo['occupancy_pct'] ?? 0), 1) }}%</div>
                    <span class="text-xs font-mono-num text-slate-500">{{ $fo['occupied'] ?? 0 }}/{{ $fo['available'] ?? 0 }} {{ $isEn ? 'Rooms' : 'Kamar' }}</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-navy rounded-full" style="width: {{ min(100, (float) ($fo['occupancy_pct'] ?? 0)) }}%"></div>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400">
                <span>In {{ $fo['arrivals'] ?? 0 }} &bull; Out {{ $fo['departures'] ?? 0 }}</span>
                <span>{{ $fo['updated_wita'] ?? '-' }}</span>
            </div>
        </div>

        <!-- F&B -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-mono-num font-bold tracking-wider text-teal bg-teal/10 px-2 py-0.5 rounded-md">F&amp;B • REVENUE</span>
                    @if (!empty($fnb['report_id']))
                        <button type="button" onclick="openReportDetail({{ $fnb['report_id'] }})" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Detail &rarr;</button>
                    @endif
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-xl font-heading font-extrabold font-mono-num text-navy">Rp {{ number_format(((float) ($fnb['total_rev'] ?? 0)) / 1000000, 1, ',', '.') }} Jt</div>
                    <span class="text-xs font-mono-num font-semibold text-teal">{{ number_format((int) ($fnb['covers'] ?? 0), 0, ',', '.') }} Covers</span>
                </div>
                <div class="text-[11px] font-mono-num text-slate-500">
                    Food {{ number_format(((float) ($fnb['food_rev'] ?? 0)) / 1000000, 1, ',', '.') }} Jt &bull; Bev {{ number_format(((float) ($fnb['bev_rev'] ?? 0)) / 1000000, 1, ',', '.') }} Jt
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                {{ $fnb['updated_wita'] ?? '-' }}
            </div>
        </div>

        <!-- Housekeeping -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-mono-num font-bold tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">HK • READINESS</span>
                    @if (!empty($hk['report_id']))
                        <button type="button" onclick="openReportDetail({{ $hk['report_id'] }})" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Detail &rarr;</button>
                    @endif
                </div>
                <div class="flex items-baseline justify-between">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-emerald-700">{{ number_format((float) ($hk['ready_pct'] ?? 0), 1) }}%</div>
                    <span class="text-xs font-mono-num text-slate-500">{{ $hk['total_rooms'] ?? 0 }} {{ $isEn ? 'Rooms' : 'Kamar' }}</span>
                </div>
                <div class="text-[11px] font-mono-num text-slate-500">
                    VC {{ $hk['vc'] ?? 0 }} &bull; OC {{ $hk['oc'] ?? 0 }} &bull; VD {{ $hk['vd'] ?? 0 }} &bull; OOO {{ $hk['ooo'] ?? 0 }}
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                {{ $hk['updated_wita'] ?? ($isEn ? 'Awaiting HK report' : 'Belum ada data HK') }}
            </div>
        </div>

        <!-- Engineering -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-mono-num font-bold tracking-wider text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md">ENG • ASSETS</span>
                    @if (!empty($eng['report_id']))
                        <button type="button" onclick="openReportDetail({{ $eng['report_id'] }})" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Detail &rarr;</button>
                    @endif
                </div>
                <div class="flex items-baseline justify-between">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">{{ $eng['total_items'] ?? 0 }} <span class="text-xs font-normal text-slate-500">Unit</span></div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold {{ ($eng['issues_count'] ?? 0) > 0 ? 'bg-rose-50 text-crimson-dark border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                        {{ $eng['issues_count'] ?? 0 }} {{ $isEn ? 'Alert' : 'Temuan' }}
                    </span>
                </div>
                <div class="text-[11px] font-mono-num text-slate-500">
                    Downtime: <strong class="text-navy">{{ $eng['downtime_min'] ?? 0 }} {{ $isEn ? 'min' : 'menit' }}</strong>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                {{ $eng['updated_wita'] ?? '-' }}
            </div>
        </div>
    </section>

    <!-- Row 2: Executive Charts (POMEC Utility Trend + Report Workflow Status) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'POMEC Utility Trend — Electricity & Water' : 'Ringkasan Eksekutif Energi & Air (POMEC)' }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ $isEn ? 'Daily LWBP vs WBP (kWh) & PDAM Water (m³)' : 'Tren harian listrik LWBP vs WBP (kWh) dan konsumsi air PDAM (m³)' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono-num">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200/80 text-navy">
                        LWBP <strong>{{ number_format($electricityLwbp, 0, ',', '.') }}</strong>
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-amber-50/70 border border-amber-200/70 text-amber-800">
                        WBP <strong>{{ number_format($electricityWbp, 0, ',', '.') }}</strong>
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-cyan-50/70 border border-teal/20 text-teal">
                        PDAM <strong>{{ number_format($waterTotal, 0, ',', '.') }} m³</strong>
                    </span>
                </div>
            </div>

            <div class="relative h-64 w-full">
                <canvas id="gm-chart-pomec-trend"></canvas>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 text-[11px] text-slate-400 font-mono-num">
                <span>
                    {{ $isEn ? 'Latest commit:' : 'Impor terakhir:' }}
                    {{ $latestElecImport?->committed_at ? $latestElecImport->committed_at->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' : '-' }}
                </span>
                @if ($canPomec)
                    <button type="button" onclick="switchWorkspaceTab('pomec')" class="font-sans text-xs font-semibold text-teal hover:underline cursor-pointer">
                        {{ $isEn ? 'Open POMEC Detail' : 'Detail POMEC' }} &rarr;
                    </button>
                @endif
            </div>
        </div>

        <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'Report Verification Distribution' : 'Distribusi Status Verifikasi Laporan' }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ $isEn ? 'Real-time status across all departments' : 'Proporsi status laporan lintas 8 departemen' }}
                    </p>
                </div>
                <span class="text-xs font-mono-num font-bold text-navy bg-slate-100 px-2.5 py-1 rounded-lg">
                    {{ $statusCounts['total'] }} {{ $isEn ? 'Reports' : 'Laporan' }}
                </span>
            </div>

            <div class="relative h-52 w-full flex items-center justify-center">
                <canvas id="gm-chart-report-status"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-2xl font-heading font-extrabold font-mono-num text-navy leading-none">{{ $statusCounts['total'] }}</span>
                    <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">{{ $isEn ? 'Total' : 'Laporan' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 pt-2 border-t border-slate-100 text-center font-mono-num text-[11px]">
                <div class="rounded-xl bg-emerald-50/70 border border-emerald-200/70 py-1.5">
                    <div class="font-bold text-emerald-700">{{ $statusCounts['reviewed'] }}</div>
                    <div class="text-[10px] text-emerald-800">Reviewed</div>
                </div>
                <div class="rounded-xl bg-amber-50/70 border border-amber-200/70 py-1.5">
                    <div class="font-bold text-amber-700">{{ $statusCounts['submitted'] }}</div>
                    <div class="text-[10px] text-amber-800">Submitted</div>
                </div>
                <div class="rounded-xl bg-rose-50/70 border border-rose-200/70 py-1.5">
                    <div class="font-bold text-crimson-dark">{{ $statusCounts['returned'] }}</div>
                    <div class="text-[10px] text-crimson-dark">Returned</div>
                </div>
                <div class="rounded-xl bg-slate-50 border border-slate-200 py-1.5">
                    <div class="font-bold text-slate-700">{{ $statusCounts['draft'] }}</div>
                    <div class="text-[10px] text-slate-500">Draft</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Row 3: Compact 8-Department Compliance Table + Room Mix Chart & Results -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? '8-Department Reporting Compliance Matrix' : 'Matriks Kepatuhan Laporan 8 Departemen' }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ $isEn ? 'Latest report & update timestamp (Makassar WITA)' : 'Laporan terbaru & waktu pembaruan masing-masing departemen (WITA)' }}
                    </p>
                </div>
                <button type="button" onclick="switchWorkspaceTab('reports')" class="text-xs font-semibold text-teal hover:underline shrink-0 cursor-pointer">
                    {{ $isEn ? 'View All' : 'Semua' }} &rarr;
                </button>
            </div>

            <div class="overflow-x-auto flex-1">
                <table class="w-full min-w-[680px] text-left border-collapse text-xs">
                    <thead class="bg-slate-50/80 text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Dept.' : 'Departemen' }}</th>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Latest Report' : 'Laporan Terbaru' }}</th>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Updated (WITA)' : 'Pembaruan (WITA)' }}</th>
                            <th class="py-3.5 px-5 font-semibold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($matrix as $entry)
                            @php
                                /** @var \App\Models\Department $dept */
                                $dept = $entry['department'];
                                /** @var \App\Models\DepartmentReport|null $latest */
                                $latest = $entry['latest'];
                                [$badgeClass, $badgeLabel] = $latest
                                    ? ($statusBadgeMap[$latest->status] ?? ['bg-slate-100 text-slate-700 border-slate-200', ucfirst($latest->status)])
                                    : ['bg-slate-50 text-slate-400 border-slate-200', $isEn ? 'None' : 'Belum Ada'];
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <div class="flex items-center gap-2.5">
                                        <span class="px-2 py-0.5 rounded bg-navy/8 text-navy font-mono-num text-[10px] font-bold">{{ $dept->code }}</span>
                                        <span class="font-semibold text-navy">{{ $dept->localizedName($locale) }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 min-w-[220px] max-w-[280px]">
                                    @if ($latest)
                                        <button type="button" onclick="openReportDetail({{ $latest->id }})" class="text-left font-medium text-slate-700 hover:text-teal truncate block w-full cursor-pointer" title="{{ $latest->title }}">
                                            {{ $latest->title }}
                                        </button>
                                        <div class="text-[10px] font-mono-num text-slate-400 mt-0.5">{{ $latest->report_code }} &bull; {{ $latest->period_date->format('d M Y') }}</div>
                                    @else
                                        <span class="text-slate-400 italic">{{ $isEn ? 'No submission yet' : 'Belum ada laporan' }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 font-mono-num text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $entry['latest_wita'] ?? '-' }}
                                </td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border {{ $badgeClass }}">
                                        {{ $badgeLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lg:col-span-5 space-y-5 flex flex-col">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-heading font-bold text-navy">
                            {{ $isEn ? 'Housekeeping Room Status Mix' : 'Komposisi Status Kamar Housekeeping' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $isEn ? 'VC, OC, VD & OOO distribution' : 'Distribusi kamar VC, OC, VD, dan OOO' }}
                        </p>
                    </div>
                    @if (!empty($hk['report_id']))
                        <button type="button" onclick="openReportDetail({{ $hk['report_id'] }})" class="text-xs font-semibold text-teal hover:underline cursor-pointer">
                            Detail &rarr;
                        </button>
                    @endif
                </div>

                <div class="relative h-44 w-full">
                    <canvas id="gm-chart-hk-rooms"></canvas>
                </div>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3.5 flex-1 flex flex-col justify-between">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-heading font-bold text-navy">{{ $isEn ? 'Business, Sales & HR Results' : 'Ringkasan Hasil Bisnis & SDM' }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ $isEn ? 'Finance settlement, MICE pipeline & staffing attendance' : 'Settlement Finance, pipeline Sales MICE & kehadiran SDM' }}
                        </p>
                    </div>
                    <button type="button" onclick="switchWorkspaceTab('reports')" class="text-xs font-semibold text-teal hover:underline cursor-pointer">
                        {{ $isEn ? 'Reports' : 'Laporan' }} &rarr;
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    <div class="rounded-xl bg-slate-50/80 border border-slate-200/80 p-3 space-y-1">
                        <div class="text-[10px] font-mono-num font-bold text-slate-500 uppercase">Finance</div>
                        <div class="text-sm font-heading font-extrabold font-mono-num text-navy">
                            Rp {{ number_format(((float) ($fin['gross_amount'] ?? 0)) / 1000000, 1, ',', '.') }} Jt
                        </div>
                        <div class="text-[10px] font-mono-num text-emerald-700">
                            {{ ($fin['variance'] ?? 0) == 0 ? ($isEn ? '0 variance' : 'Selisih Rp 0') : 'Var Rp '.number_format((float) $fin['variance'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50/80 border border-slate-200/80 p-3 space-y-1">
                        <div class="text-[10px] font-mono-num font-bold text-slate-500 uppercase">Sales MICE</div>
                        <div class="text-sm font-heading font-extrabold font-mono-num text-teal">
                            Rp {{ number_format(((float) ($sls['est_revenue'] ?? 0)) / 1000000, 1, ',', '.') }} Jt
                        </div>
                        <div class="text-[10px] font-mono-num text-slate-500">
                            {{ number_format((int) ($sls['room_nights'] ?? 0), 0, ',', '.') }} Room Nights
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50/80 border border-slate-200/80 p-3 space-y-1">
                        <div class="text-[10px] font-mono-num font-bold text-slate-500 uppercase">HR Attendance</div>
                        <div class="text-sm font-heading font-extrabold font-mono-num text-emerald-700">
                            {{ number_format((float) ($hr['attendance_pct'] ?? 0), 1) }}%
                        </div>
                        <div class="text-[10px] font-mono-num text-slate-500">
                            {{ $hr['present'] ?? 0 }}/{{ $hr['scheduled'] ?? 0 }} {{ $isEn ? 'Staff' : 'Staf' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
