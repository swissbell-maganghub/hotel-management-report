{{-- [WASTAGE-REPORT] NEW FILE: Dashboard & Input Data Wastage Report --}}
@php
    $isEn = $locale === 'en';
    $currentReport = $activeWastageReport;
    $wasteFields = \App\Models\WastageRecord::WASTE_FIELDS;
    $thCls = 'px-2 py-2 border border-slate-200 text-center align-middle text-[11px] font-bold text-navy';
    $tdCls = 'px-2 py-2 border border-slate-200 text-right font-mono-num text-xs';

    // [WASTAGE-REPORT] Helper 12 bulan Masehi & label periode
    $masehiMonthNames = $isEn
        ? [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December']
        : [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

    $formatPeriodLabel = function (string $ym) use ($masehiMonthNames): string {
        $parts = explode('-', $ym);
        if (count($parts) !== 2) {
            return $ym;
        }
        $mNum = (int) $parts[1];

        return ($masehiMonthNames[$mNum] ?? $parts[1]) . ' ' . $parts[0];
    };

    $selectedPeriodLabel = $formatPeriodLabel($wastageSelectedPeriod);
@endphp

<div class="space-y-5">
    {{-- Alert Flash Message --}}
    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between">
            <span>{{ session('status') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
        </div>
    @endif

    {{-- [WASTAGE-REPORT] Header & Sub-Navigasi (Tata letak rapi & sejajar) --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="min-w-0 space-y-1.5">
                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-md bg-teal/10 text-teal font-mono-num text-[11px] font-bold uppercase tracking-wider">
                    F&amp;B &bull; WASTAGE REPORT
                </span>
                <h2 class="text-lg sm:text-xl font-heading font-extrabold text-navy tracking-tight leading-snug">
                    {{ $isEn ? 'Food Waste Management Dashboard' : 'Dashboard Laporan Food Waste' }}
                </h2>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('wastage.template') }}"
                    class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl text-xs font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition cursor-pointer whitespace-nowrap"
                    title="{{ $isEn ? 'Download sample Excel template' : 'Unduh Template Excel Harian' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>{{ $isEn ? 'Excel Template' : 'Unduh Template' }}</span>
                </a>

                @if ($currentReport)
                    <a href="{{ route('wastage.export', $currentReport->id) }}"
                        class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Export {{ $currentReport->report_code }}</span>
                    </a>
                @endif

                @if ($canModifyWastage)
                    <button type="button" onclick="document.getElementById('wastage-import-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl text-xs font-semibold bg-navy hover:bg-navy-light text-white transition cursor-pointer whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/></svg>
                        <span>{{ $isEn ? 'Import Excel' : 'Impor File Excel' }}</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Selector Laporan & Detail Metadata Aktif --}}
        @if ($wastageReports->isNotEmpty() || $currentReport)
            <div class="pt-4 border-t border-slate-100 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    @if ($wastageReports->isNotEmpty())
                        <form method="GET" action="{{ route('workspace.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <input type="hidden" name="tab" value="wastage">
                            <label for="wastage-report-selector" class="text-xs text-slate-500 font-semibold whitespace-nowrap">
                                {{ $isEn ? 'Select Report:' : 'Pilih Laporan:' }}
                            </label>
                            <select id="wastage-report-selector" name="report_id" onchange="this.form.submit()"
                                class="h-9 w-full sm:w-auto sm:min-w-[280px] px-3 text-xs font-mono-num rounded-xl border border-slate-200 bg-white text-navy focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
                                @foreach ($wastageReports as $rep)
                                    <option value="{{ $rep->id }}" {{ $currentReport && $currentReport->id === $rep->id ? 'selected' : '' }}>
                                        {{ $rep->report_code }} ({{ $rep->period_month }}) &bull; {{ number_format($rep->total_wastage_kg, 1) }} KG
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <div></div>
                    @endif

                    @if ($currentReport && $canModifyWastage)
                        <form method="POST" action="{{ route('wastage.destroy', $currentReport->id) }}" onsubmit="return confirm('Hapus laporan {{ $currentReport->report_code }} ini beserta seluruh datanya?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white text-xs font-semibold transition cursor-pointer whitespace-nowrap">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span>{{ $isEn ? 'Delete Report' : 'Hapus Laporan' }}</span>
                            </button>
                        </form>
                    @endif
                </div>

                @if ($currentReport)
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-12 gap-2.5">
                        <div class="lg:col-span-2 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Report Code' : 'Kode Laporan' }}</span>
                            <span class="block font-heading font-bold text-navy font-mono-num text-xs truncate" title="{{ $currentReport->report_code }}">
                                {{ $currentReport->report_code }}
                            </span>
                        </div>
                        <div class="lg:col-span-2 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Period' : 'Periode Laporan' }}</span>
                            <span class="block font-heading font-bold text-navy font-mono-num text-xs truncate">
                                {{ $currentReport->period_month }}
                            </span>
                        </div>
                        <div class="lg:col-span-2 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Imported By' : 'Diimpor Oleh' }}</span>
                            <span class="block font-bold text-navy text-xs truncate" title="{{ $currentReport->importer?->name ?? 'System' }}">
                                {{ $currentReport->importer?->name ?? 'Manual System' }}
                            </span>
                        </div>
                        <div class="lg:col-span-3 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Source File' : 'Nama File Sumber' }}</span>
                            <span class="block font-bold text-navy text-xs truncate" title="{{ $currentReport->original_filename ?? 'Input Manual' }}">
                                {{ $currentReport->original_filename ?? 'Input Manual' }}
                            </span>
                        </div>
                        <div class="lg:col-span-2 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Import Date' : 'Tanggal Impor' }}</span>
                            <span class="block font-bold font-mono-num text-navy text-xs truncate">
                                {{ $currentReport->imported_at ? $currentReport->imported_at->copy()->timezone('Asia/Makassar')->format('d M Y H:i') : $currentReport->created_at->format('d M Y') }}
                            </span>
                        </div>
                        <div class="lg:col-span-1 px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $isEn ? 'Records' : 'Jumlah Hari' }}</span>
                            <span class="block font-heading font-bold text-navy font-mono-num text-xs truncate">
                                {{ $currentReport->records->count() }} {{ $isEn ? 'd' : 'hari' }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- [WASTAGE-REPORT] KPI Cards: Total Wastage (KG), Total Cover, KG per Cover (sinkron dengan bulan terpilih) --}}
    @php
        $kpiHasDailyRows = $wastageDailyRecords->isNotEmpty();
        $kpiKg = $kpiHasDailyRows
            ? (float) $wastageDailyRecords->sum('total_kg')
            : (($currentReport && $currentReport->period_month === $wastageSelectedPeriod) ? (float) $currentReport->total_wastage_kg : 0.0);
        $kpiCover = $kpiHasDailyRows
            ? (int) $wastageDailyRecords->sum('covers')
            : (($currentReport && $currentReport->period_month === $wastageSelectedPeriod) ? (int) $currentReport->total_cover : 0);
        $kpiRatio = $kpiCover > 0 ? round($kpiKg / $kpiCover, 4) : 0.0;
        $kpiLastRecord = $wastageDailyRecords->last();
        $kpiYtdKg = ($kpiLastRecord && (float) $kpiLastRecord->ytd_kg > 0)
            ? (float) $kpiLastRecord->ytd_kg
            : ($currentReport ? (float) $currentReport->ytd_kg : $kpiKg);
        $kpiYtdCover = ($kpiLastRecord && (int) $kpiLastRecord->ytd_cover > 0)
            ? (int) $kpiLastRecord->ytd_cover
            : ($currentReport ? (int) $currentReport->ytd_cover : $kpiCover);
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Wastage KG --}}
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-slate-500 text-xs font-medium gap-2">
                    <span class="truncate">{{ $isEn ? 'Total Wastage' : 'Total Wastage' }} ({{ $selectedPeriodLabel }})</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">KG</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ number_format($kpiKg, 2, ',', '.') }}
                    </span>
                    <span class="text-xs font-medium text-slate-500">KG</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400 font-mono-num">
                {{ ($kpiHasDailyRows || $currentReport) ? 'YTD: ' . number_format($kpiYtdKg, 2, ',', '.') . ' KG' : $selectedPeriodLabel }}
            </div>
        </div>

        {{-- Total Cover --}}
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-slate-500 text-xs font-medium gap-2">
                    <span class="truncate">{{ $isEn ? 'Total Cover' : 'Total Cover Tamu' }}</span>
                    <span class="w-8 h-8 rounded-xl bg-teal/10 text-teal flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ number_format($kpiCover, 0, ',', '.') }}
                    </span>
                    <span class="text-xs font-medium text-slate-500">Covers</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400 font-mono-num">
                {{ ($kpiHasDailyRows || $currentReport) ? 'YTD Cover: ' . number_format($kpiYtdCover, 0, ',', '.') : $selectedPeriodLabel }}
            </div>
        </div>

        {{-- KG per Cover --}}
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-slate-500 text-xs font-medium gap-2">
                    <span class="truncate">{{ $isEn ? 'KG / Cover Ratio' : 'Rasio KG / Cover' }}</span>
                    <span class="w-8 h-8 rounded-xl bg-cyan-50 text-teal flex items-center justify-center font-bold text-xs shrink-0">&empty;</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-heading font-extrabold font-mono-num text-teal">
                        {{ number_format($kpiRatio, 4, ',', '.') }}
                    </span>
                    <span class="text-xs font-medium text-slate-500">KG/Cov</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                @php
                    $grams = $kpiRatio * 1000;
                @endphp
                &asymp; {{ number_format($grams, 1, ',', '.') }} gram per tamu
            </div>
        </div>

        {{-- Total Laporan / Status Impor --}}
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between gap-3">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-slate-500 text-xs font-medium gap-2">
                    <span class="truncate">{{ $isEn ? 'Database Records' : 'Total Batch Laporan' }}</span>
                    <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-heading font-extrabold font-mono-num text-navy">
                        {{ $wastageReports->count() }}
                    </span>
                    <span class="text-xs font-medium text-slate-500">{{ $isEn ? 'reports' : 'periode' }}</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 text-[11px] text-emerald-700 font-medium">
                Data terintegrasi Food Waste F&amp;B
            </div>
        </div>
    </div>

    {{-- [WASTAGE-REPORT] Grafik Analisis Food Waste (Per Hari & Per Bulan) --}}
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Food Waste Analytics Charts' : 'Grafik Laporan Food Waste' }}
                </h3>
            </div>
            <button type="button" id="wastage-charts-toggle"
                class="self-start sm:self-auto px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-semibold text-slate-600 transition cursor-pointer">
                {{ $isEn ? 'Hide Charts' : 'Sembunyikan Grafik' }}
            </button>
        </div>

        <div id="wastage-charts-grid" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            {{-- Card 1: Grafik Per Hari (Filter per Tanggal) --}}
            <div class="bg-slate-50/50 rounded-xl border border-slate-200/80 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'Daily Chart' : 'Grafik Per Hari' }}</div>
                        <div id="wastage-chart-daily-meta" class="text-[11px] font-mono-num text-slate-500">-</div>
                    </div>
                    <select id="wastage-chart-date-filter"
                        aria-label="{{ $isEn ? 'Select date for daily chart' : 'Pilih tanggal untuk grafik per hari' }}"
                        class="h-8 px-2.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-navy focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal"></select>
                </div>
                <div class="relative h-64 w-full">
                    <canvas id="wastage-chart-daily"></canvas>
                    <div id="wastage-chart-daily-empty" class="hidden absolute inset-0 flex flex-col items-center justify-center text-center text-xs text-slate-400 px-4"></div>
                </div>
                <div id="wastage-chart-daily-legend" class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-1 text-[11px] text-slate-500 border-t border-slate-200/70"></div>
            </div>

            {{-- Card 2: Grafik Per Bulan (Komposisi Kategori, Rata-rata KG/COVER & MoM) --}}
            <div class="bg-slate-50/50 rounded-xl border border-slate-200/80 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'Monthly Chart' : 'Grafik Per Bulan' }}</div>
                        <div id="wastage-chart-monthly-meta" class="text-[11px] font-mono-num text-slate-500">-</div>
                    </div>
                    <select id="wastage-chart-month-filter"
                        aria-label="{{ $isEn ? 'Select month for monthly chart' : 'Pilih bulan untuk grafik per bulan' }}"
                        class="h-8 px-2.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-navy focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal"></select>
                </div>
                <div class="relative h-64 w-full">
                    <canvas id="wastage-chart-monthly"></canvas>
                    <div id="wastage-chart-monthly-empty" class="hidden absolute inset-0 flex flex-col items-center justify-center text-center text-xs text-slate-400 px-4"></div>
                </div>
                <div id="wastage-chart-monthly-mom" class="hidden pt-2 border-t border-slate-200/70 text-[11px]"></div>
            </div>

            {{-- Card 3: Grafik Per Tahun (Mode Tahunan & Ringkasan YTD, lebar penuh) --}}
            <div class="xl:col-span-2 bg-slate-50/50 rounded-xl border border-slate-200/80 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'Yearly Chart & YTD Evaluation' : 'Grafik Per Tahun & Evaluasi YTD' }}</div>
                        <div id="wastage-chart-yearly-meta" class="text-[11px] font-mono-num text-slate-500">-</div>
                    </div>
                    <select id="wastage-chart-yearly-filter"
                        aria-label="{{ $isEn ? 'Select mode or year for yearly chart' : 'Pilih mode atau tahun untuk grafik per tahun' }}"
                        class="h-8 px-2.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-navy focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal"></select>
                </div>
                <div class="relative h-72 w-full">
                    <canvas id="wastage-chart-yearly"></canvas>
                    <div id="wastage-chart-yearly-empty" class="hidden absolute inset-0 flex flex-col items-center justify-center text-center text-xs text-slate-400 px-4"></div>
                </div>
                <div id="wastage-chart-yearly-eval" class="hidden grid grid-cols-1 md:grid-cols-3 gap-2.5 pt-2 border-t border-slate-200/70"></div>
            </div>
        </div>
    </div>

    {{-- [WASTAGE-REPORT] Tabel Tunggal Sesuai Template Excel (bersih tanpa warna latar) --}}
    @php
        $fmtVal = fn ($v): string => fmod((float) $v, 1.0) == 0.0
            ? number_format((float) $v, 0, ',', '.')
            : number_format((float) $v, 1, ',', '.');
        $totalRowKg = (float) $wastageDailyRecords->sum('total_kg');
        $totalRowCover = (int) $wastageDailyRecords->sum('covers');
        $totalRowRatio = $totalRowCover > 0 ? ($totalRowKg / $totalRowCover) : 0.0;
    @endphp
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden space-y-4 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Food Waste Report Table' : 'Tabel Laporan Food Waste' }} &mdash; {{ $selectedPeriodLabel }}
                </h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- [WASTAGE-REPORT] Filter Periode Bulan Masehi --}}
                <form method="GET" action="{{ route('workspace.index') }}" class="inline-flex items-center gap-1.5">
                    <input type="hidden" name="tab" value="wastage">
                    <label for="wastage-period-filter" class="text-xs text-slate-500 font-medium">
                        {{ $isEn ? 'Month:' : 'Periode Bulan:' }}
                    </label>
                    <select name="period" id="wastage-period-filter" onchange="this.form.submit()"
                        class="h-8.5 px-2.5 text-xs font-semibold rounded-xl border border-slate-200 bg-white text-navy focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
                        @foreach ($wastageAvailableYears as $yr)
                            @if ($wastageAvailableYears->count() > 1)
                                <optgroup label="{{ $isEn ? "Year {$yr}" : "Tahun {$yr}" }}">
                            @endif
                            @for ($mNum = 1; $mNum <= 12; $mNum++)
                                @php
                                    $pKey = sprintf('%04d-%02d', (int) $yr, $mNum);
                                    $pDays = $wastagePeriodsWithData->get($pKey);
                                @endphp
                                <option value="{{ $pKey }}" {{ $wastageSelectedPeriod === $pKey ? 'selected' : '' }}>
                                    {{ $masehiMonthNames[$mNum] }} {{ $yr }}{{ $pDays !== null && $pDays > 0 ? " • ({$pDays} " . ($isEn ? 'days' : 'hari') . ')' : '' }}
                                </option>
                            @endfor
                            @if ($wastageAvailableYears->count() > 1)
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                </form>

                <div class="text-xs font-mono-num text-slate-500 font-semibold px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                    Total: {{ $wastageDailyRecords->count() }} {{ $isEn ? 'days' : 'hari' }}
                </div>
                {{-- [WASTAGE-REPORT] Tombol Preview & Ekspor PDF Bulanan sesuai periode bulan Masehi aktif --}}
                <button type="button"
                    id="wastage-export-monthly-pdf-btn"
                    data-preview-url="{{ route('wastage.export.monthly.pdf', ['period' => $wastageSelectedPeriod, 'mode' => 'preview']) }}"
                    onclick="window.openWastagePdfPreview && window.openWastagePdfPreview()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white text-xs font-semibold transition cursor-pointer whitespace-nowrap"
                    title="{{ $isEn ? "Preview & Export {$selectedPeriodLabel} to PDF" : "Preview & Ekspor PDF Bulanan {$selectedPeriodLabel}" }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span>{{ $isEn ? "Export PDF ({$selectedPeriodLabel})" : "Ekspor PDF ({$selectedPeriodLabel})" }}</span>
                </button>
                @if ($canModifyWastage)
                    <button type="button" onclick="document.getElementById('wastage-daily-input-panel').classList.toggle('hidden')"
                        class="px-3.5 py-1.5 rounded-xl border border-teal text-teal hover:bg-teal hover:text-white text-xs font-semibold transition cursor-pointer">
                        + {{ $isEn ? 'Add Daily Data' : 'Tambah Data Harian' }}
                    </button>
                @endif
            </div>
        </div>

        {{-- [WASTAGE-REPORT] Pills cepat perpindahan antar bulan Masehi yang sudah memiliki data --}}
        @if ($wastagePeriodsWithData->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-[11px] font-semibold text-slate-400 mr-1">
                    {{ $isEn ? 'Available Months:' : 'Bulan Terisi:' }}
                </span>
                @foreach ($wastagePeriodsWithData as $pKey => $pDays)
                    @php
                        $isPeriodActive = $wastageSelectedPeriod === $pKey;
                    @endphp
                    <a href="{{ route('workspace.index', ['tab' => 'wastage', 'period' => $pKey]) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl border text-xs font-semibold transition {{ $isPeriodActive ? 'bg-navy text-white border-navy' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        <span>{{ $formatPeriodLabel($pKey) }}</span>
                        <span class="font-mono-num text-[10px] {{ $isPeriodActive ? 'text-slate-200' : 'text-slate-400' }}">({{ $pDays }} {{ $isEn ? 'd' : 'hari' }})</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Form Input Baris Harian (Collapsible) --}}
        @if ($canModifyWastage)
            <div id="wastage-daily-input-panel" class="hidden bg-slate-50/80 border border-slate-200 rounded-xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-navy uppercase tracking-wider">
                        {{ $isEn ? 'Input Daily Food Waste' : 'Form Input Data Harian' }} ({{ $selectedPeriodLabel }})
                    </span>
                    <button type="button" onclick="document.getElementById('wastage-daily-input-panel').classList.add('hidden')" class="text-slate-400 hover:text-navy text-xs cursor-pointer">&times; {{ $isEn ? 'Close' : 'Tutup' }}</button>
                </div>
                <form method="POST" action="{{ route('wastage.daily') }}" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2.5 text-xs">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Periode Bulan</label>
                        <input type="month" name="period_month" required value="{{ $wastageSelectedPeriod }}"
                            class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal (Date)</label>
                        <input type="date" name="record_date" required value="{{ $wastageSelectedPeriod === date('Y-m') ? date('Y-m-d') : $wastageSelectedPeriod . '-01' }}"
                            class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Buffet Lunch</label>
                        <input type="number" step="0.01" name="buffet_lunch" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Buffet Dinner</label>
                        <input type="number" step="0.01" name="buffet_dinner" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Buffet Gravity</label>
                        <input type="number" step="0.01" name="buffet_gravity" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Prep Butcher</label>
                        <input type="number" step="0.01" name="prep_butcher" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Prep Main Kitchen</label>
                        <input type="number" step="0.01" name="prep_main_kitchen" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Prep Pastry</label>
                        <input type="number" step="0.01" name="prep_pastry" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Prep Cold Kitchen</label>
                        <input type="number" step="0.01" name="prep_cold_kitchen" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Plate Waste</label>
                        <input type="number" step="0.01" name="plate_waste" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Breakfast Swiss</label>
                        <input type="number" step="0.01" name="breakfast_cafe" placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cover (Tamu)</label>
                        <input type="number" name="covers" required placeholder="0" class="w-full h-8 px-2 rounded-lg border border-slate-200 bg-white font-mono-num">
                    </div>
                    <div class="col-span-2 sm:col-span-4 lg:col-span-6 flex justify-end gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold cursor-pointer">
                            {{ $isEn ? 'Save Row' : 'Simpan Baris Data' }}
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full min-w-[1280px] border-collapse text-xs">
                <thead>
                    <tr>
                        <th colspan="16" class="px-3 py-2.5 border border-slate-200 text-center text-sm font-heading font-extrabold tracking-wide text-navy uppercase">
                            FOOD WASTE SWISS-BELHOTEL WATERFRONT MAKASSAR &mdash; {{ strtoupper($selectedPeriodLabel) }}
                        </th>
                    </tr>
                    <tr>
                        <th rowspan="2" class="{{ $thCls }} min-w-[115px]">Date</th>
                        <th colspan="3" class="{{ $thCls }}">Buffet Lunch, Dinner, Gravity</th>
                        <th colspan="4" class="{{ $thCls }}">Preparation</th>
                        <th rowspan="2" class="{{ $thCls }}">Plate Waste</th>
                        <th rowspan="2" class="{{ $thCls }}">Breakfast Swiss</th>
                        <th rowspan="2" class="{{ $thCls }}">TOTAL KG/Day</th>
                        <th rowspan="2" class="{{ $thCls }}">COVER</th>
                        <th rowspan="2" class="{{ $thCls }}">KG YTD</th>
                        <th rowspan="2" class="{{ $thCls }}">COVER YTD</th>
                        <th rowspan="2" class="{{ $thCls }}">KG/COVER</th>
                        <th rowspan="2" class="{{ $thCls }}">Aksi</th>
                    </tr>
                    <tr>
                        <th class="{{ $thCls }}">Lunch</th>
                        <th class="{{ $thCls }}">Dinner</th>
                        <th class="{{ $thCls }}">Gravity</th>
                        <th class="{{ $thCls }}">Butcher</th>
                        <th class="{{ $thCls }}">Main Kitchen</th>
                        <th class="{{ $thCls }}">Pastry</th>
                        <th class="{{ $thCls }}">Cold Kitchen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($wastageDailyRecords as $rec)
                        @php
                            $rep = $rec->report;
                            $isRowActive = $currentReport && $currentReport->id === $rec->wastage_report_id;
                        @endphp
                        <tr class="hover:bg-slate-50 transition {{ $isRowActive ? 'font-semibold' : '' }}">
                            <td class="px-2.5 py-2 border border-slate-200 font-mono-num text-navy font-semibold whitespace-nowrap">
                                <a href="{{ route('workspace.index', ['tab' => 'wastage', 'period' => $wastageSelectedPeriod, 'report_id' => $rec->wastage_report_id]) }}"
                                    class="hover:text-teal hover:underline inline-flex items-center gap-1.5"
                                    title="{{ ($rep?->report_code ?? '-') . ' • ' . ($rep?->original_filename ?? 'Input Manual') }}">
                                    @if ($isRowActive)
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal shrink-0"></span>
                                    @endif
                                    {{ $rec->record_date?->format('M j, Y') ?? '-' }}
                                </a>
                            </td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->buffet_lunch) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->buffet_dinner) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->buffet_gravity) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->prep_butcher) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->prep_main_kitchen) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->prep_pastry) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->prep_cold_kitchen) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->plate_waste) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($rec->breakfast_cafe) }}</td>
                            <td class="{{ $tdCls }} font-bold text-navy">{{ $fmtVal($rec->total_kg) }}</td>
                            <td class="{{ $tdCls }} font-semibold">{{ number_format((int)$rec->covers, 0, ',', '.') }}</td>
                            <td class="{{ $tdCls }}">{{ number_format((float)$rec->ytd_kg, 1, ',', '.') }}</td>
                            <td class="{{ $tdCls }}">{{ number_format((int)$rec->ytd_cover, 0, ',', '.') }}</td>
                            <td class="{{ $tdCls }} font-bold text-navy">{{ number_format((float)$rec->kg_per_cover, 3, ',', '.') }}</td>
                            <td class="px-2 py-2 border border-slate-200 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('wastage.export', $rec->wastage_report_id) }}"
                                        class="px-2 py-1 rounded-lg text-[11px] font-semibold border border-slate-200 text-slate-700 hover:bg-navy hover:text-white transition"
                                        title="Export Excel ({{ $rep?->report_code ?? '' }})">
                                        XLSX
                                    </a>
                                    @if ($canModifyWastage)
                                        <form method="POST" action="{{ route('wastage.destroy', $rec->wastage_report_id) }}" class="inline" onsubmit="return confirm('Hapus laporan {{ $rep?->report_code ?? '' }} ini beserta baris datanya?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 rounded-lg text-[11px] font-semibold border border-slate-200 text-slate-600 hover:bg-crimson hover:text-white transition cursor-pointer" title="Hapus Laporan ({{ $rep?->report_code ?? '' }})">
                                                &times;
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="py-10 text-center text-slate-400">
                                {{ $isEn
                                    ? "No food waste records for {$selectedPeriodLabel}. Click \"Import Excel\" or \"+ Add Daily Data\" above."
                                    : "Belum ada data food waste untuk {$selectedPeriodLabel}. Gunakan tombol \"Impor File Excel\" atau \"+ Tambah Data Harian\" di atas." }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($wastageDailyRecords->isNotEmpty())
                    @php
                        $lastDailyRec = $wastageDailyRecords->last();
                        $footerYtdKg = ($lastDailyRec && (float) $lastDailyRec->ytd_kg > 0) ? (float) $lastDailyRec->ytd_kg : $totalRowKg;
                        $footerYtdCover = ($lastDailyRec && (int) $lastDailyRec->ytd_cover > 0) ? (int) $lastDailyRec->ytd_cover : $totalRowCover;
                    @endphp
                    <tfoot class="font-bold text-navy">
                        <tr>
                            <td class="px-2.5 py-2.5 border border-slate-200 uppercase text-[11px]">
                                TOTAL / SUMMARY
                            </td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('buffet_lunch')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('buffet_dinner')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('buffet_gravity')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('prep_butcher')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('prep_main_kitchen')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('prep_pastry')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('prep_cold_kitchen')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('plate_waste')) }}</td>
                            <td class="{{ $tdCls }}">{{ $fmtVal($wastageDailyRecords->sum('breakfast_cafe')) }}</td>
                            <td class="{{ $tdCls }} text-navy">{{ $fmtVal($totalRowKg) }}</td>
                            <td class="{{ $tdCls }}">{{ number_format($totalRowCover, 0, ',', '.') }}</td>
                            <td class="{{ $tdCls }}">{{ number_format($footerYtdKg, 1, ',', '.') }}</td>
                            <td class="{{ $tdCls }}">{{ number_format($footerYtdCover, 0, ',', '.') }}</td>
                            <td class="{{ $tdCls }} text-navy">{{ number_format($totalRowRatio, 3, ',', '.') }}</td>
                            <td class="border border-slate-200"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- MODAL IMPORT EXCEL WASTAGE (Proteksi Preview + Commit) --}}
