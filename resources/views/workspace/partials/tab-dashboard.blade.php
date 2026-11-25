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
@endphp

@if ($user->isItAdmin())
    @php
        $topo = $topologySummary ?? ['total' => 0, 'healthy' => 0, 'warning' => 0, 'degraded' => 0, 'last_checked_wita' => null];
        $db = $databaseStats ?? ['connection' => 'SQLITE', 'database' => 'database.sqlite', 'size_human' => '-', 'total_tables' => 0, 'total_rows' => 0];
        $latestBackup = ($backupFiles ?? [])[0] ?? null;
        $totalUsersCount = $managedUsers->count();
        $activeUsersCount = $managedUsers->where('is_active', true)->count();
        $activeSessionsCount = $managedUsers->sum('active_sessions_count');
        $delegatedUsersCount = $managedUsers->filter(fn ($u) => $u->is_read_only || ($u->extraPermissions && $u->extraPermissions->isNotEmpty()))->count();
        $activeTemplatesCount = ($allTemplates ?? collect())->where('is_active', true)->count();
    @endphp

    <div class="space-y-5">
        <!-- IT Administrator Dedicated System Control Header -->
        <section class="bg-white border border-slate-200/80 rounded-2xl px-5 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-crimson text-white text-[10px] font-mono-num font-bold tracking-wider uppercase shadow-2xs">
                        IT Administrator • System Control Center
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-mono-num font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span id="it-wita-live-clock" data-wita-clock>{{ $witaNow->format('D, d M Y • H:i:s') }} WITA</span>
                    </span>
                </div>
                <h2 class="text-base sm:text-lg font-heading font-extrabold text-navy">
                    {{ $isEn ? 'System Governance, Infrastructure & Security Overview' : 'Kendali Sistem, Infrastruktur & Keamanan Operasional' }}
                </h2>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button" onclick="switchWorkspaceTab('executive')" class="px-3 py-1.5 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                    Executive Dashboard
                </button>
                <button type="button" onclick="runTopologyHealthCheck()" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-teal text-xs font-semibold transition cursor-pointer">
                    {{ $isEn ? 'Run Health Check' : 'Cek Kesehatan Sistem' }}
                </button>
                <button type="button" onclick="switchWorkspaceTab('admin')" class="px-3 py-1.5 rounded-xl border border-amber-200 bg-amber-50/70 hover:bg-amber-100 text-amber-800 text-xs font-semibold transition cursor-pointer">
                    User Management
                </button>
                <button type="button" onclick="switchWorkspaceTab('audit')" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy text-xs font-semibold transition cursor-pointer">
                    Audit Trail
                </button>
            </div>
        </section>

        <!-- 4 System & Governance Health Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono-num font-bold tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">SYSTEM TOPOLOGY</span>
                        <button type="button" onclick="switchWorkspaceTab('topology')" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Detail &rarr;</button>
                    </div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ $topo['healthy'] ?? 0 }}/{{ $topo['total'] ?? 0 }} <span class="text-xs font-semibold text-emerald-700">Healthy</span>
                    </div>
                    <div class="text-[11px] font-mono-num text-slate-500">
                        Warning: {{ $topo['warning'] ?? 0 }} &bull; Degraded: {{ $topo['degraded'] ?? 0 }}
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                    {{ $topo['last_checked_wita'] ?? '-' }}
                </div>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono-num font-bold tracking-wider text-navy bg-navy/8 px-2 py-0.5 rounded-md">USERS &amp; SESSIONS</span>
                        <button type="button" onclick="switchWorkspaceTab('admin')" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">{{ $isEn ? 'Manage' : 'Kelola' }} &rarr;</button>
                    </div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ $activeUsersCount }}/{{ $totalUsersCount }} <span class="text-xs font-normal text-slate-500">{{ $isEn ? 'Active' : 'Aktif' }}</span>
                    </div>
                    <div class="text-[11px] font-mono-num text-slate-500">
                        <strong class="text-teal">{{ $activeSessionsCount }}</strong> {{ $isEn ? 'active sessions' : 'sesi login aktif' }} &bull; {{ $delegatedUsersCount }} {{ $isEn ? 'custom RBAC' : 'delegasi' }}
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400">
                    {{ $activeTemplatesCount }}/{{ ($allTemplates ?? collect())->count() }} {{ $isEn ? 'templates active' : 'template aktif' }}
                </div>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono-num font-bold tracking-wider text-teal bg-teal/10 px-2 py-0.5 rounded-md">DATABASE ENGINE</span>
                        <span class="text-[10px] font-mono-num font-semibold text-navy">{{ $db['connection'] }}</span>
                    </div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">{{ $db['size_human'] }}</div>
                    <div class="text-[11px] font-mono-num text-slate-500">
                        {{ $db['total_tables'] }} {{ $isEn ? 'tables' : 'tabel' }} &bull; {{ number_format($db['total_rows'], 0, ',', '.') }} {{ $isEn ? 'rows' : 'baris' }}
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                    {{ $db['database'] }}
                </div>
            </div>

            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono-num font-bold tracking-wider text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md">BACKUP SNAPSHOTS</span>
                        <button type="button" onclick="switchWorkspaceTab('admin')" class="text-[11px] font-semibold text-teal hover:underline cursor-pointer">Backup &rarr;</button>
                    </div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ count($backupFiles ?? []) }} <span class="text-xs font-normal text-slate-500">Snapshot</span>
                    </div>
                    <div class="text-[11px] font-mono-num text-slate-500 truncate">
                        {{ $latestBackup ? $latestBackup['filename'] : ($isEn ? 'No snapshot yet' : 'Belum ada snapshot') }}
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100 text-[10px] font-mono-num text-slate-400 truncate">
                    {{ $latestBackup ? $latestBackup['created_wita'] : 'storage/app/private/backups' }}
                </div>
            </div>
        </section>

        <!-- IT Control Split: Infrastructure Nodes + Security Audit Trail -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Infrastructure Topology Table -->
            <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-heading font-bold text-navy">
                            {{ $isEn ? 'System Infrastructure & Network Nodes' : 'Status Node Infrastruktur & Topologi Sistem' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $isEn ? 'Live endpoint health and last check timestamps (WITA)' : 'Status teknis komponen server, database, storage & backup (WITA)' }}
                        </p>
                    </div>
                    <button type="button" onclick="switchWorkspaceTab('topology')" class="text-xs font-semibold text-teal hover:underline shrink-0 cursor-pointer">
                        {{ $isEn ? 'Manage Topology' : 'Kelola Topologi' }} &rarr;
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left border-collapse text-xs">
                        <thead class="bg-slate-50/80 text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                            <tr>
                                <th class="py-3 px-5">{{ $isEn ? 'Component' : 'Komponen' }}</th>
                                <th class="py-3 px-5">{{ $isEn ? 'Technical Detail' : 'Detail Teknis / Host' }}</th>
                                <th class="py-3 px-5">{{ $isEn ? 'Checked (WITA)' : 'Cek Terakhir (WITA)' }}</th>
                                <th class="py-3 px-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($topologyComponents as $comp)
                                @php
                                    $tBadge = match($comp->status) {
                                        'healthy' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        default => 'bg-rose-50 text-crimson-dark border-rose-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-5 whitespace-nowrap">
                                        <div class="font-semibold text-navy">{{ $isEn ? $comp->name_en : $comp->name }}</div>
                                        <div class="text-[10px] font-mono-num text-slate-400 uppercase">{{ $comp->zone }}</div>
                                    </td>
                                    <td class="py-3.5 px-5 min-w-[220px]">
                                        <div class="text-slate-700 leading-snug">{{ $comp->it_technical_detail }}</div>
                                        @if ($comp->host_or_endpoint)
                                            <div class="text-[10px] font-mono-num text-teal mt-0.5">{{ $comp->host_or_endpoint }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-5 font-mono-num text-[11px] text-slate-500 whitespace-nowrap">
                                        {{ $comp->last_checked_at ? $comp->last_checked_at->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' : '-' }}
                                    </td>
                                    <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold border {{ $tBadge }}">{{ strtoupper($comp->status) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Security Audit Trail Snapshot -->
            <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-heading font-bold text-navy">
                            {{ $isEn ? 'Latest Security & System Audit Trail' : 'Audit Trail Keamanan & Aktivitas Terbaru' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $isEn ? 'Recent Auth, RBAC, Backup & Report events' : 'Aktivitas Auth, RBAC, Backup, dan Laporan (WITA)' }}
                        </p>
                    </div>
                    <button type="button" onclick="switchWorkspaceTab('audit')" class="text-xs font-semibold text-teal hover:underline shrink-0 cursor-pointer">
                        {{ $isEn ? 'All Logs' : 'Semua Log' }} &rarr;
                    </button>
                </div>

                <div class="divide-y divide-slate-100 flex-1 overflow-y-auto max-h-[380px]">
                    @forelse ($auditLogs->take(8) as $log)
                        <div class="p-4 text-xs space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono-num font-semibold text-navy">{{ $log->event }}</span>
                                <span class="font-mono-num text-[10px] text-slate-400 shrink-0">{{ $log->created_at?->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' }}</span>
                            </div>
                            <p class="text-slate-600 leading-snug">{{ $log->description }}</p>
                            <div class="text-[11px] text-slate-400">
                                {{ $log->user?->name ?? 'System' }} &bull; {{ $log->module }} &bull; {{ $log->ip_address ?? '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-slate-400">-</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@elseif ($user->isGm())
    @include('workspace.partials.executive-view')
@else
    @php
        $dailyCount = $reports->where('period_type', 'daily')->count();
        $weeklyCount = $reports->where('period_type', 'weekly')->count();
        $monthlyCount = $reports->where('period_type', 'monthly')->count();
        $latestExportable = $reports->first();
    @endphp
    <!-- Operational Dashboard (HOD & Staff) -->
    <div class="space-y-5">
        <!-- Quick Workspace Bar: Period Summary & Shortcuts (Input, Review, History, Export) -->
        <section class="bg-white border border-slate-200/80 rounded-2xl px-5 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full {{ $user->isHod() ? 'bg-navy text-white' : 'bg-teal text-white' }} text-[10px] font-mono-num font-bold tracking-wider uppercase">
                        {{ $user->roleLabel($locale) }}
                    </span>
                    <span class="text-xs font-medium text-slate-500">{{ $user->scopeLabel($locale) }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                    <span class="text-slate-400 mr-0.5">{{ $isEn ? 'By period:' : 'Ringkasan periode:' }}</span>
                    <button type="button" onclick="jumpToReportsPreset({ period: 'daily' })" class="px-2.5 py-0.5 rounded-lg bg-slate-50 border border-slate-200/80 hover:border-teal text-slate-700 font-mono-num font-semibold transition cursor-pointer">
                        {{ $isEn ? 'Daily' : 'Harian' }}: {{ $dailyCount }}
                    </button>
                    <button type="button" onclick="jumpToReportsPreset({ period: 'weekly' })" class="px-2.5 py-0.5 rounded-lg bg-slate-50 border border-slate-200/80 hover:border-teal text-slate-700 font-mono-num font-semibold transition cursor-pointer">
                        {{ $isEn ? 'Weekly' : 'Mingguan' }}: {{ $weeklyCount }}
                    </button>
                    <button type="button" onclick="jumpToReportsPreset({ period: 'monthly' })" class="px-2.5 py-0.5 rounded-lg bg-slate-50 border border-slate-200/80 hover:border-teal text-slate-700 font-mono-num font-semibold transition cursor-pointer">
                        {{ $isEn ? 'Monthly' : 'Bulanan' }}: {{ $monthlyCount }}
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if ($user->canCreateReports())
                    <button type="button" onclick="openReportEditor()" class="px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                        + {{ $isEn ? 'Create Report' : 'Input Laporan' }}
                    </button>
                @endif
                @if ($user->canReviewReports())
                    <button type="button" onclick="jumpToReportsPreset({ status: 'submitted' })" class="px-3 py-2 rounded-xl border border-amber-200 bg-amber-50/80 hover:bg-amber-100 text-amber-800 text-xs font-semibold transition cursor-pointer">
                        {{ $isEn ? 'Review Queue' : 'Antrian Review' }} ({{ $pendingReviewCount }})
                    </button>
                @endif
                <button type="button" onclick="jumpToReportsPreset({ scrollToHistory: true })" class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy text-xs font-semibold transition cursor-pointer">
                    {{ $isEn ? 'History' : 'Histori' }}
                </button>
                @if ($latestExportable)
                    <a href="/reports/{{ $latestExportable->id }}/export/pdf" class="px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-rose-50 text-crimson-dark text-xs font-semibold transition">
                        {{ $isEn ? 'Export PDF' : 'Ekspor PDF' }}
                    </a>
                @endif
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Recent Reports Snapshot -->
        <div class="lg:col-span-8 bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'Recent Departmental Reports' : 'Laporan Departemen Terbaru' }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ $isEn ? 'Department operational reports • Timezone: Makassar (WITA)' : 'Laporan operasional departemen • Zona Waktu Makassar (WITA)' }}
                    </p>
                </div>
                <button type="button" onclick="switchWorkspaceTab('reports')" class="text-xs font-semibold text-teal hover:text-navy transition cursor-pointer">
                    {{ $isEn ? 'View All' : 'Lihat Semua' }} &rarr;
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left border-collapse text-xs sm:text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Report & Template' : 'Laporan & Template' }}</th>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Dept' : 'Dept.' }}</th>
                            <th class="py-3.5 px-5 font-semibold">{{ $isEn ? 'Period & Time (WITA)' : 'Periode & Waktu (WITA)' }}</th>
                            <th class="py-3.5 px-5 font-semibold">Status</th>
                            <th class="py-3.5 px-5 font-semibold text-right">{{ $isEn ? 'Action' : 'Aksi' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($reports->take(6) as $rpt)
                            @php
                                [$cls, $lbl] = $statusBadgeMap[$rpt->status] ?? ['bg-slate-100 text-slate-700 border-slate-200', $rpt->status];
                                $rptTs = ($rpt->reviewed_at ?? $rpt->submitted_at ?? $rpt->updated_at)?->copy()->timezone('Asia/Makassar');
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-5">
                                    <div class="font-semibold text-navy">{{ $rpt->title }}</div>
                                    <div class="text-xs font-mono-num text-slate-500 mt-0.5">{{ $rpt->report_code }} &bull; {{ $rpt->template?->code }}</div>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono-num text-xs font-semibold">
                                        {{ $rpt->department?->code }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap text-xs font-mono-num text-slate-600">
                                    <div>{{ $rpt->period_date->format('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $rptTs ? $rptTs->format('H:i:s').' WITA' : '-' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $cls }}">
                                        {{ $lbl }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    <button type="button" onclick="openReportDetail({{ $rpt->id }})" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-navy hover:text-white text-navy transition cursor-pointer">
                                        {{ $user->canReviewReports() && in_array($rpt->status, ['submitted', 'in_review'], true) ? ($isEn ? 'Review' : 'Periksa') : ($isEn ? 'Detail' : 'Detail') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400 text-xs">
                                    {{ $isEn ? 'No departmental reports recorded yet.' : 'Belum ada laporan departemen tercatat.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Templates & Operational Snapshot -->
        <div class="lg:col-span-4 space-y-5">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'Department Table Templates' : 'Template Tabel Departemen' }}
                    </h3>
                    <span class="font-mono-num text-xs font-semibold text-teal">{{ $templates->count() }}</span>
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @foreach ($templates as $tpl)
                        @if ($user->canCreateReports())
                            <button type="button" onclick="openReportEditor(null, {{ $tpl->id }})" class="w-full text-left p-3 rounded-xl border border-slate-100 bg-slate-50/70 hover:bg-white hover:border-teal/40 transition cursor-pointer group">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-navy group-hover:text-teal transition">{{ $tpl->localizedName($locale) }}</span>
                                    <span class="text-[10px] font-mono-num px-1.5 py-0.5 rounded bg-navy/10 text-navy font-semibold">{{ $tpl->department?->code }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $tpl->localizedDescription($locale) }}</p>
                            </button>
                        @else
                            <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/70">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-navy">{{ $tpl->localizedName($locale) }}</span>
                                    <span class="text-[10px] font-mono-num px-1.5 py-0.5 rounded bg-navy/10 text-navy font-semibold">{{ $tpl->department?->code }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $tpl->localizedDescription($locale) }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($canPomec)
                <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-heading font-bold text-navy">
                            {{ $isEn ? 'POMEC Utility Summary' : 'Ringkasan Utilitas POMEC' }}
                        </h3>
                        <button type="button" onclick="switchWorkspaceTab('pomec')" class="text-xs font-semibold text-teal hover:underline cursor-pointer">
                            {{ $isEn ? 'Open' : 'Buka' }} &rarr;
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200/70">
                            <div class="text-slate-500 text-[11px]">LWBP</div>
                            <div class="font-heading font-bold font-mono-num text-navy text-sm mt-0.5">{{ number_format($electricityLwbp, 0, ',', '.') }} kWh</div>
                        </div>
                        <div class="p-3 rounded-xl bg-rose-50/70 border border-rose-200/70">
                            <div class="text-slate-500 text-[11px]">WBP</div>
                            <div class="font-heading font-bold font-mono-num text-crimson-dark text-sm mt-0.5">{{ number_format($electricityWbp, 0, ',', '.') }} kWh</div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 font-mono-num">
                        {{ $isEn ? 'Committed batches:' : 'Batch terimpor:' }} {{ $pomecImports->count() }} &bull; SHA-256 Protected
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>
@endif
