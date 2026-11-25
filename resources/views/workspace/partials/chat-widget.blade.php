@php
    $isEn = $locale === 'en';
    $canPomec = $user->canAccessPomec();
    $canAllDepts = $user->canAccessAllDepartments();
    $canTopo = $user->canViewTopology();

    $guideChips = $isEn
        ? array_values(array_filter([
            'How to input & review reports',
            $canPomec ? 'How to upload POMEC?' : null,
            'How to export PDF/Excel',
            'How to trace report history',
            'What is LWBP/WBP?',
            'Escalate to HOD / IT',
        ]))
        : array_values(array_filter([
            'Cara input & review laporan',
            $canPomec ? 'Cara unggah POMEC?' : null,
            'Cara ekspor PDF/Excel',
            'Cara lihat histori laporan',
            'Apa itu LWBP/WBP?',
            'Eskalasi ke HOD / IT',
        ]));

    $dataChips = match(true) {
        $canAllDepts => $isEn
            ? array_values(array_filter([
                'Which reports are awaiting review?',
                'Which department has the most pending reports?',
                'Executive summary of reports this month',
                'Compare electricity usage last 7 days',
                'Which equipment is critical?',
                $canTopo ? 'System topology status' : null,
            ]))
            : array_values(array_filter([
                'Laporan apa yang masih menunggu review?',
                'Departemen mana yang paling banyak laporan tertunda?',
                'Ringkasan laporan bulan ini',
                'Bandingkan pemakaian listrik 7 hari terakhir',
                'Peralatan apa yang statusnya critical?',
                $canTopo ? 'Status topologi sistem' : null,
            ])),
        $user->role === 'hod' => $isEn
            ? array_values(array_filter([
                'Pending review in my dept',
                'Returned reports in my dept',
                'Summarise my department reports this month',
                'Compare my department reports this week',
                $canPomec ? 'Compare electricity usage last 7 days' : null,
            ]))
            : array_values(array_filter([
                'Laporan tunggu review di departemen saya',
                'Laporan perlu revisi di departemen saya',
                'Ringkas laporan departemen saya bulan ini',
                'Bandingkan laporan departemen saya minggu ini',
                $canPomec ? 'Bandingkan pemakaian listrik 7 hari terakhir' : null,
            ])),
        default => $isEn
            ? array_values(array_filter([
                'My department reports',
                'Returned reports in my dept',
                'Summarise my department reports this month',
                $canPomec ? 'Utility summary this month' : null,
            ]))
            : array_values(array_filter([
                'Laporan departemen saya',
                'Laporan perlu revisi di departemen saya',
                'Ringkas laporan departemen saya bulan ini',
                $canPomec ? 'Ringkasan utilitas POMEC bulan ini' : null,
            ])),
    };
@endphp

<!-- Clean Floating Launcher Pill (Bernie Walks Strictly Inside Pill Area) -->
<button type="button" id="chat-launcher"
    class="fixed bottom-5 right-5 z-40 inline-flex items-center gap-2.5 pl-2 pr-4 py-2 rounded-full bg-white border border-slate-200/90 shadow-[0_8px_24px_rgba(15,41,66,0.14)] hover:border-teal hover:shadow-[0_10px_28px_rgba(30,107,123,0.2)] transition cursor-pointer group">
    <span class="relative w-14 h-8 rounded-full bg-alabaster border border-slate-200/80 overflow-hidden flex items-end justify-start px-1 shrink-0">
        <span id="bernie-pill-walker" class="inline-flex items-end justify-center will-change-transform">
            <span id="bernie-pill-flip" class="bernie-flip inline-flex items-end justify-center">
                @include('workspace.partials.bernie-mascot', ['state' => 'walk', 'class' => 'w-6 h-7'])
            </span>
        </span>
        <img src="{{ asset('images/swissbel-icon.png') }}" alt="Swiss-Belhotel"
            class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full ring-1 ring-white object-cover">
    </span>
    <span class="text-left">
        <span class="block text-xs font-heading font-bold text-navy leading-tight">Report Assistant</span>
        <span class="block text-[10px] font-mono-num text-teal leading-tight">Read-Only &bull; {{ $user->scopeLabel($locale) }}</span>
    </span>
</button>