@if ($canModifyWastage)
    <div id="wastage-import-modal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full border border-slate-200 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-heading font-extrabold text-navy text-sm">
                        {{ $isEn ? 'Import Food Waste Excel Template' : 'Impor File Excel Food Waste' }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ $isEn ? 'Preview rows and verify integrity before commit' : 'Preview baris data dan verifikasi sebelum disimpan' }}
                    </p>
                </div>
                <button type="button" onclick="document.getElementById('wastage-import-modal').classList.add('hidden')" class="text-slate-400 hover:text-navy text-lg">&times;</button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4">
                <form id="wastage-preview-form" class="space-y-3">
                    @csrf
                    {{-- [WASTAGE-REPORT] FIX: Input Periode Bulan dihapus, periode otomatis dari tanggal di file Excel --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">File Excel (.xlsx / .xls)</label>
                        <input type="file" name="excel_file" id="wst-excel-file" required accept=".xlsx,.xls,.csv"
                            class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal/10 file:text-teal hover:file:bg-teal hover:file:text-white file:cursor-pointer">
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-[11px] text-slate-400">Pastikan format kolom sesuai template resmi.</span>
                        <button type="submit" id="wst-preview-btn" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                            Preview File &rarr;
                        </button>
                    </div>
                </form>

                {{-- Preview Output Area --}}
                <div id="wst-preview-area" class="hidden border-t border-slate-100 pt-3 space-y-3">
                    <div id="wst-preview-summary" class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs"></div>
                    <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg">
                        {{-- [WASTAGE-REPORT] Preview table bersih --}}
                        <table class="w-full text-[11px] border-collapse" id="wst-preview-table">
                            <thead class="bg-white sticky top-0 font-bold text-navy">
                                <tr>
                                    <th class="p-1.5 border border-slate-200">Date</th>
                                    <th class="p-1.5 border border-slate-200">Total KG</th>
                                    <th class="p-1.5 border border-slate-200">Covers</th>
                                    <th class="p-1.5 border border-slate-200">KG/Cover</th>
                                    <th class="p-1.5 border border-slate-200">Status</th>
                                </tr>
                            </thead>
                            <tbody id="wst-preview-tbody"></tbody>
                        </table>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('wastage-import-modal').classList.add('hidden')" class="px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-600">Batal</button>
                        <button type="button" id="wst-commit-btn" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer">
                            Simpan ke Laporan &check;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Script untuk Preview & Commit Ajax --}}
    <script>
        (function () {
            let currentPreviewToken = null;
            const form = document.getElementById('wastage-preview-form');
            const fileInput = document.getElementById('wst-excel-file');
            const previewArea = document.getElementById('wst-preview-area');
            const previewSummary = document.getElementById('wst-preview-summary');
            const previewTbody = document.getElementById('wst-preview-tbody');
            const previewBtn = document.getElementById('wst-preview-btn');
            const commitBtn = document.getElementById('wst-commit-btn');

            if (!form) return;

            const escHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[c]));

            fileInput?.addEventListener('change', function () {
                currentPreviewToken = null;
                previewArea?.classList.add('hidden');
                if (previewTbody) previewTbody.innerHTML = '';
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                previewBtn.disabled = true;
                previewBtn.textContent = 'Memeriksa...';

                const fd = new FormData(form);
                try {
                    const res = await fetch("{{ route('wastage.preview') }}", {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });

                    const data = await res.json();
                    if (!res.ok) {
                        alert(data.message || 'Gagal memproses preview Excel');
                        return;
                    }

                    currentPreviewToken = data.preview_token;
                    const p = data.preview;
                    const validCount = Number(p.valid_count || 0);

                    previewSummary.innerHTML = `
                        <div class="font-bold text-navy">${escHtml(p.original_filename)} &bull; Periode: ${escHtml(p.period_month)}</div>
                        <div class="text-slate-600 mt-1">
                            Total Baris Valid: <strong class="text-emerald-700">${validCount}</strong> |
                            Total KG: <strong>${escHtml(p.total_wastage_kg)}</strong> |
                            Total Cover: <strong>${escHtml(p.total_cover)}</strong> |
                            Rasio: <strong>${escHtml(p.kg_per_cover)} KG/Cover</strong>
                        </div>
                        ${p.is_duplicate ? `<div class="text-amber-700 font-semibold mt-1">Peringatan: File ini pernah diimpor dengan kode ${escHtml(p.duplicate_code)}</div>` : ''}
                    `;

                    previewTbody.innerHTML = '';
                    (p.rows || []).forEach(r => {
                        const tr = document.createElement('tr');
                        tr.className = r.is_valid ? 'hover:bg-slate-50' : 'bg-rose-50 text-crimson-dark';
                        tr.innerHTML = `
                            <td class="p-1.5 border border-slate-200 font-mono-num">${escHtml(r.date)}</td>
                            <td class="p-1.5 border border-slate-200 text-right font-mono-num font-semibold">${escHtml(r.total_kg)}</td>
                            <td class="p-1.5 border border-slate-200 text-right font-mono-num">${escHtml(r.covers)}</td>
                            <td class="p-1.5 border border-slate-200 text-right font-mono-num">${escHtml(r.kg_per_cover)}</td>
                            <td class="p-1.5 border border-slate-200 text-center font-bold">${r.is_valid ? '&check;' : '&times;'}</td>
                        `;
                        previewTbody.appendChild(tr);
                    });

                    if (commitBtn) {
                        commitBtn.disabled = validCount <= 0;
                        commitBtn.classList.toggle('opacity-50', validCount <= 0);
                        commitBtn.classList.toggle('cursor-not-allowed', validCount <= 0);
                    }

                    previewArea.classList.remove('hidden');
                } catch (err) {
                    alert('Terjadi kesalahan jaringan atau server.');
                } finally {
                    previewBtn.disabled = false;
                    previewBtn.textContent = 'Preview File →';
                }
            });

            commitBtn?.addEventListener('click', async function () {
                if (!currentPreviewToken || commitBtn.disabled) return;
                commitBtn.disabled = true;
                commitBtn.textContent = 'Menyimpan...';

                try {
                    const res = await fetch("{{ route('wastage.commit') }}", {
                        method: 'POST',
                        body: JSON.stringify({ preview_token: currentPreviewToken }),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });

                    const data = await res.json();
                    if (!res.ok) {
                        alert(data.message || 'Gagal menyimpan laporan.');
                        return;
                    }

                    alert(data.message || 'Laporan berhasil disimpan!');
                    window.location.href = "{{ route('workspace.index', ['tab' => 'wastage']) }}&report_id=" + data.report.id;
                } catch (err) {
                    alert('Gagal menghubungi server.');
                } finally {
                    commitBtn.disabled = false;
                    commitBtn.textContent = 'Simpan ke Laporan ✓';
                }
            });
        })();
    </script>
