@php
    $isEn = $locale === 'en';
    $canImportPomec = $user->canModifyPomec();
    $canDeletePomec = $user->canDeletePomec();
    $pomecTypes = \App\Models\PomecRecord::TYPES;
    $field = 'w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal';
    $pomecCard = 'bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)]';
    $engName = $departments->firstWhere('code', 'ENG')?->localizedName($locale) ?? 'Engineering';
@endphp

{{-- Isi kartu, grafik, dan tabel dirender oleh workspace.js dari data bulan yang dipilih. --}}
<div id="pomec-root" class="space-y-5" data-types='@json($pomecTypes)' data-can-delete="{{ $canDeletePomec ? '1' : '0' }}">
    {{-- POMEC adalah halaman laporan Engineering: judul modul, departemen, dan status pembaruan harian. --}}
    <div class="{{ $pomecCard }} p-4 sm:p-5 space-y-3">
        <div class="space-y-1 min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-teal">{{ $engName }} &middot; {{ $isEn ? 'Utility Report' : 'Laporan Utilitas' }}</p>
            <h2 class="text-lg font-heading font-extrabold text-navy">POMEC Control</h2>
            <p class="text-xs text-slate-500 max-w-3xl">{{ $isEn
                ? 'Electricity, water, LPG, and genset diesel consumption and cost, compiled from the Engineering daily records and verified by the Head of Department.'
                : 'Konsumsi dan biaya listrik, air, gas LPG, serta solar genset, disusun dari pencatatan harian Engineering dan diverifikasi oleh Head of Department.' }}</p>
        </div>
        <div id="pomec-freshness" class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-3 border-t border-slate-100 text-[11px] text-slate-500"></div>
    </div>

    {{-- Navigasi di kiri; filter, lalu aksi utama di kanan. Kalau sempit, turun baris dan aksi tetap di kanan. --}}
    <div class="@container">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <div role="group" aria-label="{{ $isEn ? 'Utility' : 'Jenis utilitas' }}" class="flex gap-1 max-w-full overflow-x-auto p-1 rounded-xl bg-slate-100">
                @foreach (['all' => $isEn ? 'Overview' : 'Ringkasan'] + array_map(fn ($type) => $type[$isEn ? 'en' : 'id'], $pomecTypes) as $key => $label)
                    <button type="button" data-pomec-open="{{ $key }}" aria-pressed="false" class="pomec-tab px-2 sm:px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap text-slate-500 hover:text-navy aria-pressed:bg-teal aria-pressed:!text-white transition cursor-pointer">{{ $label }}</button>
                @endforeach
            </div>
            {{-- Ganti baris di layar sempit: tab di atas, filter dan aksi bersama di bawah. --}}
            <span aria-hidden="true" class="basis-full @min-[64rem]:hidden"></span>
            <select id="pomec-month-filter" aria-label="{{ $isEn ? 'Month' : 'Bulan' }}" class="{{ $field }} !w-auto font-semibold text-navy @min-[64rem]:ml-auto"></select>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 @max-[36rem]:order-last">
                <span id="pomec-view-label">{{ $isEn ? 'Chart by' : 'Grafik per' }}</span>
                <div role="group" aria-labelledby="pomec-view-label" class="flex gap-1 p-1 rounded-xl bg-slate-100">
                    @foreach (['day' => $isEn ? 'Day' : 'Hari', 'week' => $isEn ? 'Week' : 'Minggu', 'month' => $isEn ? 'Month' : 'Bulan'] as $key => $label)
                        <button type="button" data-pomec-view="{{ $key }}" aria-pressed="false" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-navy aria-pressed:bg-white aria-pressed:!text-navy aria-pressed:shadow-sm dark:aria-pressed:bg-[#2C374B] dark:aria-pressed:!text-white transition cursor-pointer">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @if ($canImportPomec)
                <span aria-hidden="true" class="hidden @min-[64rem]:block w-px h-6 bg-slate-200 dark:bg-white/10"></span>
                <button type="button" id="pomec-upload-toggle" aria-haspopup="dialog" class="ml-auto @min-[64rem]:ml-0 px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold whitespace-nowrap transition cursor-pointer">
                    {{ $isEn ? 'Upload File' : 'Unggah File' }}
                </button>
            @endif
        </div>
    </div>

    @if ($canImportPomec)
        {{-- <dialog> bawaan browser: fokus terkunci, Esc menutup, latar gelap. --}}
        <dialog id="pomec-upload-panel" aria-labelledby="pomec-upload-title" class="m-auto w-[calc(100%-2rem)] max-w-xl max-h-[90vh] p-0 rounded-2xl bg-white text-slate-900 border border-slate-200 shadow-2xl backdrop:bg-slate-900/50 open:flex flex-col overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 id="pomec-upload-title" class="text-base font-heading font-bold text-navy">{{ $isEn ? 'Upload Daily Data' : 'Unggah Data Harian' }}</h3>
                    <p id="pomec-upload-subtitle" class="text-xs text-slate-500">{{ $isEn ? 'Upload the same monthly Excel file every day. Dates already saved are skipped; only new dates are saved.' : 'Unggah file Excel bulanan yang sama setiap hari. Tanggal yang sudah tersimpan dilewati; hanya tanggal baru yang disimpan.' }}</p>
                </div>
                <button type="button" data-pomec-close aria-label="{{ $isEn ? 'Close' : 'Tutup' }}" class="p-1.5 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-100 transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4 overflow-y-auto">
                <form id="pomec-preview-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-xs font-semibold text-slate-700 space-y-1.5">
                        <span>{{ $isEn ? 'Utility' : 'Jenis utilitas' }}</span>
                        <select name="utility_type" id="pomec-utility-type" autofocus class="{{ $field }} font-normal">
                            @foreach ($pomecTypes as $key => $type)
                                <option value="{{ $key }}">{{ $type[$isEn ? 'en' : 'id'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs font-semibold text-slate-700 space-y-1.5">
                        <span>{{ $isEn ? 'Month in the file' : 'Bulan di file' }}</span>
                        <input type="month" name="period_month" id="pomec-period-month" value="{{ now()->subDay()->format('Y-m') }}" required class="{{ $field }} font-normal">
                    </label>
                    {{-- Input asli menutupi kotak (transparan), jadi klik, seret-lepas, dan keyboard tetap jalan. --}}
                    <div class="sm:col-span-2 relative flex items-center gap-3 rounded-xl border border-dashed border-slate-300 hover:border-teal focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/25 px-4 py-4 transition">
                        <span class="w-10 h-10 rounded-xl bg-cyan-50 text-teal flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span id="pomec-file-name" data-empty="{{ $isEn ? 'Choose a file or drop it here' : 'Pilih file atau seret ke sini' }}" class="block text-xs font-semibold text-navy truncate">{{ $isEn ? 'Choose a file or drop it here' : 'Pilih file atau seret ke sini' }}</span>
                            <span id="pomec-file-info" data-empty="{{ $isEn ? '.xlsx or .xls, up to 5 MB' : '.xlsx atau .xls, maksimal 5 MB' }}" class="block text-[11px] text-slate-500">{{ $isEn ? '.xlsx or .xls, up to 5 MB' : '.xlsx atau .xls, maksimal 5 MB' }}</span>
                        </span>
                        <span class="hidden sm:inline-block px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-navy shrink-0">{{ $isEn ? 'Browse' : 'Cari file' }}</span>
                        <input type="file" name="excel_file" id="pomec-file-input" accept=".xlsx,.xls" required aria-label="{{ $isEn ? 'Excel file' : 'File Excel' }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    </div>
                </form>

                <div id="pomec-hash-banner" role="status" class="hidden"></div>

                <div id="pomec-preview-box" class="hidden max-h-60 overflow-auto border border-slate-200 rounded-xl">
                    <table class="w-full min-w-[420px] text-left border-collapse text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 sticky top-0">
                            <tr>
                                <th class="py-2.5 px-4">{{ $isEn ? 'Date' : 'Tanggal' }}</th>
                                <th class="py-2.5 px-4">Meter</th>
                                <th class="py-2.5 px-4 text-right">{{ $isEn ? 'Usage' : 'Pemakaian' }}</th>
                                <th class="py-2.5 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="pomec-preview-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50">
                <p class="text-[11px] text-slate-500">{{ $isEn ? 'Nothing is saved until you confirm.' : 'Belum ada yang tersimpan sebelum dikonfirmasi.' }}</p>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" data-pomec-close class="py-2 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-navy text-xs font-semibold transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
                    <button type="submit" form="pomec-preview-form" id="pomec-preview-btn" data-busy="{{ $isEn ? 'Checking…' : 'Memeriksa…' }}" class="py-2 px-4 rounded-xl bg-teal hover:bg-teal-light text-white text-xs font-semibold transition cursor-pointer disabled:opacity-50">{{ $isEn ? 'Check File' : 'Periksa File' }}</button>
                    <button type="button" id="pomec-draft-btn" class="hidden py-2 px-4 rounded-xl border border-navy/30 bg-white text-navy hover:bg-navy/5 text-xs font-semibold transition cursor-pointer disabled:opacity-50">{{ $isEn ? 'Save as Draft' : 'Simpan Draft' }}</button>
                    <button type="button" id="pomec-commit-btn" class="hidden py-2 px-4 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer disabled:opacity-50"></button>
                </div>
            </div>
        </dialog>
    @endif

    {{-- Ringkasan semua utilitas (tampilan awal) --}}
    <div id="pomec-overview" class="space-y-4">
        <div id="pomec-cost-card" class="{{ $pomecCard }} p-5 space-y-4">
            <div>
                <h3 id="pomec-cost-title" class="text-xs font-semibold text-slate-500"></h3>
                <p id="pomec-month-cost" class="text-2xl sm:text-3xl font-heading font-extrabold font-mono-num text-navy"></p>
                <p id="pomec-cost-note" class="text-xs text-slate-500"></p>
            </div>
            <div class="relative h-56 w-full">
                <canvas id="pomec-chart-cost" role="img" aria-label="{{ $isEn ? 'Daily cost per utility' : 'Biaya harian per utilitas' }}"></canvas>
            </div>
            <p id="pomec-chart-hint" class="text-[11px] text-slate-500"></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($pomecTypes as $key => $type)
                <div class="{{ $pomecCard }} p-5 space-y-3 hover:border-teal transition" data-mini="{{ $key }}">
                    <button type="button" data-pomec-open="{{ $key }}" class="group block w-full text-left space-y-1 cursor-pointer">
                        <span class="flex items-center justify-between gap-3 text-xs font-semibold">
                            <span class="text-slate-500">{{ $type[$isEn ? 'en' : 'id'] }}</span>
                            <span class="text-teal group-hover:underline">{{ $isEn ? 'Details' : 'Rincian' }} &rarr;</span>
                        </span>
                        <span data-value class="block text-2xl font-heading font-extrabold font-mono-num text-navy">
                            <span data-total></span> <span class="text-xs font-normal text-slate-500">{{ $type['unit'] }}</span>
                        </span>
                        <span class="flex flex-wrap gap-x-3 min-h-4 text-[11px]">
                            <span data-sub class="text-slate-500"></span>
                            <span data-trend></span>
                        </span>
                    </button>
                    <div class="relative h-32 w-full">
                        <canvas id="pomec-mini-{{ $key }}" role="img" aria-label="{{ $isEn ? 'Daily usage' : 'Pemakaian harian' }} {{ $type[$isEn ? 'en' : 'id'] }}"></canvas>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div id="pomec-detail" class="hidden {{ $pomecCard }} overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="pomec-detail-title" class="text-sm font-heading font-bold text-navy"></h3>
                <p id="pomec-detail-note" class="text-xs text-slate-500"></p>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold">
                <span class="text-slate-500 font-normal">{{ $isEn ? 'Download' : 'Unduh' }}</span>
                <a id="pomec-export-excel" data-href="{{ route('pomec.export.excel', '__type__') }}" href="#" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy transition">Excel</a>
                <a id="pomec-export-pdf" data-href="{{ route('pomec.export.pdf', '__type__') }}" href="#" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy transition">PDF</a>
            </div>
        </div>

        <dl id="pomec-stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4 px-5 py-4 border-b border-slate-100"></dl>
        <div id="pomec-meters" class="hidden px-5 py-4 border-b border-slate-100 space-y-2.5"></div>

        <div class="p-5 border-b border-slate-100">
            <div class="relative h-60 w-full">
                <canvas id="pomec-chart-trend"></canvas>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="pomec-records-table" class="w-full min-w-[640px] text-left border-collapse text-xs">
                {{-- Kepala dan isi tabel dirender TanStack Table (workspace.js). --}}
                <thead class="bg-slate-50/90 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500"></thead>
                <tbody id="pomec-records-tbody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
        <div id="pomec-pagination"></div>
    </div>

    {{-- Satu unggahan = satu laporan Engineering: Draft → Submitted → Review → Returned / Reviewed. --}}
    <section class="{{ $pomecCard }} overflow-hidden" aria-labelledby="pomec-reports-title">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
            <div>
                <h3 id="pomec-reports-title" class="text-sm font-heading font-bold text-navy">
                    {{ $isEn ? 'POMEC Reports' : 'Laporan POMEC' }}
                    <span id="pomec-batch-count" class="ml-1 font-mono-num text-slate-500">{{ $pomecImports->count() }}</span>
                </h3>
                <p class="text-xs text-slate-500">{{ $isEn ? 'Each upload forms one report for Head of Department verification.' : 'Setiap unggahan menjadi satu laporan untuk diverifikasi Head of Department.' }}</p>
            </div>
            <div role="group" aria-label="Status" class="flex flex-wrap gap-1 p-1 rounded-xl bg-slate-100">
                @foreach ($isEn ? ['all' => 'All', 'draft' => 'Draft', 'submitted' => 'Awaiting HOD', 'in_review' => 'In review', 'returned' => 'Returned', 'reviewed' => 'Approved'] : ['all' => 'Semua', 'draft' => 'Draft', 'submitted' => 'Menunggu HOD', 'in_review' => 'Diperiksa', 'returned' => 'Dikembalikan', 'reviewed' => 'Disetujui'] as $key => $label)
                    <button type="button" data-pomec-status="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-navy aria-pressed:bg-white aria-pressed:!text-navy aria-pressed:shadow-sm dark:aria-pressed:bg-[#2C374B] dark:aria-pressed:!text-white transition cursor-pointer">{{ $label }} <span data-count class="font-normal opacity-70"></span></button>
                @endforeach
            </div>
        </div>
        {{-- relative: label sr-only (absolute) di kepala tabel tidak bocor keluar area gulir di HP. --}}
        <div class="relative overflow-x-auto">
            <table id="pomec-reports-table" class="w-full min-w-[860px] text-left border-collapse text-xs">
                <thead class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wider text-slate-500"></thead>
                <tbody id="pomec-imports-tbody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
        <div id="pomec-reports-pagination"></div>
    </section>
</div>
