@php
    $isEn = $locale === 'en';
    $canImportPomec = $user->canModifyPomec();
    $elecSum = max(1, $electricityTotal);
    $lwbpPct = $electricityTotal > 0 ? round(($electricityLwbp / $elecSum) * 100) : 0;
    $wbpPct = $electricityTotal > 0 ? 100 - $lwbpPct : 0;
@endphp

<div class="space-y-5">
    <!-- Row 1: Executive POMEC Utility Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Electricity LWBP / WBP Breakdown -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Electricity Total (LWBP + WBP)' : 'Total Listrik (LWBP + WBP)' }}</div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy mt-1">
                        <span id="pomec-stat-elec-total">{{ number_format($electricityTotal, 0, ',', '.') }}</span>
                        <span class="text-xs font-normal text-slate-500">kWh</span>
                    </div>
                </div>
                <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 border border-amber-200/70 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <div class="space-y-1.5">
                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden flex">
                    <div id="pomec-stat-elec-lwbp-bar" class="h-full bg-navy" style="width: {{ $lwbpPct }}%"></div>
                    <div id="pomec-stat-elec-wbp-bar" class="h-full bg-amber-500" style="width: {{ $wbpPct }}%"></div>
                </div>
                <div id="pomec-stat-elec-split" class="flex items-center justify-between text-[11px] font-mono-num text-slate-600">
                    <span>LWBP: {{ number_format($electricityLwbp, 0, ',', '.') }} ({{ $lwbpPct }}%)</span>
                    <span>WBP: {{ number_format($electricityWbp, 0, ',', '.') }} ({{ $wbpPct }}%)</span>
                </div>
            </div>
        </div>

        <!-- Water Usage -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-3">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Water Consumption' : 'Total Pemakaian Air' }}</div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-teal mt-1">
                        <span id="pomec-stat-water-total">{{ number_format($waterTotal, 1, ',', '.') }}</span>
                        <span class="text-xs font-normal text-slate-500">m³</span>
                    </div>
                </div>
                <span class="w-9 h-9 rounded-xl bg-cyan-50 text-teal border border-cyan-200/70 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"/></svg>
                </span>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between pt-1">
                <span>{{ $isEn ? 'Meter Readings' : 'Pembacaan Meter' }}</span>
                <span id="pomec-stat-water-count" class="font-mono-num font-semibold text-navy">{{ $pomecRecords->where('utility_type', 'water')->count() }} {{ $isEn ? 'records' : 'catatan' }}</span>
            </div>
        </div>

        <!-- Verified Import Batches + Quick Export -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] flex flex-col justify-between space-y-3">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'SHA-256 Verified Batches' : 'Batch Impor Terverifikasi' }}</div>
                    <div class="text-2xl font-heading font-extrabold font-mono-num text-navy mt-1" id="pomec-batch-count">
                        {{ $pomecImports->count() }}
                    </div>
                </div>
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200/70 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('pomec.export.excel', 'electricity') }}" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-navy transition">
                    {{ $isEn ? 'XLSX Elec' : 'XLSX Listrik' }}
                </a>
                <a href="{{ route('pomec.export.pdf', 'electricity') }}" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-rose-50 text-crimson-dark transition">
                    {{ $isEn ? 'PDF Elec' : 'PDF Listrik' }}
                </a>
                <a href="{{ route('pomec.export.excel', 'water') }}" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-teal transition">
                    {{ $isEn ? 'XLSX Water' : 'XLSX Air' }}
                </a>
                <a href="{{ route('pomec.export.pdf', 'water') }}" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-rose-50 text-crimson-dark transition">
                    {{ $isEn ? 'PDF Water' : 'PDF Air' }}
                </a>
            </div>
        </div>
    </div>

    <!-- Row 2: Import & SHA-256 Validation Panel (Full Width for Clean Preview) -->
    @if ($canImportPomec)
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-heading font-bold text-navy">
                        {{ $isEn ? 'Import POMEC Excel (SHA-256 Protected)' : 'Impor Excel POMEC (Proteksi Duplikasi SHA-256)' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $isEn ? 'Upload -> Automatic SHA-256 Preview -> Commit Valid Rows' : 'Unggah File -> Preview & Cek Duplikasi SHA-256 -> Simpan Baris Valid' }}
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('pomec.template', 'electricity') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 font-semibold text-navy transition">
                        {{ $isEn ? 'Download Elec Sample' : 'Template Listrik (.xlsx)' }}
                    </a>
                    <a href="{{ route('pomec.template', 'water') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 font-semibold text-teal transition">
                        {{ $isEn ? 'Download Water Sample' : 'Template Air (.xlsx)' }}
                    </a>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <form id="pomec-preview-form" class="lg:col-span-5 space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Utility' : 'Utilitas' }}</label>
                            <select name="utility_type" id="pomec-utility-type" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                                <option value="electricity">{{ $isEn ? 'Electricity (LWBP/WBP)' : 'Listrik (LWBP/WBP)' }}</option>
                                <option value="water">{{ $isEn ? 'Water (m³)' : 'Air (m³)' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Month' : 'Periode' }}</label>
                            <input type="month" name="period_month" id="pomec-period-month" value="{{ date('Y-m') }}" required
                                class="w-full px-3 py-2 text-xs font-mono-num rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                        </div>
                    </div>

                    <label for="pomec-file-input" class="flex flex-col items-center justify-center gap-1.5 px-4 py-5 rounded-xl border-2 border-dashed border-slate-200 hover:border-teal bg-slate-50/60 hover:bg-teal/5 text-center transition cursor-pointer">
                        <svg class="w-6 h-6 text-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <span id="pomec-file-label" class="text-xs font-semibold text-navy">
                            {{ $isEn ? 'Choose .xlsx or .csv file' : 'Pilih file .xlsx atau .csv' }}
                        </span>
                        <span class="text-[11px] text-slate-400">
                            {{ $isEn ? 'Max 5 MB • Auto SHA-256 deduplication' : 'Maks 5 MB • Cek duplikasi SHA-256 otomatis' }}
                        </span>
                        <input type="file" name="excel_file" id="pomec-file-input" accept=".xlsx,.xls,.csv" required class="hidden">
                    </label>

                    <button type="submit" id="pomec-preview-btn" class="w-full py-2.5 px-4 rounded-xl bg-teal hover:bg-teal-light text-white text-xs font-semibold transition cursor-pointer">
                        {{ $isEn ? 'Upload & Preview Validation' : 'Unggah & Preview Validasi' }}
                    </button>
                </form>

                <!-- Preview Result Box -->
                <div id="pomec-preview-box" class="hidden lg:col-span-7 space-y-3">
                    <div id="pomec-hash-banner" class="p-3.5 rounded-xl border text-xs space-y-1"></div>
                    <div class="max-h-56 overflow-auto border border-slate-200 rounded-xl bg-white">
                        <table class="w-full min-w-[540px] text-left border-collapse text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 sticky top-0">
                                <tr>
                                    <th class="py-2.5 px-4 whitespace-nowrap">{{ $isEn ? 'Date' : 'Tanggal' }}</th>
                                    <th class="py-2.5 px-4">Meter</th>
                                    <th class="py-2.5 px-4 text-right whitespace-nowrap">Total</th>
                                    <th class="py-2.5 px-4 whitespace-nowrap">Status</th>
                                </tr>
                            </thead>
                            <tbody id="pomec-preview-rows" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                    <button type="button" id="pomec-commit-btn" class="w-full py-2.5 px-4 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ $isEn ? 'Commit Valid Rows to Database' : 'Simpan Data Valid ke Database' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Row 3: Full-Width Utility Usage Log & Daily Trend -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Utility Usage Log & Daily Trend' : 'Log Pemakaian Utilitas & Tren Harian' }}
                </h3>
                <p class="text-xs text-slate-500">
                    {{ $isEn ? 'Verified electricity (LWBP/WBP) and water meter readings.' : 'Catatan pembacaan meter listrik (LWBP/WBP) dan air terverifikasi.' }}
                </p>
            </div>

            <!-- Segmented Filter + Month + Search -->
            @php
                $pomecMonths = $pomecImports->pluck('period_month')->filter()->unique()->sortDesc()->values();
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-48">
                    <input id="pomec-search" type="text" placeholder="{{ $isEn ? 'Search date, meter, note...' : 'Cari tanggal, meter, catatan...' }}"
                        class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50/60 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                </div>
                <select id="pomec-month-filter" class="px-3 py-1.5 text-xs font-mono-num rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Months' : 'Semua Bulan' }}</option>
                    @foreach ($pomecMonths as $pm)
                        <option value="{{ $pm }}">{{ $pm }}</option>
                    @endforeach
                </select>
                <div class="inline-flex rounded-xl bg-slate-100 p-0.5 border border-slate-200 text-xs font-semibold">
                    <button type="button" data-type="all" class="pomec-filter-btn px-3 py-1 rounded-lg bg-navy text-white transition cursor-pointer">{{ $isEn ? 'All' : 'Semua' }}</button>
                    <button type="button" data-type="electricity" class="pomec-filter-btn px-3 py-1 rounded-lg text-slate-600 hover:text-navy transition cursor-pointer">{{ $isEn ? 'Electricity' : 'Listrik' }}</button>
                    <button type="button" data-type="water" class="pomec-filter-btn px-3 py-1 rounded-lg text-slate-600 hover:text-navy transition cursor-pointer">{{ $isEn ? 'Water' : 'Air' }}</button>
                </div>
                <span id="pomec-record-count" class="px-2.5 py-1 rounded-lg bg-slate-100 text-navy font-mono-num text-[11px] font-semibold">
                    {{ $pomecRecords->count() }}/{{ $pomecRecords->count() }}
                </span>
                <button type="button" id="pomec-reset-btn" onclick="resetPomecFilters()" class="hidden px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                    Reset
                </button>
            </div>
        </div>

        <!-- Interactive POMEC Charts (Follows Filter & Commit) -->
        <div class="p-5 bg-slate-50/40 border-b border-slate-100 grid grid-cols-1 lg:grid-cols-12 gap-4">
            <div class="lg:col-span-8 bg-white rounded-xl border border-slate-200/80 p-4 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-xs font-bold text-navy">{{ $isEn ? 'Daily Electricity (LWBP / WBP) & Water Trend' : 'Tren Harian Listrik (LWBP / WBP) & Air PDAM' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $isEn ? 'Dual-axis chart following active filters' : 'Grafik dual-axis mengikuti filter aktif' }}</div>
                    </div>
                    <span id="pomec-chart-range" class="text-[10px] font-mono-num text-slate-400">-</span>
                </div>
                <div class="relative h-56 w-full">
                    <canvas id="pomec-chart-trend"></canvas>
                </div>
            </div>

            <div class="lg:col-span-4 bg-white rounded-xl border border-slate-200/80 p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs font-bold text-navy">{{ $isEn ? 'LWBP vs WBP Share' : 'Proporsi Tarif LWBP vs WBP' }}</div>
                    <span class="text-[10px] font-mono-num text-slate-500" id="pomec-chart-elec-total">0 kWh</span>
                </div>
                <div class="relative h-40 w-full flex items-center justify-center">
                    <canvas id="pomec-chart-split"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span id="pomec-chart-split-center" class="text-lg font-heading font-extrabold font-mono-num text-navy leading-none">0%</span>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">LWBP</span>
                    </div>
                </div>
                <div id="pomec-chart-split-legend" class="grid grid-cols-2 gap-2 text-[11px] font-mono-num pt-1 border-t border-slate-100"></div>
            </div>
        </div>

        <!-- Full-Width Spacious Records Table -->
        <div class="overflow-x-auto">
            <table id="pomec-records-table" class="w-full min-w-[860px] text-left border-collapse text-xs">
                <thead class="bg-slate-50/90 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="reading_date">{{ $isEn ? 'Date' : 'Tanggal' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="utility_type">{{ $isEn ? 'Type' : 'Jenis' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="meter_name">Meter</th>
                        <th class="py-3.5 px-5 text-right">LWBP / WBP / m³</th>
                        <th class="py-3.5 px-5 text-right cursor-pointer select-none hover:text-navy transition" data-sort-key="total_usage">Total</th>
                        <th class="py-3.5 px-5">{{ $isEn ? 'Notes' : 'Catatan' }}</th>
                    </tr>
                </thead>
                <tbody id="pomec-records-tbody" class="divide-y divide-slate-100">
                    @forelse ($pomecRecords as $rec)
                        @php
                            $isElec = $rec->utility_type === 'electricity';
                            $waterVal = (float) $rec->water_m3;
                            $waterDecimals = fmod($waterVal, 1.0) !== 0.0 ? 2 : 0;
                            $totalVal = (float) $rec->total_usage;
                            $totalDecimals = fmod($totalVal, 1.0) !== 0.0 ? 2 : 0;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 font-mono-num text-slate-600 whitespace-nowrap">{{ $rec->reading_date->copy()->timezone('Asia/Makassar')->format('d M Y') }}</td>
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $isElec ? 'bg-amber-50 text-amber-700 border border-amber-200/70' : 'bg-cyan-50 text-teal border border-cyan-200/70' }}">
                                    {{ strtoupper($rec->utility_type) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-5 font-semibold text-navy whitespace-nowrap">{{ $rec->meter_name }}</td>
                            <td class="py-3.5 px-5 text-right font-mono-num text-slate-600 whitespace-nowrap">
                                {{ $isElec ? number_format((float) $rec->lwbp_value, 0, ',', '.').' / '.number_format((float) $rec->wbp_value, 0, ',', '.') : number_format($waterVal, $waterDecimals, ',', '.').' m³' }}
                            </td>
                            <td class="py-3.5 px-5 text-right font-mono-num font-bold text-navy whitespace-nowrap">
                                {{ number_format($totalVal, $totalDecimals, ',', '.') }} {{ $isElec ? 'kWh' : 'm³' }}
                            </td>
                            <td class="py-3.5 px-5 text-slate-500 min-w-[180px]">{{ $rec->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">{{ $isEn ? 'No utility records match your filter.' : 'Tidak ada data utilitas yang cocok.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="pomec-pagination"></div>
    </div>

    <!-- Row 4: Full-Width Import Batch & SHA-256 Fingerprint History -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Import Batch History & SHA-256 Audit' : 'Riwayat Batch Impor & Sidik Jari SHA-256' }}
                </h3>
                <p class="text-xs text-slate-500">
                    {{ $isEn ? 'Each uploaded Excel file is fingerprinted with SHA-256 to prevent duplicate commits.' : 'Setiap file Excel yang diimpor dicatat hash SHA-256 untuk mencegah duplikasi data.' }}
                </p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-5">Batch Code</th>
                        <th class="py-3.5 px-5">{{ $isEn ? 'Utility & Period' : 'Utilitas & Periode' }}</th>
                        <th class="py-3.5 px-5">{{ $isEn ? 'Original File' : 'Nama File' }}</th>
                        <th class="py-3.5 px-5">SHA-256 Fingerprint</th>
                        <th class="py-3.5 px-5 text-right">{{ $isEn ? 'Rows / Total' : 'Baris / Total' }}</th>
                        <th class="py-3.5 px-5">{{ $isEn ? 'Uploaded By (WITA)' : 'Pengunggah (WITA)' }}</th>
                    </tr>
                </thead>
                <tbody id="pomec-imports-tbody" class="divide-y divide-slate-100">
                    @forelse ($pomecImports as $imp)
                        @php
                            $isElec = $imp->utility_type === 'electricity';
                            $unit = $isElec ? 'kWh' : 'm³';
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 font-mono-num font-semibold text-navy whitespace-nowrap">{{ $imp->batch_code }}</td>
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $isElec ? 'bg-amber-50 text-amber-700' : 'bg-cyan-50 text-teal' }}">{{ strtoupper($imp->utility_type) }}</span>
                                <span class="font-mono-num text-slate-500 ml-1.5">{{ $imp->period_month }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-slate-700 min-w-[200px]">{{ $imp->original_filename }}</td>
                            <td class="py-3.5 px-5 font-mono-num text-[11px] text-slate-500 whitespace-nowrap">{{ substr($imp->file_hash_sha256 ?? '', 0, 24) }}...</td>
                            <td class="py-3.5 px-5 text-right font-mono-num font-semibold text-navy whitespace-nowrap">{{ $imp->total_rows }} {{ $isEn ? 'rows' : 'baris' }} &bull; {{ number_format((float) $imp->total_usage, 0, ',', '.') }} {{ $unit }}</td>
                            <td class="py-3.5 px-5 text-slate-600 whitespace-nowrap">
                                <div>{{ $imp->uploader?->name ?? '-' }}</div>
                                <div class="text-[10px] font-mono-num text-slate-400 mt-0.5">{{ $imp->committed_at ? $imp->committed_at->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA' : '-' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">{{ $isEn ? 'No POMEC import batches yet.' : 'Belum ada riwayat batch impor POMEC.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="pomec-pagination"></div>
    </div>
</div>
