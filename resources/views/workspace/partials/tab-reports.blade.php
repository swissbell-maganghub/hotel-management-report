@php
    $isEn = $locale === 'en';
    $statusBadgeMap = [
        'reviewed' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'REVIEWED'],
        'in_review' => ['bg-cyan-50 text-teal border-cyan-200', 'REVIEW'],
        'submitted' => ['bg-amber-50 text-amber-700 border-amber-200', 'SUBMITTED'],
        'returned' => ['bg-rose-50 text-crimson-dark border-rose-200', 'RETURNED / REVISION'],
        'draft' => ['bg-slate-100 text-slate-600 border-slate-200', 'DRAFT'],
    ];
@endphp

<div class="space-y-4">
    @php
        $accessibleDeptIds = $user->accessibleDepartmentIds();
        $submenuDepts = ($accessibleDepts ?? $departments->reject(fn ($d) => $d->code === 'ANG')->filter(fn ($d) => $user->canAccessAllDepartments() || in_array($d->id, $accessibleDeptIds, true)))->values();
        $showDeptFilter = $user->canAccessAllDepartments() || $submenuDepts->count() > 1;
        $currentDeptCode = $activeReportDept ?? '';
        $currentDeptObj = $currentDeptCode ? $submenuDepts->firstWhere('code', $currentDeptCode) : null;
        $initialScopedReports = $currentDeptObj ? $reports->where('department_id', $currentDeptObj->id) : $reports;
        $initialReviewed = $initialScopedReports->where('status', 'reviewed')->count();
        $initialActionable = $initialScopedReports->whereIn('status', ['submitted', 'in_review', 'returned'])->count();
        $fieldCls = 'w-full h-9 px-3 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal';
    @endphp

    <!-- Organized Department Submenu & Context Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3.5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="reports-active-dept-badge" class="px-2 py-0.5 rounded-md bg-navy/8 text-navy font-mono-num text-[10px] font-bold uppercase tracking-wider">
                        {{ $currentDeptObj ? $currentDeptObj->code : ($showDeptFilter ? ($isEn ? 'ALL DEPTS' : 'SEMUA DEPT') : ($submenuDepts->first()?->code ?? 'DEPT')) }}
                    </span>
                    <h2 id="reports-active-dept-title" class="text-base font-heading font-extrabold text-navy truncate">
                        {{ $currentDeptObj ? $currentDeptObj->localizedName($locale) : ($showDeptFilter ? ($isEn ? 'All Departmental Reports' : 'Semua Laporan Departemen') : ($submenuDepts->first()?->localizedName($locale) ?? ($isEn ? 'Department Reports' : 'Laporan Departemen'))) }}
                    </h2>
                </div>
                <p id="reports-active-dept-subtitle" class="text-xs text-slate-500 truncate">
                    {{ $currentDeptObj
                        ? ($currentDeptObj->description ?: ($isEn ? 'Focused report workspace & approval queue for this department.' : 'Ruang laporan, analitik, dan histori khusus departemen ini.'))
                        : ($showDeptFilter
                            ? ($isEn ? 'Select a department submenu below to focus on specific operational reports and analytics.' : 'Pilih submenu departemen di bawah agar laporan, grafik, dan histori tersusun per divisi.')
                            : ($isEn ? 'Department report authoring, workflow review, and archive.' : 'Penyusunan, pemeriksaan, dan arsip laporan departemen Anda.')) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <div id="reports-active-dept-stats" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[11px] font-mono-num">
                    <span class="text-slate-600"><strong class="text-navy">{{ $initialScopedReports->count() }}</strong> {{ $isEn ? 'total' : 'laporan' }}</span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-emerald-700"><strong>{{ $initialReviewed }}</strong> reviewed</span>
                    @if ($initialActionable > 0)
                        <span class="text-slate-300">&bull;</span>
                        <span class="text-amber-700 font-semibold"><strong>{{ $initialActionable }}</strong> {{ $isEn ? 'pending' : 'perlu aksi' }}</span>
                    @endif
                </div>

                @if ($user->canCreateReports())
                    <button type="button" id="reports-dept-create-btn" onclick="createReportForActiveDept()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span id="reports-dept-create-label">{{ $currentDeptObj ? (($isEn ? 'New ' : 'Buat Laporan ').$currentDeptObj->code) : ($isEn ? 'Create Report' : 'Buat Laporan') }}</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($showDeptFilter)
            <div id="reports-dept-submenu" class="flex flex-wrap items-center gap-1.5 pt-3 border-t border-slate-100" role="tablist" aria-label="{{ $isEn ? 'Department Submenu' : 'Submenu Departemen' }}">
                <button type="button" data-dept-tab="" onclick="selectReportDepartment('')"
                    class="report-dept-tab-btn inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer {{ $currentDeptCode === '' ? 'bg-navy text-white border-navy shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-navy' }}">
                    <span>{{ $isEn ? 'All Departments' : 'Semua Departemen' }}</span>
                    <span data-dept-tab-count="" class="font-mono-num text-[10px] font-bold leading-none min-w-[1.15rem] text-center px-1 py-0.5 rounded-full {{ $currentDeptCode === '' ? 'bg-white/15 text-white' : 'bg-slate-200/70 text-slate-700' }}">{{ $reports->count() }}</span>
                </button>

                @foreach ($submenuDepts as $dept)
                    @php
                        $deptReports = $reports->where('department_id', $dept->id);
                        $deptCount = $deptReports->count();
                        $deptAlertCount = $deptReports->whereIn('status', ['submitted', 'in_review', 'returned'])->count();
                        $isDeptActive = $currentDeptCode === $dept->code;
                    @endphp
                    <button type="button" data-dept-tab="{{ $dept->code }}" onclick="selectReportDepartment('{{ $dept->code }}')"
                        class="report-dept-tab-btn inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer {{ $isDeptActive ? 'bg-navy text-white border-navy shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-navy' }}">
                        <span class="font-mono-num text-[10px] font-bold px-1.5 py-0.5 rounded {{ $isDeptActive ? 'bg-white/15 text-white' : 'bg-white border border-slate-200 text-navy' }}">{{ $dept->code }}</span>
                        <span>{{ $dept->localizedName($locale) }}</span>
                        <span data-dept-alert-dot="{{ $dept->code }}" class="{{ $deptAlertCount > 0 ? '' : 'hidden' }} w-1.5 h-1.5 rounded-full bg-amber-500" title="{{ $deptAlertCount }} {{ $isEn ? 'needs attention' : 'perlu tindakan' }}"></span>
                        <span data-dept-tab-count="{{ $dept->code }}" class="font-mono-num text-[10px] font-bold leading-none min-w-[1.15rem] text-center px-1 py-0.5 rounded-full {{ $isDeptActive ? 'bg-white/15 text-white' : 'bg-slate-200/70 text-slate-700' }}">{{ $deptCount }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Filter & Search Bar (Instant Client-Side Filtering) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">

        <!-- Row 1: Search + Dropdown Filters (equal height, no awkward wrapping) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 {{ $showDeptFilter ? 'lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]' : 'lg:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))]' }}">
            <div class="relative sm:col-span-2 lg:col-span-1">
                <input id="report-search" type="text"
                    placeholder="{{ $isEn ? 'Search code, title, creator, dept...' : 'Cari kode, judul, pembuat, dept...' }}"
                    class="{{ $fieldCls }} pl-9">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </div>

            @if ($showDeptFilter)
                <select id="report-dept-filter" class="{{ $fieldCls }}" title="{{ $isEn ? 'Department' : 'Departemen' }}">
                    <option value="">{{ $isEn ? 'All Accessible Depts' : 'Semua Departemen' }}</option>
                    @foreach ($submenuDepts as $dept)
                        <option value="{{ $dept->code }}">{{ $dept->localizedName($locale) }} ({{ $dept->code }})</option>
                    @endforeach
                </select>
            @endif

            <select id="report-period-filter" class="{{ $fieldCls }}" title="{{ $isEn ? 'Period type' : 'Jenis periode' }}">
                <option value="">{{ $isEn ? 'All Periods' : 'Semua Periode' }}</option>
                <option value="daily">{{ $isEn ? 'Daily' : 'Harian' }}</option>
                <option value="weekly">{{ $isEn ? 'Weekly' : 'Mingguan' }}</option>
                <option value="monthly">{{ $isEn ? 'Monthly' : 'Bulanan' }}</option>
            </select>

            <input id="report-date-filter" type="date" title="{{ $isEn ? 'Filter by report date' : 'Filter tanggal laporan' }}"
                class="{{ $fieldCls }} font-mono-num">
        </div>

        <!-- Row 2: Status Pills (left) + Count / Reset / SOP (right) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-3 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-1.5" id="report-status-pills">
                <span class="text-[11px] font-semibold text-slate-400 mr-1">Status:</span>
                @foreach (['all' => ($isEn ? 'All' : 'Semua'), 'draft' => 'Draft', 'submitted' => 'Submitted', 'in_review' => 'Review', 'returned' => 'Returned', 'reviewed' => 'Reviewed'] as $stKey => $stLabel)
                    <button type="button" data-status="{{ $stKey }}"
                        class="report-status-btn px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer {{ $stKey === 'all' ? 'bg-navy text-white border-navy' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        {{ $stLabel }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-[11px] text-slate-400">{{ $isEn ? 'Showing' : 'Tampil' }}</span>
                <span id="report-filter-count" class="px-2.5 py-1 rounded-lg bg-slate-100 text-navy font-mono-num text-[11px] font-semibold">
                    {{ $reports->count() }}/{{ $reports->count() }}
                </span>
                <button type="button" id="report-reset-btn" onclick="resetReportFilters()" class="hidden px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                    Reset
                </button>
                <button type="button" onclick="document.getElementById('reports-workflow-sop').classList.toggle('hidden')"
                    class="px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-slate-200 bg-white text-slate-500 hover:text-navy hover:bg-slate-50 transition cursor-pointer"
                    title="{{ $isEn ? 'View Reporting Workflow SOP' : 'Lihat Alur Workflow' }}">
                    SOP
                </button>
            </div>
        </div>

        <!-- Collapsible Minimalist SOP Reference Table (Hidden by Default for Clean Look) -->
        <div id="reports-workflow-sop" class="hidden pt-3 border-t border-slate-100">
            <div class="overflow-x-auto border border-slate-200/80 rounded-xl">
                <table class="w-full min-w-[640px] text-left border-collapse text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-2 px-3.5">{{ $isEn ? 'Stage' : 'Tahap' }}</th>
                            <th class="py-2 px-3.5">{{ $isEn ? 'Actor' : 'Pelaksana' }}</th>
                            <th class="py-2 px-3.5">{{ $isEn ? 'Activity' : 'Aktivitas' }}</th>
                            <th class="py-2 px-3.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">1</td><td class="py-2 px-3.5 font-medium">Admin / Staff</td><td class="py-2 px-3.5">{{ $isEn ? 'Create or upload report' : 'Membuat atau mengunggah laporan' }}</td><td class="py-2 px-3.5 font-mono-num">Draft</td></tr>
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">2</td><td class="py-2 px-3.5 font-medium">Admin / Staff</td><td class="py-2 px-3.5">{{ $isEn ? 'Submit report for review' : 'Mengirim laporan untuk diperiksa' }}</td><td class="py-2 px-3.5 font-mono-num">Submitted</td></tr>
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">3</td><td class="py-2 px-3.5 font-medium">HOD</td><td class="py-2 px-3.5">{{ $isEn ? 'Verify completeness & accuracy' : 'Memeriksa kelengkapan & kesesuaian' }}</td><td class="py-2 px-3.5 font-mono-num text-teal font-semibold">Review</td></tr>
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">4A</td><td class="py-2 px-3.5 font-medium">HOD</td><td class="py-2 px-3.5">{{ $isEn ? 'Return report for fixes' : 'Mengembalikan laporan untuk perbaikan' }}</td><td class="py-2 px-3.5 font-mono-num text-crimson-dark font-semibold">Returned / Revision</td></tr>
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">4B</td><td class="py-2 px-3.5 font-medium">HOD</td><td class="py-2 px-3.5">{{ $isEn ? 'Complete verification' : 'Menyelesaikan pemeriksaan' }}</td><td class="py-2 px-3.5 font-mono-num text-emerald-700 font-semibold">Reviewed</td></tr>
                        <tr><td class="py-2 px-3.5 font-mono-num font-bold text-navy">5</td><td class="py-2 px-3.5 font-medium">Management</td><td class="py-2 px-3.5">{{ $isEn ? 'Monitor results per authority' : 'Memantau laporan sesuai kewenangan' }}</td><td class="py-2 px-3.5 font-mono-num text-navy font-semibold">Available for monitoring</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reports Analytics & Insights (follows active filters, click charts to filter) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-heading font-bold text-navy">
                    {{ $isEn ? 'Report Analytics' : 'Analitik Laporan' }}
                </h3>
                <p class="text-[11px] text-slate-500">
                    {{ $isEn ? 'Charts follow the filters above • click a chart segment or bar to filter the table.' : 'Grafik mengikuti filter di atas • klik bagian grafik untuk memfilter tabel.' }}
                </p>
            </div>
            <button type="button" id="reports-insights-toggle"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-200 bg-white text-slate-600 hover:text-navy hover:bg-slate-50 transition cursor-pointer">
                {{ $isEn ? 'Hide Charts' : 'Sembunyikan Grafik' }}
            </button>
        </div>

        <div id="reports-insights-body" class="p-4 sm:p-5 space-y-4">
            <!-- Insight Mini Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 space-y-1">
                    <div class="text-[11px] font-medium text-slate-500">{{ $isEn ? 'Reports in View' : 'Laporan Terfilter' }}</div>
                    <div class="text-xl font-heading font-extrabold font-mono-num text-navy" id="ri-total">0</div>
                    <div class="text-[10px] font-mono-num text-slate-400" id="ri-total-sub">-</div>
                </div>
                <div class="rounded-xl border border-emerald-200/70 bg-emerald-50/50 p-3.5 space-y-1">
                    <div class="text-[11px] font-medium text-emerald-800">{{ $isEn ? 'Approval Rate' : 'Tingkat Persetujuan' }}</div>
                    <div class="text-xl font-heading font-extrabold font-mono-num text-emerald-700" id="ri-approval">0%</div>
                    <div class="w-full h-1.5 rounded-full bg-emerald-100 overflow-hidden">
                        <div id="ri-approval-bar" class="h-full bg-emerald-600 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>
                <div class="rounded-xl border border-amber-200/70 bg-amber-50/50 p-3.5 space-y-1">
                    <div class="text-[11px] font-medium text-amber-800">{{ $isEn ? 'Needs My Action' : 'Perlu Tindakan Saya' }}</div>
                    <div class="text-xl font-heading font-extrabold font-mono-num text-amber-700" id="ri-action">0</div>
                    <div class="text-[10px] font-mono-num text-amber-700/80" id="ri-action-sub">-</div>
                </div>
                <div class="rounded-xl border border-cyan-200/70 bg-cyan-50/50 p-3.5 space-y-1">
                    <div class="text-[11px] font-medium text-teal">{{ $isEn ? 'Avg. HOD Review Time' : 'Rata-rata Waktu Review HOD' }}</div>
                    <div class="text-xl font-heading font-extrabold font-mono-num text-teal" id="ri-avg-review">-</div>
                    <div class="text-[10px] font-mono-num text-slate-500" id="ri-avg-review-sub">{{ $isEn ? 'submitted → reviewed' : 'dikirim → disetujui' }}</div>
                </div>
            </div>

            <!-- Charts Row 1: Status Doughnut + Daily Trend -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="rounded-xl border border-slate-200/80 p-4">
                    <div class="text-xs font-bold text-navy mb-2">{{ $isEn ? 'Status Composition' : 'Komposisi Status' }}</div>
                    <div class="relative h-48">
                        <canvas id="reports-chart-status"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <div class="text-2xl font-heading font-extrabold font-mono-num text-navy" id="reports-chart-status-center">0</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400">{{ $isEn ? 'reports' : 'laporan' }}</div>
                        </div>
                    </div>
                    <div id="reports-chart-status-legend" class="mt-3 grid grid-cols-1 gap-1 text-[11px]"></div>
                </div>
                <div class="rounded-xl border border-slate-200/80 p-4 lg:col-span-2">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'Report Trend by Period Date (14 days)' : 'Tren Laporan per Tanggal Periode (14 hari)' }}</div>
                        <span class="text-[10px] font-mono-num text-slate-400" id="reports-chart-trend-range">-</span>
                    </div>
                    <div class="relative h-64">
                        <canvas id="reports-chart-trend"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: Department Breakdown + Action Queue -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="rounded-xl border border-slate-200/80 p-4 lg:col-span-2">
                    <div class="text-xs font-bold text-navy mb-2">{{ $isEn ? 'Status per Department' : 'Status per Departemen' }}</div>
                    <div class="relative h-64">
                        <canvas id="reports-chart-dept"></canvas>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200/80 p-4 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'My Action Queue' : 'Antrian Tindakan Saya' }}</div>
                        <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-mono-num font-semibold" id="reports-action-count">0</span>
                    </div>
                    <ul id="reports-action-list" class="flex-1 divide-y divide-slate-100 text-xs overflow-y-auto max-h-64"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table Card -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left border-collapse" id="reports-table">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="title">{{ $isEn ? 'Report & Code' : 'Laporan & Kode' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="department">{{ $isEn ? 'Department & Template' : 'Departemen & Template' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="period_date">{{ $isEn ? 'Period & Time (WITA)' : 'Periode & Waktu (WITA)' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="creator">{{ $isEn ? 'Author' : 'Penyusun' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="status">Status</th>
                        <th class="py-3.5 px-5 text-right">{{ $isEn ? 'Actions' : 'Aksi' }}</th>
                    </tr>
                </thead>
                <tbody id="reports-tbody" class="divide-y divide-slate-100 text-sm">
                    @foreach ($reports as $rpt)
                        @php
                            $deptName = $rpt->department?->localizedName($locale) ?? '-';
                            $tplName = $rpt->template?->localizedName($locale) ?? '-';
                            $rowCount = is_array($rpt->rows_data) ? count($rpt->rows_data) : 0;
                            $rptTs = ($rpt->reviewed_at ?? $rpt->submitted_at ?? $rpt->updated_at)?->copy()->timezone('Asia/Makassar');
                            [$badgeCls, $badgeLabel] = $statusBadgeMap[$rpt->status] ?? ['bg-slate-100 text-slate-600 border-slate-200', strtoupper($rpt->status)];
                            $canReview = $rpt->isReviewableBy($user);
                            $canEdit = $rpt->isEditableBy($user);
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-4 px-5 align-middle min-w-[240px]">
                                <div class="font-semibold text-navy leading-snug">{{ $rpt->title }}</div>
                                <div class="text-xs font-mono-num text-slate-500 mt-0.5">{{ $rpt->report_code }} &bull; {{ $rowCount }} {{ $isEn ? 'rows' : 'baris' }}</div>
                            </td>
                            <td class="py-4 px-5 align-middle min-w-[200px]">
                                <div class="text-xs font-semibold text-slate-700">{{ $deptName }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $tplName }}</div>
                            </td>
                            <td class="py-4 px-5 align-middle whitespace-nowrap text-xs font-mono-num text-slate-600">
                                <div>{{ $rpt->period_date->copy()->timezone('Asia/Makassar')->format('d M Y') }} <span class="text-[10px] uppercase text-slate-400">({{ $rpt->period_type }})</span></div>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $rptTs ? $rptTs->format('d M Y, H:i').' WITA' : '-' }}</div>
                            </td>
                            <td class="py-4 px-5 align-middle whitespace-nowrap text-xs text-slate-600">{{ $rpt->creator?->name ?? '-' }}</td>
                            <td class="py-4 px-5 align-middle whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeCls }}">
                                    {{ $badgeLabel }}
                                </span>
                                @if ($rpt->status === 'reviewed')
                                    <div class="text-[10px] text-emerald-700 font-medium mt-0.5">Available for monitoring</div>
                                @endif
                            </td>
                            <td class="py-4 px-5 align-middle text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button" onclick="openReportDetail({{ $rpt->id }})" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-navy hover:text-white text-navy transition cursor-pointer">
                                        {{ $canReview ? ($isEn ? 'Review' : 'Periksa') : ($isEn ? 'View' : 'Detail') }}
                                    </button>
                                    @if ($canEdit)
                                        <button type="button" onclick="openReportEditor({{ $rpt->id }})" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-teal/10 text-teal hover:bg-teal hover:text-white transition cursor-pointer">
                                            {{ $isEn ? 'Edit' : 'Ubah' }}
                                        </button>
                                    @endif
                                    @if ($canEdit && $rpt->status === 'draft')
                                        <button type="button" onclick="openDeleteDraftModal({{ $rpt->id }}, '{{ e($rpt->report_code) }}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                                            {{ $isEn ? 'Del' : 'Hapus' }}
                                        </button>
                                    @endif
                                    <a href="/reports/{{ $rpt->id }}/export/excel" class="inline-block px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50">XLSX</a>
                                    <a href="/reports/{{ $rpt->id }}/export/pdf" class="inline-block px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 text-crimson-dark hover:bg-rose-50">PDF</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Two-State Empty Screen -->
        <div id="reports-empty-onboarding" class="{{ $reports->isEmpty() ? '' : 'hidden' }} py-12 px-6 text-center space-y-3">
            <div class="text-base font-heading font-bold text-navy">
                {{ $isEn ? 'No Departmental Reports Yet' : 'Belum Ada Laporan Departemen' }}
            </div>
            <p class="text-sm text-slate-500 max-w-md mx-auto">
                {{ $isEn ? 'Start by creating a report using your department-specific table template.' : 'Mulai dengan membuat laporan menggunakan template tabel khusus departemen Anda.' }}
            </p>
            @if ($user->canCreateReports())
                <button type="button" onclick="openReportEditor()" class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-semibold hover:bg-navy-light transition cursor-pointer">
                    + {{ $isEn ? 'Create First Report' : 'Buat Laporan Pertama' }}
                </button>
            @endif
        </div>

        <div id="reports-empty-filter" class="hidden py-10 px-6 text-center space-y-2">
            <div class="text-sm font-semibold text-navy">
                {{ $isEn ? 'No reports match your current filter' : 'Tidak ada laporan yang sesuai filter pencarian' }}
            </div>
            <button type="button" onclick="resetReportFilters()" class="text-xs font-semibold text-teal hover:underline cursor-pointer">
                {{ $isEn ? 'Reset Filters' : 'Reset Filter' }}
            </button>
        </div>
        <div id="reports-pagination"></div>
    </div>

    <!-- Consolidated Report Workflow History Table (WITA) -->
    <div id="reports-history-card" class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Report Workflow & Revision History' : 'Histori Alur, Revisi & Verifikasi Laporan' }}
                </h3>
                <p class="text-xs text-slate-500">
                    {{ $isEn ? 'Complete chronological audit trail of drafts, submissions, returns, and HOD approvals (Makassar WITA).' : 'Catatan kronologis pembuatan, pengiriman, pengembalian revisi, dan persetujuan laporan (Waktu Makassar • WITA).' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="history-action-filter" class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Actions' : 'Semua Aktivitas' }}</option>
                    <option value="created">Created / Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="in_review">Review</option>
                    <option value="returned">Returned / Revision</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="updated">Updated</option>
                </select>
                <span class="font-mono-num text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-navy" id="report-history-count">
                    {{ ($reportHistories ?? collect())->count() }} {{ $isEn ? 'events' : 'aktivitas' }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="reports-history-table" class="w-full min-w-[860px] text-left border-collapse text-xs">
                <thead class="bg-slate-50/90 border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="created_at">{{ $isEn ? 'Timestamp (WITA)' : 'Waktu (WITA)' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="report">{{ $isEn ? 'Report & Dept' : 'Laporan & Dept.' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="action">{{ $isEn ? 'Transition' : 'Aksi & Status' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="user">{{ $isEn ? 'Actor' : 'Pelaku' }}</th>
                        <th class="py-3.5 px-5">{{ $isEn ? 'Notes' : 'Catatan' }}</th>
                    </tr>
                </thead>
                <tbody id="report-histories-tbody" class="divide-y divide-slate-100">
                    @forelse ($reportHistories ?? [] as $hist)
                        @php
                            $actionCls = match($hist->action) {
                                'reviewed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'returned' => 'bg-rose-50 text-crimson-dark border-rose-200',
                                'submitted' => 'bg-amber-50 text-amber-800 border-amber-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 font-mono-num text-slate-600 whitespace-nowrap">
                                {{ $hist->created_at?->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' }}
                            </td>
                            <td class="py-3.5 px-5 min-w-[220px]">
                                @if ($hist->report)
                                    <button type="button" onclick="openReportDetail({{ $hist->report->id }})" class="font-mono-num font-bold text-navy hover:text-teal cursor-pointer">
                                        {{ $hist->report->report_code }}
                                    </button>
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono-num text-[10px] font-semibold">{{ $hist->report->department?->code }}</span>
                                    <div class="text-slate-600 truncate max-w-[260px] mt-0.5">{{ $hist->report->title }}</div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $actionCls }}">{{ strtoupper($hist->action) }}</span>
                                <span class="font-mono-num text-[11px] text-slate-500 ml-1.5">
                                    {{ $hist->from_status ? strtoupper($hist->from_status).' → ' : '' }}{{ strtoupper($hist->to_status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-slate-700 font-medium whitespace-nowrap">{{ $hist->user?->name ?? 'System' }}</td>
                            <td class="py-3.5 px-5 text-slate-600 min-w-[200px]">{{ $hist->note ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">{{ $isEn ? 'No workflow history recorded yet.' : 'Belum ada riwayat aktivitas laporan.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="report-histories-pagination"></div>
    </div>
</div>
