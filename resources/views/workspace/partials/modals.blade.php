@php
    $isEn = $locale === 'en';
    $inputCls = 'w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal transition';
@endphp

<!-- Modal 1: Multi-Template Departmental Report Editor (Create / Edit) -->
<div id="modal-report-editor" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Clean Executive Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-white shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-teal/10 text-teal flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 id="editor-modal-title" class="text-base font-heading font-bold text-navy truncate">
                        {{ $isEn ? 'Create Departmental Report' : 'Buat Laporan Departemen' }}
                    </h3>
                    <p id="editor-template-subtitle" class="text-xs text-slate-500 truncate"></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-report-editor')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer shrink-0" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Scrollable Form Body -->
        <form id="report-editor-form" class="flex-1 overflow-y-auto p-6 space-y-5">
            <input type="hidden" id="editor-report-id" value="">
            <div id="editor-form-error" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-medium text-crimson-dark"></div>

            <!-- HOD Revision Feedback Banner (shown automatically when editing a Returned report) -->
            <div id="editor-revision-banner" class="hidden p-3.5 rounded-xl bg-amber-50/90 border border-amber-200 text-xs space-y-1">
                <div class="font-bold text-amber-900 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-crimson"></span>
                    <span>{{ $isEn ? 'Returned for Revision by HOD' : 'Dikembalikan untuk Revisi oleh HOD' }}</span>
                </div>
                <p id="editor-revision-note-text" class="text-amber-800 leading-relaxed"></p>
            </div>

            <!-- Section 1: Report Identity & Period -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        {{ $isEn ? 'Department Table Template' : 'Template Tabel Departemen' }} <span class="text-crimson">*</span>
                    </label>
                    <select id="editor-template-select" class="{{ $inputCls }}">
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">[{{ $tpl->department->code }}] {{ $tpl->localizedName($locale) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700">
                            {{ $isEn ? 'Report Title' : 'Judul Laporan' }} <span class="text-crimson">*</span>
                        </label>
                        <button type="button" onclick="autoFillEditorTitle(true)" class="text-[11px] font-semibold text-teal hover:text-navy transition cursor-pointer">
                            {{ $isEn ? 'Auto-fill' : 'Isi Otomatis' }}
                        </button>
                    </div>
                    <input id="editor-title" type="text" required maxlength="200" placeholder="{{ $isEn ? 'e.g. Daily Occupancy Report...' : 'Mis. Laporan Operasional Harian...' }}" class="{{ $inputCls }}">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        {{ $isEn ? 'Period' : 'Periode' }} <span class="text-crimson">*</span>
                    </label>
                    <select id="editor-period-type" class="{{ $inputCls }}">
                        <option value="daily">{{ $isEn ? 'Daily' : 'Harian' }}</option>
                        <option value="weekly">{{ $isEn ? 'Weekly' : 'Mingguan' }}</option>
                        <option value="monthly">{{ $isEn ? 'Monthly' : 'Bulanan' }}</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        {{ $isEn ? 'Date' : 'Tanggal' }} <span class="text-crimson">*</span>
                    </label>
                    <input id="editor-period-date" type="date" value="{{ date('Y-m-d') }}" required class="{{ $inputCls }} font-mono-num">
                </div>
            </div>

            <!-- Section 2: Interactive Department-Specific Table Builder -->
            <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white">
                <div class="px-4 py-3 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-navy">
                            {{ $isEn ? 'Department Data Table' : 'Tabel Data Departemen' }}
                        </span>
                        <span id="editor-row-count" class="px-2 py-0.5 rounded-full bg-navy/8 text-navy font-mono-num text-[10px] font-bold">
                            0 {{ $isEn ? 'rows' : 'baris' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="loadTemplateDefaultRows()" class="text-xs font-semibold px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer">
                            {{ $isEn ? 'Reset Sample' : 'Muat Contoh' }}
                        </button>
                        <button type="button" onclick="addEditorRow()" class="text-xs font-semibold px-3 py-1.5 rounded-xl bg-teal hover:bg-teal-light text-white transition cursor-pointer">
                            + {{ $isEn ? 'Add Row' : 'Tambah Baris' }}
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full min-w-[860px] text-left border-collapse text-xs" id="editor-dynamic-table">
                        <thead id="editor-table-head" class="bg-slate-50 sticky top-0 z-10 border-b border-slate-200 text-slate-600"></thead>
                        <tbody id="editor-table-body" class="divide-y divide-slate-100"></tbody>
                        <tfoot id="editor-table-foot" class="bg-slate-50 border-t border-slate-200 font-semibold text-navy"></tfoot>
                    </table>
                </div>
            </div>

            <!-- Section 3: Summary Notes & Supporting Attachment -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Operational Summary Notes' : 'Ringkasan / Catatan Operasional' }}</label>
                    <textarea id="editor-summary-notes" rows="3" maxlength="2000" placeholder="{{ $isEn ? 'Key highlights for HOD & Management...' : 'Poin penting untuk pemeriksaan HOD & Manajemen...' }}" class="{{ $inputCls }}"></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-700">{{ $isEn ? 'Supporting Attachment (Optional, max 5MB)' : 'Lampiran Pendukung (Opsional, maks 5MB)' }}</label>
                    <label for="editor-attachment" class="flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl border-2 border-dashed border-slate-200 hover:border-teal bg-slate-50/50 hover:bg-teal/5 transition cursor-pointer">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-teal/10 text-teal flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            </span>
                            <div class="min-w-0">
                                <div id="editor-attachment-label" class="text-xs font-semibold text-navy truncate">
                                    {{ $isEn ? 'Click to choose PDF, Excel, CSV, or Image' : 'Klik untuk pilih PDF, Excel, CSV, atau Gambar' }}
                                </div>
                                <div class="text-[11px] text-slate-400">PDF, XLSX, XLS, CSV, PNG, JPG • Max 5 MB</div>
                            </div>
                        </div>
                        <button type="button" id="editor-attachment-clear" onclick="clearEditorAttachment(event)" class="hidden shrink-0 px-2 py-1 rounded-lg bg-rose-50 text-crimson-dark text-[11px] font-semibold hover:bg-crimson hover:text-white transition cursor-pointer">
                            {{ $isEn ? 'Clear' : 'Hapus' }}
                        </button>
                    </label>
                    <input id="editor-attachment" type="file" accept=".pdf,.xlsx,.xls,.csv,.png,.jpg,.jpeg" class="hidden">
                    <div id="editor-existing-attachment" class="hidden text-xs text-teal font-medium"></div>
                </div>
            </div>
        </form>

        <!-- Sticky Footer -->
        <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 shrink-0">
            <div class="text-[11px] text-slate-400 hidden sm:block">
                {{ $isEn ? 'Stage 1: Draft (editable) • Stage 2: Submit (locked for HOD Review)' : 'Tahap 1: Draft (bisa diubah) • Tahap 2: Submit (masuk pemeriksaan HOD)' }}
            </div>
            <div class="flex flex-wrap items-center gap-2 ml-auto">
                <button type="button" onclick="closeModal('modal-report-editor')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    {{ $isEn ? 'Cancel' : 'Batal' }}
                </button>
                <button type="button" id="editor-save-draft-btn" onclick="submitReportForm('draft')" class="px-4 py-2 rounded-xl border border-navy/30 bg-white text-navy hover:bg-navy/5 text-xs font-semibold transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ $isEn ? 'Save as Draft' : 'Simpan Draft' }}
                </button>
                <button type="button" id="editor-submit-btn" onclick="submitReportForm('submit')" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold shadow-xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ $isEn ? 'Submit for HOD Review' : 'Kirim untuk Review HOD' }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Report Detail, Workflow History & HOD Review -->
<div id="modal-report-detail" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-white shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div id="detail-code-badge" class="text-[11px] font-mono-num font-semibold text-teal truncate"></div>
                    <h3 id="detail-title" class="text-base font-heading font-bold text-navy truncate"></h3>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-report-detail')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer shrink-0" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div id="detail-body" class="flex-1 overflow-y-auto p-6 space-y-5 text-sm"></div>

        <div id="detail-footer" class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 shrink-0"></div>
    </div>
</div>

<!-- Modal 3: Confirm Delete Draft Report -->
<div id="modal-delete-report" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-md overflow-hidden">
        <div class="p-6 space-y-4">
            <input type="hidden" id="delete-report-id">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200 text-crimson flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div class="space-y-1 min-w-0">
                    <h3 class="text-base font-heading font-bold text-navy">
                        {{ $isEn ? 'Delete Draft Report' : 'Hapus Draft Laporan' }}
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $isEn ? 'Are you sure you want to permanently delete draft' : 'Apakah Anda yakin ingin menghapus secara permanen draft' }}
                        <strong id="delete-report-label" class="text-navy font-mono-num"></strong>?
                    </p>
                </div>
            </div>
        </div>
        <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" onclick="closeModal('modal-delete-report')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                {{ $isEn ? 'Cancel' : 'Batal' }}
            </button>
            <button type="button" onclick="confirmDeleteDraft()" class="px-4 py-2 rounded-xl bg-crimson hover:bg-crimson-dark text-white text-xs font-semibold shadow-xs transition cursor-pointer">
                {{ $isEn ? 'Delete Draft' : 'Hapus Draft' }}
            </button>
        </div>
    </div>
