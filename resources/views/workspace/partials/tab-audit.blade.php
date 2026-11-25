@php
    $isEn = ($locale ?? 'id') === 'en';
    $auditTotalCount = $auditLogs->count();
    $auditSecurityCount = $auditLogs->whereIn('module', ['Auth', 'RBAC'])->count();
    $auditOpsCount = $auditLogs->whereIn('module', ['Reports', 'POMEC'])->count();
    $auditAlertCount = $auditLogs->filter(function ($log) {
        $ev = strtolower((string) $log->event);
        return str_contains($ev, 'fail')
            || str_contains($ev, 'block')
            || str_contains($ev, 'delete')
            || str_contains($ev, 'revoke')
            || str_contains($ev, 'disable')
            || str_contains($ev, 'denied');
    })->count();
    $auditModules = ['Auth', 'RBAC', 'Reports', 'POMEC', 'Topology', 'Chatbot', 'Templates', 'Database'];
    $auditActors = $auditLogs->map(fn ($l) => $l->user?->name ?? 'System/Guest')->unique()->sort()->values();
@endphp

<div class="space-y-5">
    <!-- Audit Governance Header -->
    <section class="bg-white border border-slate-200/80 rounded-2xl px-5 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-navy text-white text-[10px] font-mono-num font-bold tracking-wider uppercase">
                    GOVERNANCE &amp; COMPLIANCE AUDIT TRAIL
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-slate-600 text-[11px] font-mono-num font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal"></span>
                    <span>WITA &bull; UTC+8</span>
                </span>
            </div>
            <h2 class="text-base sm:text-lg font-heading font-extrabold text-navy">
                {{ $isEn ? 'Security, Activity & Compliance Audit Center' : 'Pusat Audit Keamanan, Aktivitas & Kepatuhan Sistem' }}
            </h2>
            <p class="text-xs text-slate-500">
                {{ $isEn
                    ? 'Structured tracking for authentication events, RBAC changes, workflow reviews, POMEC commits, database backups, and AI guardrails.'
                    : 'Pemantauan terstruktur untuk autentikasi, perubahan RBAC, alur persetujuan laporan, unggah POMEC, backup database, dan guardrail AI.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <div class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 text-xs font-semibold">
                <button type="button" data-audit-subtab="system" onclick="switchAuditSubTab('system')"
                    class="audit-subtab-btn px-3 py-1.5 rounded-lg transition cursor-pointer bg-navy text-white shadow-2xs">
                    {{ $isEn ? 'System & Security' : 'Log Sistem & Keamanan' }}
                </button>
                <button type="button" data-audit-subtab="workflow" onclick="switchAuditSubTab('workflow')"
                    class="audit-subtab-btn px-3 py-1.5 rounded-lg transition cursor-pointer text-slate-600 hover:text-navy">
                    {{ $isEn ? 'Workflow Approval' : 'Alur Revisi Laporan' }}
                </button>
            </div>

            <button type="button" onclick="exportAuditLogsCsv()"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy text-xs font-semibold transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export CSV</span>
            </button>
        </div>
    </section>

    <!-- Sub-Panel 1: System & Security Audit Trail -->
    <div id="audit-subpanel-system" class="space-y-5">
        <!-- 4 Structured Audit KPIs -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
                <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Loaded Audit Events' : 'Total Log Audit' }}</div>
                <div class="flex items-baseline justify-between mt-1">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy" id="audit-kpi-total">{{ $auditTotalCount }}</div>
                    <span class="text-[10px] font-mono-num text-slate-400">Latest 250</span>
                </div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
                <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Auth & RBAC Events' : 'Keamanan & RBAC' }}</div>
                <div class="flex items-baseline justify-between mt-1">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-teal" id="audit-kpi-security">{{ $auditSecurityCount }}</div>
                    <span class="text-[10px] font-mono-num text-teal font-semibold">Auth &bull; RBAC</span>
                </div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
                <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Report & POMEC Activity' : 'Operasional & Utilitas' }}</div>
                <div class="flex items-baseline justify-between mt-1">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-emerald-700" id="audit-kpi-ops">{{ $auditOpsCount }}</div>
                    <span class="text-[10px] font-mono-num text-emerald-700 font-semibold">Reports &bull; POMEC</span>
                </div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
                <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Sensitive / Blocked Alerts' : 'Event Sensitif / Diblokir' }}</div>
                <div class="flex items-baseline justify-between mt-1">
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-amber-600" id="audit-kpi-alerts">{{ $auditAlertCount }}</div>
                    <span class="text-[10px] font-mono-num text-amber-700 font-semibold">Watchlist</span>
                </div>
            </div>
        </section>

        <!-- Audit Table Container -->
        <section class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <!-- Quick Module Pills -->
            <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5" id="audit-module-pills">
                    <button type="button" data-audit-module=""
                        class="audit-module-pill px-3 py-1 rounded-xl text-xs font-semibold border transition cursor-pointer bg-navy text-white border-navy">
                        {{ $isEn ? 'All Modules' : 'Semua Modul' }} ({{ $auditTotalCount }})
                    </button>
                    @foreach ($auditModules as $mod)
                        @php $modCnt = $auditLogs->where('module', $mod)->count(); @endphp
                        <button type="button" data-audit-module="{{ $mod }}"
                            class="audit-module-pill px-3 py-1 rounded-xl text-xs font-semibold border transition cursor-pointer bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100">
                            {{ $mod }} <span class="font-mono-num opacity-75">({{ $modCnt }})</span>
                        </button>
                    @endforeach
                </div>

                <span id="admin-audit-count" class="px-2.5 py-1 rounded-lg bg-slate-100 text-navy font-mono-num text-[11px] font-semibold shrink-0">
                    {{ $auditTotalCount }}
                </span>
            </div>

            <!-- Multi-Criteria Filter Toolbar -->
            <div class="px-5 py-3.5 bg-slate-50/50 border-b border-slate-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-2.5 items-center">
                <div class="relative lg:col-span-4">
                    <input id="admin-audit-search" type="text"
                        placeholder="{{ $isEn ? 'Search event, actor, description, IP...' : 'Cari event, user, deskripsi, atau IP...' }}"
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal transition">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                </div>

                <select id="admin-audit-module-filter" class="lg:col-span-2 px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Modules' : 'Semua Modul' }}</option>
                    @foreach ($auditModules as $mod)
                        <option value="{{ $mod }}">{{ $mod }}</option>
                    @endforeach
                </select>

                <select id="admin-audit-actor-filter" class="lg:col-span-2 px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Actors' : 'Semua Aktor' }}</option>
                    @foreach ($auditActors as $actorName)
                        <option value="{{ $actorName }}">{{ $actorName }}</option>
                    @endforeach
                </select>

                <select id="admin-audit-severity-filter" class="lg:col-span-2 px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Categories' : 'Semua Kategori' }}</option>
                    <option value="alert">{{ $isEn ? 'Alerts / Blocked / Deleted' : 'Sensitif / Diblokir / Hapus' }}</option>
                    <option value="security">{{ $isEn ? 'Auth & Access Control' : 'Autentikasi & RBAC' }}</option>
                    <option value="data">{{ $isEn ? 'Operational Changes' : 'Perubahan Data & Laporan' }}</option>
                </select>

                <div class="lg:col-span-2 flex items-center gap-1.5">
                    <input id="admin-audit-date-from" type="date" title="{{ $isEn ? 'From date' : 'Dari tanggal' }}"
                        class="w-full px-2.5 py-2 text-xs font-mono-num rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25">
                    <button type="button" id="admin-audit-reset-btn" onclick="resetAuditFilters()"
                        class="hidden shrink-0 px-2.5 py-2 rounded-xl text-xs font-semibold border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                        Reset
                    </button>
                </div>
            </div>

            <!-- Sortable Audit Table -->
            <div class="overflow-x-auto">
                <table id="admin-audit-table" class="w-full min-w-[960px] text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                            <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="created_at">{{ $isEn ? 'Timestamp (WITA)' : 'Waktu (WITA)' }}</th>
                            <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="module">{{ $isEn ? 'Module' : 'Modul' }}</th>
                            <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="event">Event</th>
                            <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="actor">{{ $isEn ? 'Actor & IP' : 'Pelaku & IP' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Activity Detail & Metadata' : 'Deskripsi & Metadata' }}</th>
                        </tr>
                    </thead>
                    <tbody id="admin-audit-list" class="divide-y divide-slate-100">
                        @forelse ($auditLogs->take(10) as $log)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-5 align-top whitespace-nowrap font-mono-num text-[11px] text-slate-500">
                                    {{ $log->created_at?->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' }}
                                </td>
                                <td class="py-3.5 px-5 align-top whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold bg-slate-100 text-navy border border-slate-200">{{ $log->module }}</span>
                                </td>
                                <td class="py-3.5 px-5 align-top whitespace-nowrap font-mono-num font-semibold text-navy">
                                    {{ $log->event }}
                                </td>
                                <td class="py-3.5 px-5 align-top whitespace-nowrap">
                                    <div class="font-semibold text-navy">{{ $log->user?->name ?? 'System/Guest' }}</div>
                                    <div class="font-mono-num text-[10px] text-slate-400">{{ $log->ip_address ?? '-' }}</div>
                                </td>
                                <td class="py-3.5 px-5 align-top text-slate-600 leading-snug">
                                    {{ $log->description }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    {{ $isEn ? 'No audit events recorded yet.' : 'Belum ada log audit yang tercatat.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="admin-audit-pagination"></div>
        </section>
    </div>

    <!-- Sub-Panel 2: Departmental Report Approval & Revision Workflow Audit -->
    <div id="audit-subpanel-workflow" class="hidden space-y-5">
        <section class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'Department Report Approval & Revision Audit' : 'Audit Alur Persetujuan & Revisi Laporan Departemen' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $isEn ? 'Chronological record of submissions, HOD reviews, returns for revision, and edits.' : 'Jejak kronologis pengajuan staf, pemeriksaan HOD, pengembalian revisi, dan perubahan data laporan.' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative w-52">
                        <input id="audit-workflow-search" type="text" placeholder="{{ $isEn ? 'Search code, actor, note...' : 'Cari kode, aktor, catatan...' }}"
                            class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50/70 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    </div>
                    <select id="audit-workflow-action-filter" class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-white">
                        <option value="">{{ $isEn ? 'All Actions' : 'Semua Aksi' }}</option>
                        <option value="created">Created</option>
                        <option value="submitted">Submitted</option>
                        <option value="in_review">In Review</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="returned">Returned</option>
                        <option value="updated">Updated</option>
                    </select>
                    <span id="audit-workflow-count" class="px-2.5 py-1 rounded-lg bg-slate-100 text-navy font-mono-num text-[11px] font-semibold">
                        {{ ($reportHistories ?? collect())->count() }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="audit-workflow-table" class="w-full min-w-[880px] text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                            <th class="py-3.5 px-5">{{ $isEn ? 'Timestamp (WITA)' : 'Waktu (WITA)' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Report Code' : 'Kode Laporan' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Department' : 'Departemen' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Action' : 'Aksi' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Actor' : 'Pelaku' }}</th>
                            <th class="py-3.5 px-5">{{ $isEn ? 'Audit Note' : 'Catatan Audit' }}</th>
                        </tr>
                    </thead>
                    <tbody id="audit-workflow-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div id="audit-workflow-pagination"></div>
        </section>
    </div>
</div>

<!-- Audit Payload Metadata Modal -->
<div id="modal-audit-detail" data-backdrop-close="true" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs items-center justify-center p-4">
    <div class="modal-panel bg-white border border-slate-200 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden text-xs">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-heading font-bold text-navy">{{ $isEn ? 'Audit Event Inspection' : 'Detail Inspeksi Event Audit' }}</h3>
                <p id="audit-modal-subtitle" class="text-[11px] font-mono-num text-slate-500 mt-0.5">-</p>
            </div>
            <button type="button" onclick="closeModal('modal-audit-detail')" class="p-1.5 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-100 cursor-pointer" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3 bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                <div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">{{ $isEn ? 'Module & Event' : 'Modul & Event' }}</div>
                    <div id="audit-modal-event" class="font-mono-num font-semibold text-navy mt-0.5">-</div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">{{ $isEn ? 'Actor & IP' : 'Pelaku & Alamat IP' }}</div>
                    <div id="audit-modal-actor" class="font-semibold text-navy mt-0.5">-</div>
                </div>
            </div>

            <div>
                <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">{{ $isEn ? 'Description' : 'Deskripsi Aktivitas' }}</div>
                <p id="audit-modal-desc" class="text-slate-700 leading-relaxed bg-slate-50 border border-slate-200/80 rounded-xl p-3"></p>
            </div>

            <div>
                <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">{{ $isEn ? 'Structured Metadata Payload' : 'Payload Metadata JSON' }}</div>
                <pre id="audit-modal-meta" class="font-mono-num text-[11px] text-slate-700 bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 overflow-x-auto max-h-60 whitespace-pre-wrap"></pre>
            </div>
        </div>
    </div>
</div>