@endif

{{-- [WASTAGE-REPORT] Modal Preview PDF Bulanan sebelum Download --}}
<div id="wastage-pdf-preview-modal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4" onclick="if (event.target === this && window.closeWastagePdfPreview) window.closeWastagePdfPreview();">
    <div class="bg-white rounded-2xl max-w-5xl w-full h-[88vh] border border-slate-200 shadow-xl overflow-hidden flex flex-col">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3 shrink-0">
            <div class="min-w-0">
                <h3 class="font-heading font-extrabold text-navy text-sm truncate">
                    {{ $isEn ? "Monthly Food Waste PDF Preview — {$selectedPeriodLabel}" : "Preview PDF Laporan Food Waste — {$selectedPeriodLabel}" }}
                </h3>
                <p class="text-xs text-slate-500">
                    {{ $isEn ? 'Review the generated PDF layout before downloading.' : 'Periksa hasil tampilan PDF terlebih dahulu sebelum mengunduh.' }}
                </p>
            </div>
            <button type="button" onclick="window.closeWastagePdfPreview && window.closeWastagePdfPreview()" class="text-slate-400 hover:text-navy text-xl leading-none px-1.5 py-0.5 rounded-lg hover:bg-slate-100 transition cursor-pointer" aria-label="Tutup Preview">&times;</button>
        </div>

        <div class="relative flex-1 bg-slate-50 overflow-hidden">
            <div id="wastage-pdf-preview-loading" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-xs text-slate-500 bg-slate-50 z-10">
                <span class="font-semibold text-navy">{{ $isEn ? 'Loading PDF preview...' : 'Memuat pratinjau PDF...' }}</span>
            </div>
            <iframe id="wastage-pdf-preview-frame" title="Preview PDF Food Waste" class="w-full h-full border-0"></iframe>
        </div>

        <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 shrink-0">
            <a href="{{ route('wastage.export.monthly.pdf', ['period' => $wastageSelectedPeriod, 'mode' => 'preview']) }}"
                target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-semibold text-slate-700 transition">
                <span>{{ $isEn ? 'Open in New Tab' : 'Buka di Tab Baru' }}</span>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.closeWastagePdfPreview && window.closeWastagePdfPreview()"
                    class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    {{ $isEn ? 'Close' : 'Tutup' }}
                </button>
                <a href="{{ route('wastage.export.monthly.pdf', ['period' => $wastageSelectedPeriod]) }}"
                    id="wastage-confirm-download-pdf-btn"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-crimson hover:bg-crimson-dark text-white text-xs font-semibold transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>{{ $isEn ? "Download PDF ({$selectedPeriodLabel})" : "Download PDF ({$selectedPeriodLabel})" }}</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('wastage-pdf-preview-modal');
        const frame = document.getElementById('wastage-pdf-preview-frame');
        const loading = document.getElementById('wastage-pdf-preview-loading');
        const triggerBtn = document.getElementById('wastage-export-monthly-pdf-btn');

        frame?.addEventListener('load', function () {
            if (frame.getAttribute('src')) {
                loading?.classList.add('hidden');
            }
        });

        window.openWastagePdfPreview = function () {
            if (!modal || !frame || !triggerBtn) return;
            const previewUrl = triggerBtn.getAttribute('data-preview-url');
            if (!previewUrl) return;
            if (frame.getAttribute('src') === previewUrl) {
                loading?.classList.add('hidden');
            } else {
                loading?.classList.remove('hidden');
                frame.setAttribute('src', previewUrl);
            }
            modal.classList.remove('hidden');
        };

        window.closeWastagePdfPreview = function () {
            if (!modal) return;
            loading?.classList.add('hidden');
            modal.classList.add('hidden');
        };

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                window.closeWastagePdfPreview();
            }
        });
    })();
</script>