</div>

<!-- Modal 4: Self-Service Change Password -->
<div id="modal-change-password" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <h3 class="text-sm font-heading font-bold text-navy">{{ $isEn ? 'Change Account Password' : 'Ubah Kata Sandi Akun' }}</h3>
            </div>
            <button type="button" onclick="closeModal('modal-change-password')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="change-password-form">
            <div class="p-6 space-y-3.5 text-xs">
                <div id="cp-form-error" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 font-medium text-crimson-dark"></div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Current Password' : 'Kata Sandi Saat Ini' }}</label>
                    <input id="cp-current" type="password" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'New Password (min 8, upper/lower & number)' : 'Kata Sandi Baru (min 8, huruf besar/kecil & angka)' }}</label>
                    <input id="cp-new" type="password" required minlength="8" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Confirm New Password' : 'Konfirmasi Kata Sandi Baru' }}</label>
                    <input id="cp-confirm" type="password" required minlength="8" class="{{ $inputCls }}">
                </div>
            </div>
            <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-change-password')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    {{ $isEn ? 'Cancel' : 'Batal' }}
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                    {{ $isEn ? 'Update Password' : 'Simpan Kata Sandi' }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 5: Edit Topology Component (IT Admin) -->
<div id="modal-topology-edit" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-teal/10 text-teal flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </div>
                <h3 id="topo-modal-title" class="text-sm font-heading font-bold text-navy truncate">{{ $isEn ? 'Update Topology Component' : 'Perbarui Komponen Topologi' }}</h3>
            </div>
            <button type="button" onclick="closeModal('modal-topology-edit')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="topology-edit-form">
            <input type="hidden" id="topo-id">
            <div class="p-6 space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Status</label>
                    <select id="topo-status" class="{{ $inputCls }}">
                        <option value="healthy">HEALTHY</option>
                        <option value="warning">WARNING</option>
                        <option value="degraded">DEGRADED</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">GM Summary (ID)</label>
                    <input id="topo-gm-id" type="text" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">GM Summary (EN)</label>
                    <input id="topo-gm-en" type="text" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">IT Technical Detail</label>
                    <input id="topo-it-detail" type="text" required class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Host / Endpoint</label>
                    <input id="topo-host" type="text" class="{{ $inputCls }} font-mono-num">
                </div>
            </div>
            <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-topology-edit')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">{{ $isEn ? 'Save' : 'Simpan' }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 6: Add / Edit User & Cross-Department RBAC (IT Admin) -->
<div id="modal-user-form" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 id="user-modal-title" class="text-sm font-heading font-bold text-navy truncate">{{ $isEn ? 'Manage User & Permissions' : 'Kelola Pengguna & Hak Akses' }}</h3>
            </div>
            <button type="button" onclick="closeModal('modal-user-form')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="user-crud-form" class="flex-1 flex flex-col min-h-0">
            <input type="hidden" id="user-edit-id">
            <div class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
                <div id="user-form-error" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 font-medium text-crimson-dark"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Full Name' : 'Nama Lengkap' }}</label>
                        <input id="user-name" type="text" required class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Email</label>
                        <input id="user-email" type="email" required class="{{ $inputCls }}">
                    </div>
                </div>

                <div id="user-password-wrap">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-semibold text-slate-700">Password (min 8, A-Z, 0-9)</label>
                        <button type="button" onclick="generateStrongPassword('user-password')" class="text-[11px] font-semibold text-teal hover:text-navy cursor-pointer">
                            {{ $isEn ? 'Generate Strong' : 'Buat Otomatis' }}
                        </button>
                    </div>
                    <input id="user-password" type="text" minlength="8" autocomplete="new-password" class="{{ $inputCls }} font-mono-num">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Role</label>
                        <select id="user-role" class="{{ $inputCls }}">
                            <option value="staff">Admin / Staff</option>
                            <option value="hod">Head of Department (HOD)</option>
                            <option value="gm">General Manager (GM)</option>
                            <option value="it_admin">IT Administrator</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">{{ $isEn ? 'Primary Department' : 'Departemen Utama' }}</label>
                        <select id="user-dept-id" class="{{ $inputCls }}">
                            <option value="">{{ $isEn ? '-- None (All Depts for GM) --' : '-- Tidak Ada (Semua Dept untuk GM) --' }}</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->localizedName($locale) }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 pt-1">
                    <div id="user-active-wrap" class="hidden">
                        <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="user-active" checked class="rounded border-slate-300 text-teal focus:ring-teal">
                            <span>{{ $isEn ? 'Account Active' : 'Akun Aktif' }}</span>
                        </label>
                    </div>
                    <div id="user-readonly-wrap">
                        <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="user-read-only" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>{{ $isEn ? 'Read-Only on Primary Dept (View/Export only)' : 'Mode Read-Only di Dept. Utama (Hanya Lihat/Export)' }}</span>
                        </label>
                    </div>
                </div>

                <!-- Cross-Department Access Delegation Matrix (for HOD / Staff) -->
                <div id="user-extra-perms-section" class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/40">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="font-heading font-bold text-navy">
                                {{ $isEn ? 'Cross-Department Access Delegation' : 'Delegasi Akses Lintas Departemen Tambahan' }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ $isEn ? 'Allow HOD/Staff to Read-Only, Input, or Review other departments.' : 'Atur izin Read-Only, Input Laporan, atau Review HOD pada departemen lain.' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="presetExtraPerms('read')" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-[11px] font-semibold text-navy transition cursor-pointer">
                                {{ $isEn ? 'All Read-Only' : 'Semua Read-Only' }}
                            </button>
                            <button type="button" onclick="presetExtraPerms('')" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-rose-50 text-[11px] font-semibold text-crimson-dark transition cursor-pointer">
                                {{ $isEn ? 'Clear Extra' : 'Reset' }}
                            </button>
                        </div>
                    </div>
                    <div class="divide-y divide-slate-100 bg-white max-h-60 overflow-y-auto">
                        @foreach ($departments as $dept)
                            <div class="px-4 py-2.5 flex items-center justify-between gap-3 user-extra-perm-row" data-dept-id="{{ $dept->id }}">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="px-2 py-0.5 rounded bg-navy/8 text-navy font-mono-num text-[10px] font-bold shrink-0">{{ $dept->code }}</span>
                                    <span class="font-medium text-slate-700 truncate">{{ $dept->localizedName($locale) }}</span>
                                    <span class="user-primary-pill hidden text-[10px] px-1.5 py-0.5 rounded bg-teal/10 text-teal font-semibold shrink-0">{{ $isEn ? 'Primary' : 'Utama' }}</span>
                                </div>
                                <select data-dept-id="{{ $dept->id }}" class="user-extra-perm-select w-48 px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50/70 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25">
                                    <option value="">{{ $isEn ? 'No Access' : 'Tidak Ada Akses' }}</option>
                                    <option value="read">{{ $isEn ? 'Read-Only (View & Export)' : 'Read-Only (Lihat & Export)' }}</option>
                                    <option value="input">{{ $isEn ? 'Input & Edit Reports' : 'Input & Ubah Laporan' }}</option>
                                    <option value="review">{{ $isEn ? 'Full HOD (Input + Review)' : 'Penuh HOD (Input + Review)' }}</option>
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div id="user-all-scope-notice" class="hidden p-3.5 rounded-xl bg-amber-50/70 border border-amber-200 text-[11px] text-amber-900">
                    {{ $isEn
                        ? 'GM automatically gets Read-Only results across all 8 departments. IT Administrator automatically gets full access to all departments and system controls.'
                        : 'Role GM otomatis memiliki akses lihat hasil seluruh 8 departemen. Role IT Administrator otomatis memegang kendali penuh seluruh departemen & sistem.' }}
                </div>
            </div>
            <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2 shrink-0">
                <button type="button" onclick="closeModal('modal-user-form')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">{{ $isEn ? 'Save' : 'Simpan' }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 7: Admin Reset User Password -->
<div id="modal-admin-reset-password" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h3 id="arp-title" class="text-sm font-heading font-bold text-navy truncate">Reset Password</h3>
            </div>
            <button type="button" onclick="closeModal('modal-admin-reset-password')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-navy hover:bg-slate-100 flex items-center justify-center transition cursor-pointer" aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="admin-reset-password-form">
            <input type="hidden" id="arp-user-id">
            <div class="p-6 space-y-3 text-xs">
                <div id="arp-form-error" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 font-medium text-crimson-dark"></div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-semibold text-slate-700">{{ $isEn ? 'New Password (min 8, A-Z, 0-9)' : 'Password Baru (min 8, A-Z, 0-9)' }}</label>
                        <button type="button" onclick="generateStrongPassword('arp-password')" class="text-[11px] font-semibold text-teal hover:text-navy cursor-pointer">
                            {{ $isEn ? 'Generate Strong' : 'Buat Otomatis' }}
                        </button>
                    </div>
                    <input id="arp-password" type="text" required minlength="8" autocomplete="new-password" class="{{ $inputCls }} font-mono-num">
                </div>
            </div>
            <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-admin-reset-password')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">Reset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 8: Confirm Delete User -->
<div id="modal-delete-user" data-backdrop-close="true" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="modal-panel bg-white rounded-2xl border border-slate-200/90 shadow-[0_24px_60px_rgba(15,41,66,0.22)] w-full max-w-md overflow-hidden">
        <div class="p-6 space-y-4">
            <input type="hidden" id="delete-user-id">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200 text-crimson flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <div class="space-y-1 min-w-0">
                    <h3 class="text-base font-heading font-bold text-navy">{{ $isEn ? 'Delete User Account' : 'Hapus Akun Pengguna' }}</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $isEn ? 'Are you sure you want to permanently remove account' : 'Apakah Anda yakin ingin menghapus permanen akun' }}
                        <strong id="delete-user-label" class="text-navy font-mono-num"></strong>?
                    </p>
                </div>
            </div>
        </div>
        <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" onclick="closeModal('modal-delete-user')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">{{ $isEn ? 'Cancel' : 'Batal' }}</button>
            <button type="button" onclick="confirmDeleteUser()" class="px-4 py-2 rounded-xl bg-crimson hover:bg-crimson-dark text-white text-xs font-semibold transition cursor-pointer">{{ $isEn ? 'Delete User' : 'Hapus Akun' }}</button>
        </div>
    </div>
</div>
