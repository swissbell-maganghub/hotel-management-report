@php $isEn = $locale === 'en'; @endphp

<div class="space-y-5">
    <!-- Conceptual Network & Security Flow -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3.5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-heading font-bold text-navy">
                        {{ $isEn ? 'System & Network Topology Overview' : 'Gambaran Topologi Sistem & Jaringan' }}
                    </h2>
                    @unless ($user->canEditTopology())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-slate-600 text-[10px] font-mono-num font-bold tracking-wider uppercase">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Read-Only
                        </span>
                    @endunless
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $user->isItAdmin()
                        ? ($isEn ? 'IT Administrator View: Technical details, endpoints, and live health check controls.' : 'Tampilan IT Administrator: Detail teknis, endpoint, dan kontrol pemeriksaan status.')
                        : ($isEn ? 'General Manager View (Read-Only): High-level summary; sensitive network configs hidden.' : 'Tampilan General Manager (Read-Only): Ringkasan tingkat tinggi; detail IP/port sensitif disembunyikan.') }}
                </p>
            </div>
            @if ($user->canEditTopology())
                <button type="button" onclick="runTopologyHealthCheck()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>{{ $isEn ? 'Run Health Check' : 'Jalankan Cek Status' }}</span>
                </button>
            @else
                @php
                    $topoTotal = $topologyComponents->count();
                    $topoHealthy = $topologyComponents->where('status', 'healthy')->count();
                @endphp
                <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-semibold {{ $topoHealthy === $topoTotal ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                    <span class="w-2 h-2 rounded-full {{ $topoHealthy === $topoTotal ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    <span class="font-mono-num">{{ $topoHealthy }}/{{ $topoTotal }}</span>
                    <span>{{ $isEn ? 'components healthy' : 'komponen normal' }}</span>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs pt-1">
            @foreach (['LAN / VPN + MFA', 'Firewall & HTTPS', 'Laravel 13 + RBAC', 'MySQL (Internal VLAN)', 'Private File Storage', 'Separate NAS Backup'] as $step)
                <span class="px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/90 text-navy font-medium">{{ $step }}</span>
                @if (! $loop->last)
                    <svg class="w-3.5 h-3.5 text-teal shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                @endif
            @endforeach
        </div>
    </div>

    <!-- 8 Topology Component Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="topology-grid">
        @php
            $autoProbedKeys = ['app_server', 'web_server', 'laravel_app', 'mysql_db', 'private_storage', 'backup'];
        @endphp
        @foreach ($topologyComponents as $comp)
            @php
                $badgeCls = match($comp->status) {
                    'healthy' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                    default => 'bg-rose-50 text-crimson-dark border-rose-200',
                };
                $isAutoProbed = in_array($comp->component_key, $autoProbedKeys, true);
            @endphp
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between space-y-3">
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold truncate">{{ $comp->zone }}</div>
                            <h3 class="text-sm font-heading font-bold text-navy mt-0.5 truncate">{{ $isEn ? $comp->name_en : $comp->name }}</h3>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-mono-num font-bold border {{ $badgeCls }}">
                            {{ strtoupper($comp->status) }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $user->isItAdmin() ? $comp->it_technical_detail : ($isEn ? $comp->gm_summary_en : $comp->gm_summary) }}
                    </p>

                    @if ($user->isItAdmin() && $comp->host_or_endpoint)
                        <div class="text-[11px] font-mono-num text-teal bg-slate-50 border border-slate-200/80 rounded-lg px-2.5 py-1.5 break-all">
                            {{ $comp->host_or_endpoint }}
                        </div>
                    @endif
                </div>

                <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2 text-[11px] text-slate-400">
                    <span class="font-mono-num truncate">
                        {{ $comp->last_checked_at ? $comp->last_checked_at->copy()->timezone('Asia/Makassar')->format('d M Y, H:i').' WITA' : '-' }}
                    </span>
                    @if ($user->canEditTopology())
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono-num font-semibold {{ $isAutoProbed ? 'bg-teal/10 text-teal' : 'bg-slate-100 text-slate-500' }}">
                                {{ $isAutoProbed ? 'LIVE PROBE' : 'ATTESTED' }}
                            </span>
                            <button type="button" onclick='openTopologyEditor(@json($comp))' class="font-semibold text-teal hover:text-navy cursor-pointer">
                                {{ $isEn ? 'Edit' : 'Ubah' }}
                            </button>
                        </div>
                    @else
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-300">{{ $isEn ? 'View only' : 'Lihat saja' }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($user->canEditTopology() && ! empty($systemDiagnostics))
        @php
            $diagSummary = $systemDiagnostics['summary'];
            $diagRuntime = $systemDiagnostics['runtime'];
            $diagDb = $systemDiagnostics['db'];
        @endphp
        <!-- IT SysAdmin Production Readiness & Live Diagnostics -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-heading font-bold text-navy">
                            {{ $isEn ? 'IT SysAdmin Production Readiness & Runtime Audit' : 'Audit Kesiapan Produksi & Diagnostik Runtime (IT SysAdmin)' }}
                        </h3>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono-num font-bold border {{ $diagSummary['fail'] > 0 ? 'bg-rose-50 text-crimson-dark border-rose-200' : ($diagSummary['warn'] > 0 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') }}">
                            {{ $diagSummary['pass'] }}/{{ $diagSummary['total'] }} PASS
                        </span>
                        @if ($diagSummary['warn'] > 0)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-mono-num font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                {{ $diagSummary['warn'] }} WARN
                            </span>
                        @endif
                        @if ($diagSummary['fail'] > 0)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-mono-num font-semibold bg-rose-50 text-crimson-dark border border-rose-200">
                                {{ $diagSummary['fail'] }} FAIL
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $isEn ? 'Live non-destructive security, database, storage, and backup readiness checks (secrets omitted).' : 'Pemeriksaan langsung keamanan konfigurasi, database, storage, dan backup tanpa menampilkan kredensial.' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                    @php $attentionCount = $diagSummary['warn'] + $diagSummary['fail']; @endphp
                    <button type="button" data-readiness-filter="all" onclick="filterReadinessChecks('all')"
                        class="readiness-filter-btn px-2.5 py-1 rounded-lg text-[11px] font-semibold border bg-navy text-white border-navy transition cursor-pointer">
                        {{ $isEn ? 'All' : 'Semua' }} ({{ $diagSummary['total'] }})
                    </button>
                    <button type="button" data-readiness-filter="attention" onclick="filterReadinessChecks('attention')"
                        class="readiness-filter-btn px-2.5 py-1 rounded-lg text-[11px] font-semibold border bg-white text-slate-600 border-slate-200 hover:bg-slate-50 transition cursor-pointer">
                        {{ $isEn ? 'Needs Attention' : 'Perlu Perhatian' }} ({{ $attentionCount }})
                    </button>
                    <button type="button" data-readiness-filter="pass" onclick="filterReadinessChecks('pass')"
                        class="readiness-filter-btn px-2.5 py-1 rounded-lg text-[11px] font-semibold border bg-white text-slate-600 border-slate-200 hover:bg-slate-50 transition cursor-pointer">
                        Pass ({{ $diagSummary['pass'] }})
                    </button>
                    <span class="text-[11px] font-mono-num text-slate-400 ml-1 hidden sm:inline">{{ $diagSummary['checked_at_wita'] }}</span>
                </div>
            </div>

            <!-- Runtime Telemetry Strip -->
            <div class="px-5 py-3.5 bg-slate-50/60 border-b border-slate-100 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs">
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Host / SAPI</div>
                    <div class="font-mono-num font-semibold text-navy truncate mt-0.5">{{ $diagRuntime['hostname'] }} ({{ $diagRuntime['sapi'] }})</div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Framework</div>
                    <div class="font-mono-num font-semibold text-navy truncate mt-0.5">Laravel {{ $diagRuntime['laravel_version'] }} • PHP {{ $diagRuntime['php_version'] }}</div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Database</div>
                    <div class="font-mono-num font-semibold text-navy truncate mt-0.5">{{ strtoupper($diagDb['driver']) }} v{{ $diagDb['version'] }} ({{ $diagDb['latency_ms'] }} ms)</div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Cache / Session</div>
                    <div class="font-mono-num font-semibold text-navy truncate mt-0.5">{{ $diagRuntime['cache_driver'] }} / {{ $diagRuntime['session_driver'] }}</div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Queue / Failed</div>
                    <div class="font-mono-num font-semibold truncate mt-0.5 {{ $diagRuntime['failed_jobs'] > 0 ? 'text-crimson-dark' : 'text-navy' }}">{{ $diagRuntime['queue_driver'] }} ({{ $diagRuntime['failed_jobs'] }} failed)</div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200/70 px-3 py-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Timezone</div>
                    <div class="font-mono-num font-semibold text-navy truncate mt-0.5">{{ $diagRuntime['timezone'] }}</div>
                </div>
            </div>

            <!-- 10-Point Production Readiness Checklist -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-left border-collapse text-xs">
                    <thead class="bg-slate-50/70 border-b border-slate-100 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3 px-5">{{ $isEn ? 'Check' : 'Parameter Pemeriksaan' }}</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">{{ $isEn ? 'Current Value' : 'Kondisi Saat Ini' }}</th>
                            <th class="py-3 px-4">{{ $isEn ? 'Prod Target' : 'Target Produksi' }}</th>
                            <th class="py-3 px-5">{{ $isEn ? 'IT Action / Notes' : 'Catatan & Tindakan IT' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($systemDiagnostics['checks'] as $chk)
                            @php
                                $chkCls = match($chk['status']) {
                                    'pass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'warn' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    default => 'bg-rose-50 text-crimson-dark border-rose-200',
                                };
                            @endphp
                            <tr data-readiness-status="{{ $chk['status'] }}" class="readiness-row hover:bg-slate-50/60 transition">
                                <td class="py-3 px-5 font-semibold text-navy whitespace-nowrap">{{ $chk['label'] }}</td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-bold border {{ $chkCls }}">
                                        {{ strtoupper($chk['status']) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-mono-num text-slate-700 whitespace-nowrap">{{ $chk['current'] }}</td>
                                <td class="py-3 px-4 font-mono-num text-slate-500 whitespace-nowrap">{{ $chk['target'] }}</td>
                                <td class="py-3 px-5 text-slate-600">{{ $chk['advice'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            window.filterReadinessChecks = window.filterReadinessChecks || function (mode) {
                document.querySelectorAll('.readiness-row').forEach(function (row) {
                    const st = row.dataset.readinessStatus;
                    const show = mode === 'all' || (mode === 'attention' ? (st === 'warn' || st === 'fail') : st === mode);
                    row.classList.toggle('hidden', !show);
                });
                document.querySelectorAll('.readiness-filter-btn').forEach(function (btn) {
                    const active = btn.dataset.readinessFilter === mode;
                    btn.className = 'readiness-filter-btn px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition cursor-pointer ' +
                        (active ? 'bg-navy text-white border-navy' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50');
                });
            };
        </script>
    @endif
</div>