<!-- Resizable Floating AI Assistant Panel (Hidden Until Opened) -->
<div id="chat-widget"
    data-guide-chips="{{ json_encode($guideChips, JSON_UNESCAPED_UNICODE) }}"
    data-data-chips="{{ json_encode($dataChips, JSON_UNESCAPED_UNICODE) }}"
    class="hidden fixed bottom-5 right-5 z-40 w-[calc(100vw-2rem)] sm:w-[440px] h-[540px] bg-white border border-slate-200/90 rounded-2xl shadow-[0_20px_50px_rgba(15,41,66,0.18)] flex flex-col overflow-hidden">
    <!-- Drag-to-Resize Handle (Top-Left Corner) -->
    <div id="chat-resize-handle" title="{{ $isEn ? 'Drag corner to resize' : 'Tarik sudut untuk ubah ukuran' }}"
        class="hidden sm:flex absolute top-0 left-0 w-5 h-5 cursor-nwse-resize z-20 items-center justify-center text-slate-300 hover:text-teal select-none">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 17L17 7M7 7h10v10"/>
        </svg>
    </div>

    <!-- Clean Light Executive Header -->
    <div class="bg-white border-b border-slate-100 px-4 py-3 flex items-center justify-between gap-2 shrink-0">
        <div class="flex items-center gap-2.5 pl-1.5 min-w-0">
            <button type="button" id="chat-header-bernie-btn" title="Bernie • Swiss-Belhotel"
                class="relative w-9 h-9 rounded-xl bg-alabaster border border-slate-200/80 flex items-end justify-center overflow-visible shrink-0 cursor-pointer hover:border-teal transition">
                <span id="chat-header-bernie" class="inline-flex items-end justify-center translate-y-0.5">
                    @include('workspace.partials.bernie-mascot', ['state' => 'wave', 'class' => 'w-7 h-8'])
                </span>
                <img src="{{ asset('images/swissbel-icon.png') }}" alt="Swiss-Belhotel"
                    class="absolute -bottom-1 -right-1 w-4 h-4 rounded-md ring-2 ring-white object-cover shadow-2xs">
            </button>
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-heading font-bold text-navy truncate">Report Assistant</span>
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                        Read-Only
                    </span>
                </div>
                <div class="text-[11px] font-mono-num text-slate-500 truncate">
                    {{ $user->scopeLabel($locale) }}
                </div>
            </div>
        </div>

        <div class="flex items-center gap-1 shrink-0">
            <button type="button" id="chat-expand-btn" title="{{ $isEn ? 'Expand / Shrink' : 'Perbesar / Perkecil' }}"
                class="w-7 h-7 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
            <button type="button" id="chat-reset-btn" title="{{ $isEn ? 'Reset conversation' : 'Reset percakapan' }}"
                class="w-7 h-7 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
            <button type="button" id="chat-minimize-btn" title="{{ $isEn ? 'Close' : 'Tutup' }}"
                class="w-7 h-7 rounded-lg text-slate-500 hover:text-crimson-dark hover:bg-rose-50 flex items-center justify-center transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Segmented Mode Switcher (shown once user picks a mode; keeps header & footer clean) -->
    <div id="chat-filter-bar" class="hidden px-3.5 py-2 bg-slate-50/80 border-b border-slate-100 shrink-0">
        <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-slate-200/60">
            <button type="button" data-chat-filter="guide"
                class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-slate-600 hover:text-navy transition cursor-pointer truncate">
                {{ $isEn ? 'System Guide & SOP' : 'Panduan Sistem & SOP' }}
            </button>
            <button type="button" data-chat-filter="data"
                class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-slate-600 hover:text-navy transition cursor-pointer truncate">
                {{ $isEn ? 'Reports & Analytics' : 'Laporan & Analisis Data' }}
            </button>
        </div>
    </div>

    <!-- Widget Body -->
    <div id="chat-body" class="flex-1 flex flex-col min-h-0 bg-alabaster">
        <!-- Messages Area -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 text-xs">
            <div class="chat-msg-in bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs space-y-3">
                <!-- Clean Contained Bernie Promenade Inside Chat Area -->
                <div id="bernie-stage" class="relative h-14 rounded-xl bg-alabaster border border-slate-200/70 px-3 flex items-end justify-between overflow-hidden">
                    <div id="bernie-stage-walker" class="inline-flex items-end will-change-transform cursor-pointer" title="Bernie • Swiss-Belhotel">
                        <span id="bernie-stage-flip" class="bernie-flip inline-flex items-end justify-center">
                            @include('workspace.partials.bernie-mascot', ['state' => 'walk', 'class' => 'w-10 h-12'])
                        </span>
                    </div>
                    <img src="{{ asset('images/swissbel-logo.png') }}" alt="Swiss-Belhotel" class="h-6 w-auto object-contain self-center opacity-90 shrink-0">
                </div>
                <div class="space-y-1">
                    <div class="text-[13px] font-heading font-bold text-navy">
                        {{ $isEn ? "Hello, {$user->name}" : "Halo, {$user->name}" }}
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        @if ($isEn)
                            Please select a conversation mode below to get started:
                        @else
                            Pilih mode percakapan di bawah ini untuk memulai:
                        @endif
                    </p>
                </div>
                <div class="space-y-2">
                    <button type="button" data-chat-track="guide"
                        class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-slate-200 bg-alabaster/70 hover:border-teal hover:bg-teal/5 text-left transition cursor-pointer group">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-teal/10 text-teal flex items-center justify-center shrink-0 group-hover:bg-teal group-hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-navy leading-snug">{{ $isEn ? 'System Guide & SOP' : 'Panduan Sistem & SOP' }}</div>
                                <div class="text-[11px] text-slate-500 leading-snug">{{ $isEn ? 'Workflows, export steps & hotel glossary' : 'Cara penggunaan, alur kerja & glosarium' }}</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 group-hover:text-teal shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button type="button" data-chat-track="data"
                        class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-slate-200 bg-alabaster/70 hover:border-navy hover:bg-navy/5 text-left transition cursor-pointer group">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-navy/10 text-navy flex items-center justify-center shrink-0 group-hover:bg-navy group-hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-navy leading-snug">{{ $isEn ? 'Reports & Analytics' : 'Laporan & Analisis Data' }}</div>
                                <div class="text-[11px] text-slate-500 leading-snug">{{ $isEn ? 'Department reports, KPIs & POMEC utilities' : 'Status laporan, KPI departemen & POMEC' }}</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 group-hover:text-navy shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Input Bar (locked until a topic filter is selected) -->
        <form id="chat-form" class="p-3 bg-white border-t border-slate-200/80 flex items-center gap-2 shrink-0">
            <input id="chat-input" type="text" required maxlength="500" disabled
                placeholder="{{ $isEn ? 'Select a mode above to start chatting...' : 'Pilih mode di atas untuk memulai percakapan...' }}"
                class="flex-1 px-3.5 py-2 text-xs rounded-xl border border-slate-200 bg-alabaster focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal disabled:opacity-60 disabled:cursor-not-allowed transition">
            <button type="submit" id="chat-send-btn" aria-label="Send" disabled
                class="w-9 h-9 rounded-xl bg-navy hover:bg-teal text-white flex items-center justify-center transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </form>
    </div>
</div>
