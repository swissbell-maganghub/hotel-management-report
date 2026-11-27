import Chart from 'chart.js/auto';
import {
    constructTable,
    createPaginatedRowModel,
    createSortedRowModel,
    rowPaginationFeature,
    rowSortingFeature,
    sortFns,
    tableFeatures,
} from '@tanstack/table-core';
import { storeReactivityBindings } from '@tanstack/table-core/store-reactivity-bindings';

// TanStack Table (headless): mengurus urutan dan halaman; markup tabel tetap milik kita.
const DATA_TABLE_FEATURES = tableFeatures({
    coreReactivityFeature: storeReactivityBindings(),
    rowSortingFeature,
    rowPaginationFeature,
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    sortFns,
});

const I18N = {
    id: {
        rows: 'baris',
        review: 'Periksa',
        view: 'Detail',
        edit: 'Ubah',
        del: 'Hapus',
        createReport: 'Buat Laporan Departemen',
        editReport: 'Ubah Laporan',
        minOneRow: 'Minimal harus ada 1 baris data.',
        titleRequired: 'Judul laporan wajib diisi.',
        saveFailed: 'Gagal menyimpan data.',
        networkError: 'Terjadi kesalahan jaringan.',
        department: 'Departemen',
        period: 'Periode',
        author: 'Penyusun',
        summaryNotes: 'Catatan Operasional',
        reviewNotes: 'Catatan Review HOD',
        downloadAttachment: 'Unduh Lampiran',
        workflowHistory: 'Histori Alur Laporan',
        hodVerification: 'Pemeriksaan & Verifikasi HOD',
        reviewPlaceholder: 'Tambahkan catatan pemeriksaan atau instruksi revisi...',
        markReviewed: 'Tandai Reviewed',
        returnRevision: 'Kembalikan untuk Revisi',
        close: 'Tutup',
        checkingData: 'Sedang mengecek data terkontrol...',
        assistantUnavailable: 'Layanan asisten sementara tidak tersedia.',
        editUser: 'Ubah Pengguna',
        addUser: 'Tambah Pengguna Baru',
        total: 'Total',
    },
    en: {
        rows: 'rows',
        review: 'Review',
        view: 'View',
        edit: 'Edit',
        del: 'Del',
        createReport: 'Create Departmental Report',
        editReport: 'Edit Report',
        minOneRow: 'At least 1 row is required.',
        titleRequired: 'Report title is required.',
        saveFailed: 'Failed to save changes.',
        networkError: 'Network error occurred.',
        department: 'Department',
        period: 'Period',
        author: 'Author',
        summaryNotes: 'Operational Notes',
        reviewNotes: 'HOD Review Notes',
        downloadAttachment: 'Download Attachment',
        workflowHistory: 'Workflow Audit History',
        hodVerification: 'HOD Verification & Review',
        reviewPlaceholder: 'Add verification remarks or revision instructions...',
        markReviewed: 'Mark as Reviewed',
        returnRevision: 'Return for Revision',
        close: 'Close',
        checkingData: 'Checking controlled data...',
        assistantUnavailable: 'Assistant service temporarily unavailable.',
        editUser: 'Edit User',
        addUser: 'Add New User',
        total: 'Total',
    },
};

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (s) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[s]));
}

// Minimal Markdown for chatbot replies: **bold**, "- " bullets, "1. " steps, paragraphs.
// Escapes first, so model output can never inject HTML. ponytail: no lib; add marked+DOMPurify if tables/links are ever needed.
function renderChatText(str) {
    const inline = (s) => escapeHtml(s).replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-navy">$1</strong>');
    const out = [];
    let list = null; // { tag, items }
    let para = []; // consecutive text lines → one <p> with <br>; blank line ends it
    const flushList = () => {
        if (!list) return;
        const cls = list.tag === 'ul' ? 'list-disc' : 'list-decimal';
        out.push(`<${list.tag} class="${cls} pl-5 space-y-0.5">${list.items.map((i) => `<li>${i}</li>`).join('')}</${list.tag}>`);
        list = null;
    };
    const flushPara = () => {
        if (para.length) out.push(`<p>${para.join('<br>')}</p>`);
        para = [];
    };
    for (const raw of String(str ?? '').split('\n')) {
        const line = raw.trim();
        const m = line.match(/^(?:([-*•])|(\d+)[.)])\s+(.*)$/);
        if (m) {
            flushPara();
            const tag = m[1] ? 'ul' : 'ol';
            if (list?.tag !== tag) { flushList(); list = { tag, items: [] }; }
            list.items.push(inline(m[3]));
        } else if (line) {
            flushList();
            para.push(inline(line));
        } else {
            flushList();
            flushPara();
        }
    }
    flushList();
    flushPara();
    return out.join('');
}

function formatWita(value, withSeconds = true, isEn = document.documentElement.lang === 'en') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    const localeTag = isEn ? 'en-GB' : 'id-ID';
    const datePart = new Intl.DateTimeFormat(localeTag, {
        timeZone: 'Asia/Makassar',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(d);
    const timePart = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Makassar',
        hour: '2-digit',
        minute: '2-digit',
        ...(withSeconds ? { second: '2-digit' } : {}),
        hour12: false,
    }).format(d);
    return `${datePart}, ${timePart} WITA`;
}

function formatWitaIsoDate(value) {
    if (!value) return '';
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Makassar',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(d);
}

function formatWitaDateLabel(value, isEn = false) {
    if (!value) return '-';
    const d = typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T12:00:00+08:00`)
        : new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return new Intl.DateTimeFormat(isEn ? 'en-GB' : 'id-ID', {
        timeZone: 'Asia/Makassar',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(d);
}

function extractErrorMessage(data, fallback) {
    if (data?.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }
    return data?.message || fallback;
}

function showInlineError(boxId, message) {
    const el = document.getElementById(boxId);
    if (!el) return;
    if (!message) {
        el.classList.add('hidden');
        el.textContent = '';
        return;
    }
    el.textContent = message;
    el.classList.remove('hidden');
}

function initWorkspace() {
    const bootstrapEl = document.getElementById('workspace-bootstrap');
    if (!bootstrapEl) return;

    const boot = JSON.parse(bootstrapEl.textContent);
    const LOCALE = boot.locale || 'id';
    const IS_EN = LOCALE === 'en';
    const T = I18N[IS_EN ? 'en' : 'id'];
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const CURRENT_USER = boot.user;
    const TEMPLATES_DATA = boot.templates || [];
    let REPORTS_DATA = boot.reports || [];
    let REPORT_HISTORIES = boot.reportHistories || [];
    let POMEC_RECORDS = boot.pomecRecords || [];
    let POMEC_IMPORTS = boot.pomecImports || [];
    let MANAGED_USERS = boot.managedUsers || [];
    let AUDIT_LOGS = boot.auditLogs || [];
    let BACKUP_FILES = boot.backupFiles || [];

    let reportFilterState = { search: '', dept: boot.activeReportDept || '', period: '', date: '', status: 'all', list: false };
    let historyActionFilter = '';
    let adminFilterState = { search: '', role: '', dept: '', status: 'all' };
    let auditModuleFilter = '';
    let auditSearchQuery = '';
    let auditActorFilter = '';
    let auditSeverityFilter = '';
    let auditDateFrom = '';
    let auditWorkflowSearch = '';
    let auditWorkflowActionFilter = '';
    let pomecType = 'all';
    let pomecReportStatus = 'all';
    let pomecReplace = null; // laporan POMEC yang dikembalikan HOD dan sedang diunggah ulang
    let pomecView = 'day'; // 'day' | 'week' | 'month'
    let pomecMonth = '';
    let editorRows = [];
    let editorTitleTouched = false;
    let currentPomecPreviewToken = null;

    const tableStates = {
        reports: { page: 1, perPage: 10, sortKey: 'period_date', sortDir: 'desc', lastFilterSig: '' },
        histories: { page: 1, perPage: 10, sortKey: 'created_at', sortDir: 'desc', lastFilterSig: '' },
        users: { page: 1, perPage: 10, sortKey: 'name', sortDir: 'asc', lastFilterSig: '' },
        audit: { page: 1, perPage: 10, sortKey: 'created_at', sortDir: 'desc', lastFilterSig: '' },
        auditWorkflow: { page: 1, perPage: 10, sortKey: 'created_at', sortDir: 'desc', lastFilterSig: '' },
    };

    function compareSortValues(a, b) {
        if (a === b) return 0;
        if (a === null || a === undefined || a === '') return 1;
        if (b === null || b === undefined || b === '') return -1;
        if (typeof a === 'number' && typeof b === 'number') return a - b;
        return String(a).localeCompare(String(b), IS_EN ? 'en' : 'id', { numeric: true, sensitivity: 'base' });
    }

    function paginateAndSort(items, state, filterSig, extractors, footerId, onUpdate) {
        if (state.lastFilterSig !== filterSig) {
            state.page = 1;
            state.lastFilterSig = filterSig;
        }

        const extractor = state.sortKey ? extractors[state.sortKey] : null;
        const sorted = extractor
            ? [...items].sort((a, b) => {
                const cmp = compareSortValues(extractor(a), extractor(b));
                return state.sortDir === 'asc' ? cmp : -cmp;
            })
            : [...items];

        const total = sorted.length;
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));
        if (state.page > totalPages) state.page = totalPages;
        if (state.page < 1) state.page = 1;

        const startIdx = (state.page - 1) * state.perPage;
        const pageItems = sorted.slice(startIdx, startIdx + state.perPage);
        renderPaginationFooter(footerId, state, total, totalPages, startIdx, pageItems.length, onUpdate);
        return pageItems;
    }

    function renderPaginationFooter(footerId, state, total, totalPages, startIdx, shownCount, onUpdate) {
        const container = document.getElementById(footerId);
        if (!container) return;
        if (total === 0) {
            container.innerHTML = '';
            return;
        }

        const from = startIdx + 1;
        const to = startIdx + shownCount;
        const pages = [];
        const addPage = (p) => { if (!pages.includes(p)) pages.push(p); };
        addPage(1);
        for (let p = Math.max(2, state.page - 1); p <= Math.min(totalPages - 1, state.page + 1); p++) addPage(p);
        if (totalPages > 1) addPage(totalPages);

        let pageButtonsHtml = '';
        let prev = 0;
        pages.forEach((p) => {
            if (prev && p - prev > 1) {
                pageButtonsHtml += '<span class="px-1.5 text-slate-400">…</span>';
            }
            const active = p === state.page;
            pageButtonsHtml += `<button type="button" data-pg="${p}" class="pg-btn min-w-[28px] h-7 px-2 rounded-lg text-xs font-mono-num font-semibold border transition cursor-pointer ${active ? 'bg-navy text-white border-navy' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'}">${p}</button>`;
            prev = p;
        });

        container.innerHTML = `
            <div class="px-5 py-3 bg-slate-50/60 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5 text-slate-500">
                    <span>${IS_EN ? 'Showing' : 'Menampilkan'} <strong class="font-mono-num text-navy">${from}–${to}</strong> ${IS_EN ? 'of' : 'dari'} <strong class="font-mono-num text-navy">${total}</strong></span>
                    <select data-pg-size class="h-7 px-2 rounded-lg border border-slate-200 bg-white text-xs font-mono-num text-navy focus:outline-none focus:ring-2 focus:ring-teal/25">
                        ${[10, 25, 50].map((n) => `<option value="${n}" ${state.perPage === n ? 'selected' : ''}>${n}/${IS_EN ? 'page' : 'hal'}</option>`).join('')}
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" data-pg="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''} class="pg-btn h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Prev</button>
                    ${pageButtonsHtml}
                    <button type="button" data-pg="${state.page + 1}" ${state.page >= totalPages ? 'disabled' : ''} class="pg-btn h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Next</button>
                </div>
            </div>
        `;

        container.querySelectorAll('.pg-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = Number(btn.dataset.pg);
                if (!Number.isFinite(target) || target < 1 || target > totalPages || target === state.page) return;
                state.page = target;
                onUpdate();
            });
        });

        container.querySelector('[data-pg-size]')?.addEventListener('change', (e) => {
            state.perPage = Number(e.target.value) || 10;
            state.page = 1;
            onUpdate();
        });
    }

    /**
     * Tabel data dengan TanStack Table: kepala kolom bisa diurutkan (aria-sort), 10 baris per halaman.
     * columns: [{ id, header, accessor?, cell, align?, visible?, tdClass? }]; tanpa accessor = tidak bisa diurutkan.
     */
    function createDataTable({ tableId, pagerId, columns, sorting = [], empty }) {
        const el = document.getElementById(tableId);
        if (!el) return null;
        const table = constructTable({
            features: DATA_TABLE_FEATURES,
            data: [],
            columns: columns.map((c) => ({
                id: c.id,
                accessorFn: c.accessor || (() => ''),
                header: c.id,
                enableSorting: !!c.accessor,
                sortingFn: 'basic',
                sortDescFirst: c.align === 'right',
            })),
            initialState: { sorting, pagination: { pageIndex: 0, pageSize: 10 } },
        });
        const text = (v) => (typeof v === 'function' ? v() : v);
        const alignCls = (c) => (c.align === 'right' ? 'text-right' : '');

        const render = () => {
            const { pageIndex, pageSize } = table.store.state.pagination;
            const pageCount = Math.max(1, table.getPageCount());
            // Data berkurang (filter, hapus) dan halaman ini jadi kosong: mundur ke halaman terakhir.
            if (pageIndex >= pageCount) return table.setPageIndex(pageCount - 1);

            const shown = columns.filter((c) => !c.visible || c.visible());
            el.tHead.innerHTML = `<tr>${shown.map((c) => {
                const col = table.getColumn(c.id);
                const label = escapeHtml(text(c.header));
                if (!col.getCanSort()) return `<th scope="col" class="py-3 px-5 ${alignCls(c)}">${label}</th>`;
                const sorted = col.getIsSorted();
                const aria = sorted === 'asc' ? 'ascending' : (sorted === 'desc' ? 'descending' : 'none');
                return `<th scope="col" class="py-3 px-5 ${alignCls(c)}" aria-sort="${aria}">
                    <button type="button" data-sort="${c.id}" class="inline-flex items-center gap-1 uppercase tracking-wider font-semibold whitespace-nowrap hover:text-navy transition cursor-pointer ${c.align === 'right' ? 'flex-row-reverse' : ''}">
                        ${label}<span aria-hidden="true" class="text-[10px] ${sorted ? 'text-navy' : 'opacity-40'}">${sorted === 'asc' ? '↑' : (sorted === 'desc' ? '↓' : '↕')}</span>
                    </button>
                </th>`;
            }).join('')}</tr>`;

            const rows = table.getRowModel().rows;
            el.tBodies[0].innerHTML = rows.length
                ? rows.map((row) => `<tr class="hover:bg-slate-50/70 transition">${shown.map((c) => `<td class="py-3 px-5 ${alignCls(c)} ${c.tdClass || ''}">${c.cell(row.original)}</td>`).join('')}</tr>`).join('')
                : `<tr><td colspan="${shown.length}" class="py-8 text-center text-slate-400">${escapeHtml(text(empty))}</td></tr>`;

            const pager = { page: pageIndex + 1, perPage: pageSize };
            renderPaginationFooter(pagerId, pager, table.getPrePaginatedRowModel().rows.length, pageCount, pageIndex * pageSize, rows.length, () => {
                if (pager.perPage !== pageSize) table.setPageSize(pager.perPage);
                table.setPageIndex(pager.page - 1);
            });
        };

        el.tHead.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-sort]');
            if (btn) table.getColumn(btn.dataset.sort).getToggleSortingHandler()(e);
        });
        table.store.subscribe(render);

        return {
            setData(data, { resetPage = false } = {}) {
                table.setOptions((prev) => ({ ...prev, data }));
                if (resetPage && table.store.state.pagination.pageIndex !== 0) table.setPageIndex(0);
                else render();
            },
        };
    }

    function bindSortableHeaders(tableId, state, onUpdate) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const headers = table.querySelectorAll('thead th[data-sort-key]');

        const syncIndicators = () => {
            headers.forEach((th) => {
                const key = th.dataset.sortKey;
                th.classList.add('whitespace-nowrap');
                let ind = th.querySelector('.sort-ind');
                if (!ind) {
                    const label = th.textContent.trim();
                    th.textContent = '';
                    const wrap = document.createElement('span');
                    wrap.className = 'inline-flex items-center gap-1 whitespace-nowrap';
                    const txt = document.createElement('span');
                    txt.textContent = label;
                    ind = document.createElement('span');
                    ind.className = 'sort-ind inline-block text-[10px] font-mono-num opacity-60 shrink-0';
                    wrap.appendChild(txt);
                    wrap.appendChild(ind);
                    th.appendChild(wrap);
                }
                if (state.sortKey === key) {
                    ind.textContent = state.sortDir === 'asc' ? '↑' : '↓';
                    ind.classList.remove('opacity-60');
                    ind.classList.add('text-teal');
                } else {
                    ind.textContent = '↕';
                    ind.classList.add('opacity-60');
                    ind.classList.remove('text-teal');
                }
            });
        };

        headers.forEach((th) => {
            th.addEventListener('click', () => {
                const key = th.dataset.sortKey;
                if (!key) return;
                if (state.sortKey === key) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortKey = key;
                    state.sortDir = 'asc';
                }
                state.page = 1;
                syncIndicators();
                onUpdate();
            });
        });

        syncIndicators();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    function statusBadgeHtml(status) {
        const meta = {
            reviewed: ['bg-emerald-50 text-emerald-700 border-emerald-200', 'REVIEWED'],
            in_review: ['bg-cyan-50 text-teal border-cyan-200', 'REVIEW'],
            submitted: ['bg-amber-50 text-amber-700 border-amber-200', 'SUBMITTED'],
            returned: ['bg-rose-50 text-crimson-dark border-rose-200', 'RETURNED / REVISION'],
            draft: ['bg-slate-100 text-slate-600 border-slate-200', 'DRAFT'],
        }[status] || ['bg-slate-100 text-slate-600 border-slate-200', String(status || '').toUpperCase()];

        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${meta[0]}">${escapeHtml(meta[1])}</span>` +
            (status === 'reviewed' ? '<div class="text-[10px] text-emerald-700 font-medium mt-0.5">Available for monitoring</div>' : '');
    }

    function canEditReport(rpt) {
        if (!['draft', 'returned'].includes(rpt.status)) return false;
        // Form manual dimatikan; hanya laporan POMEC yang masih bisa ditindaklanjuti.
        if (!rpt.pomec_import_id && !boot.manualReports) return false;
        if (CURRENT_USER.role === 'it_admin') return true;
        const allowed = CURRENT_USER.inputtableDepartmentIds || [];
        return allowed.includes(rpt.department_id);
    }

    function canReviewReport(rpt) {
        if (!['submitted', 'in_review'].includes(rpt.status)) return false;
        if (CURRENT_USER.role === 'it_admin') return true;
        const allowed = CURRENT_USER.reviewableDepartmentIds || [];
        return allowed.includes(rpt.department_id);
    }

    function refreshKpis() {
        const total = REPORTS_DATA.length;
        const pending = REPORTS_DATA.filter((r) => ['submitted', 'in_review'].includes(r.status)).length;
        const returned = REPORTS_DATA.filter((r) => r.status === 'returned').length;
        const reviewed = REPORTS_DATA.filter((r) => r.status === 'reviewed').length;

        const elTotal = document.getElementById('kpi-total');
        const elPending = document.getElementById('kpi-pending');
        const elReturned = document.getElementById('kpi-returned');
        const elBadge = document.getElementById('reports-tab-badge');

        if (elTotal) elTotal.textContent = total;
        if (elPending) elPending.textContent = pending;
        if (elReturned) elReturned.textContent = returned;
        if (elBadge) {
            const actionCount = pending + returned;
            elBadge.textContent = actionCount;
            elBadge.classList.toggle('hidden', actionCount === 0);
            document.getElementById('notif-dot')?.classList.toggle('hidden', actionCount === 0);
            const pill = document.getElementById('notif-count-pill');
            if (pill) pill.textContent = actionCount;
        }

        const pct = total > 0 ? Math.round((reviewed / total) * 100) : 0;
        const pctEl = document.getElementById('kpi-reviewed-pct');
        const barEl = document.getElementById('kpi-reviewed-bar');
        if (pctEl) pctEl.textContent = `${pct}% Reviewed`;
        if (barEl) barEl.style.width = `${pct}%`;
    }

    function upsertReportInState(updated) {
        const idx = REPORTS_DATA.findIndex((r) => r.id === updated.id);
        if (idx >= 0) REPORTS_DATA[idx] = updated;
        else REPORTS_DATA.unshift(updated);

        if (Array.isArray(updated.histories)) {
            const dept = updated.department || null;
            updated.histories.forEach((h) => {
                if (!REPORT_HISTORIES.some((existing) => existing.id === h.id)) {
                    REPORT_HISTORIES.unshift({
                        ...h,
                        report: {
                            id: updated.id,
                            report_code: updated.report_code,
                            title: updated.title,
                            department: dept,
                            period_type: updated.period_type,
                            period_date: updated.period_date,
                            status: updated.status,
                        },
                    });
                }
            });
        }
        renderReportsTable();
        renderReportHistories();
        refreshKpis();
        if (updated.pomec_import_id) {
            renderPomecImports();
            renderPomecFreshness();
        }
    }

    // -------------------------------------------------------------------------
    // 1. Sidebar, Tab Switching & Header User Menu
    // -------------------------------------------------------------------------
    const sidebar = document.getElementById('app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const sidebarMobileOpen = document.getElementById('sidebar-mobile-open');
    const sidebarMobileClose = document.getElementById('sidebar-mobile-close');
    const sidebarCollapseBtn = document.getElementById('sidebar-collapse-btn');
    const SIDEBAR_KEY = 'sbwm_sidebar_collapsed';

    function applySidebarCollapsed(collapsed) {
        if (!sidebar) return;
        sidebar.classList.toggle('sidebar-collapsed', collapsed);
    }

    if (localStorage.getItem(SIDEBAR_KEY) === '1') {
        applySidebarCollapsed(true);
    }

    sidebarCollapseBtn?.addEventListener('click', () => {
        const nextCollapsed = !sidebar?.classList.contains('sidebar-collapsed');
        applySidebarCollapsed(nextCollapsed);
        try { localStorage.setItem(SIDEBAR_KEY, nextCollapsed ? '1' : '0'); } catch {}
    });

    function openMobileSidebar() {
        sidebar?.classList.remove('-translate-x-full');
        sidebarBackdrop?.classList.remove('hidden');
    }

    function closeMobileSidebar() {
        sidebar?.classList.add('-translate-x-full');
        sidebarBackdrop?.classList.add('hidden');
    }

    sidebarMobileOpen?.addEventListener('click', openMobileSidebar);
    sidebarMobileClose?.addEventListener('click', closeMobileSidebar);
    sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

    const userMenuBtn = document.getElementById('user-menu-btn');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    const userMenuChevron = document.getElementById('user-menu-chevron');
    const userMenuContainer = document.getElementById('user-menu-container');

    window.closeUserMenu = function () {
        userMenuDropdown?.classList.add('hidden');
        userMenuChevron?.classList.remove('rotate-180');
    };

    userMenuBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = userMenuDropdown?.classList.contains('hidden');
        userMenuDropdown?.classList.toggle('hidden');
        userMenuChevron?.classList.toggle('rotate-180', !!willOpen);
    });

    document.addEventListener('click', (e) => {
        if (userMenuContainer && !userMenuContainer.contains(e.target)) {
            window.closeUserMenu();
        }
        if (notifContainer && !notifContainer.contains(e.target)) {
            notifDropdown?.classList.add('hidden');
        }
    });

    // Topbar Quick Search -> jumps directly to Reports tab & filters
    document.getElementById('topbar-quick-search')?.addEventListener('input', (e) => {
        const val = e.target.value;
        const reportSearch = document.getElementById('report-search');
        if (reportSearch) reportSearch.value = val;
        reportFilterState.search = val;
        if (val.trim() !== '') {
            window.switchWorkspaceTab('reports');
        }
        renderReportsTable();
    });

    // Live WITA Operational Clock (Makassar Asia/Makassar UTC+8)
    (function startWitaClock() {
        function formatLiveWita(now) {
            const localeTag = IS_EN ? 'en-GB' : 'id-ID';
            const dayPart = new Intl.DateTimeFormat(localeTag, { timeZone: 'Asia/Makassar', weekday: 'short' }).format(now);
            const datePart = new Intl.DateTimeFormat(localeTag, { timeZone: 'Asia/Makassar', day: '2-digit', month: 'short', year: 'numeric' }).format(now);
            const timePart = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Makassar',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(now);
            return `${dayPart}, ${datePart} • ${timePart} WITA`;
        }
        function tick() {
            const targets = document.querySelectorAll('[data-wita-clock]');
            if (targets.length === 0) return;
            try {
                const formatted = formatLiveWita(new Date());
                targets.forEach((el) => {
                    el.textContent = formatted;
                });
            } catch {}
        }
        tick();
        setInterval(tick, 1000);
    })();

    // Actionable Report Notifications Dropdown
    const notifContainer = document.getElementById('notif-container');
    const notifBtn = document.getElementById('notif-btn');
    const notifDropdown = document.getElementById('notif-dropdown');

    function renderNotifList() {
        const listEl = document.getElementById('notif-list');
        const dotEl = document.getElementById('notif-dot');
        const pillEl = document.getElementById('notif-count-pill');
        if (!listEl) return;

        const actionable = REPORTS_DATA.filter((r) => ['submitted', 'in_review', 'returned'].includes(r.status));
        dotEl?.classList.toggle('hidden', actionable.length === 0);
        if (pillEl) pillEl.textContent = actionable.length;

        if (actionable.length === 0) {
            listEl.innerHTML = `<div class="p-4 text-center text-slate-400">${IS_EN ? 'All reports are up to date.' : 'Tidak ada laporan yang menunggu tindakan.'}</div>`;
            return;
        }

        listEl.innerHTML = actionable.slice(0, 6).map((r) => `
            <button type="button" onclick="notifDropdownClose(); openReportDetail(${r.id})"
                class="w-full text-left px-4 py-3 hover:bg-slate-50 transition flex items-start justify-between gap-2 cursor-pointer">
                <div class="min-w-0">
                    <div class="font-semibold text-navy truncate">${escapeHtml(r.title)}</div>
                    <div class="text-[11px] font-mono-num text-slate-500">${escapeHtml(r.report_code)} &bull; ${escapeHtml(r.department?.code || '')}</div>
                </div>
                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold ${r.status === 'returned' ? 'bg-rose-50 text-crimson-dark' : 'bg-amber-50 text-amber-700'}">
                    ${escapeHtml(r.status.toUpperCase())}
                </span>
            </button>
        `).join('');
    }

    window.notifDropdownClose = function () {
        notifDropdown?.classList.add('hidden');
    };

    notifBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        renderNotifList();
        notifDropdown?.classList.toggle('hidden');
    });

    // Engineering melapor lewat POMEC: halaman laporannya adalah POMEC.
    const hasPomec = () => !!document.getElementById('pomec-root');
    const engOnly = () => (boot.accessibleDepts || []).length === 1 && boot.accessibleDepts[0].code === 'ENG' && hasPomec();

    window.switchWorkspaceTab = function (tabKey, { toggleSubmenu = false } = {}) {
        if (tabKey === 'reports' && engOnly()) tabKey = 'pomec';
        const currentVisiblePanel = document.querySelector('.workspace-panel:not(.hidden)');
        const alreadyOnTab = currentVisiblePanel?.id === `tab-panel-${tabKey}`;

        document.querySelectorAll('.workspace-panel').forEach((p) => p.classList.add('hidden'));
        document.getElementById(`tab-panel-${tabKey}`)?.classList.remove('hidden');
        updateKpiRow();

        // POMEC berada di bawah Laporan Departemen → Engineering.
        const navKey = tabKey === 'pomec' ? 'reports' : tabKey;
        document.querySelectorAll('.workspace-tab-btn').forEach((btn) => {
            const active = btn.dataset.tab === navKey;
            btn.className = 'workspace-tab-btn w-full flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer ' +
                (active ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100/80 hover:text-navy');
        });

        const submenuEl = document.getElementById('sidebar-reports-submenu');
        const chevronEl = document.getElementById('sidebar-reports-chevron');
        const reportsNavBtn = document.querySelector('.workspace-tab-btn[data-tab="reports"]');
        if (submenuEl) {
            const isCurrentlyOpen = !submenuEl.classList.contains('hidden');
            const openSubmenu = navKey === 'reports'
                ? ((toggleSubmenu && alreadyOnTab) ? !isCurrentlyOpen : true)
                : false;
            submenuEl.classList.toggle('hidden', !openSubmenu);
            chevronEl?.classList.toggle('rotate-180', openSubmenu);
            reportsNavBtn?.setAttribute('aria-expanded', openSubmenu ? 'true' : 'false');
        }

        const titleEl = document.getElementById('topbar-page-title');
        if (titleEl && boot.tabLabels?.[tabKey]) {
            titleEl.textContent = boot.tabLabels[tabKey];
        }

        if (!(alreadyOnTab && toggleSubmenu && tabKey === 'reports' && submenuEl)) {
            closeMobileSidebar();
        }

        const url = new URL(window.location);
        url.searchParams.set('tab', tabKey);
        if (tabKey === 'reports' && reportFilterState.dept) {
            url.searchParams.set('dept', reportFilterState.dept);
        } else {
            url.searchParams.delete('dept');
        }
        window.history.replaceState({}, '', url);

        if (typeof syncReportDepartmentSubmenus === 'function') {
            syncReportDepartmentSubmenus();
        }

        if (tabKey === 'dashboard' || tabKey === 'executive' || tabKey === 'reports' || tabKey === 'pomec') {
            const shouldAnimateReveal = !revealedChartTabs.has(tabKey);
            revealedChartTabs.add(tabKey);
            window.requestAnimationFrame(() => {
                ['gm-chart-pomec-trend', 'gm-chart-report-status', 'gm-chart-hk-rooms', 'reports-chart-status', 'reports-chart-trend', 'reports-chart-dept', 'pomec-chart-trend', 'pomec-chart-cost', 'pomec-mini-electricity', 'pomec-mini-water', 'pomec-mini-gas', 'pomec-mini-solar'].forEach((id) => {
                    const c = document.getElementById(id);
                    const chart = c ? Chart.getChart(c) : null;
                    if (!chart) return;
                    if (shouldAnimateReveal) {
                        chart.reset();
                        chart.resize();
                        chart.update();
                    } else {
                        chart.resize();
                        chart.update('none');
                    }
                });
            });
        }
    };

    // -------------------------------------------------------------------------
    // 2. Modals
    // -------------------------------------------------------------------------
    window.openModal = function (id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
    };

    window.closeModal = function (id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
    };

    // Close modal on Escape key or backdrop click
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[id^="modal-"]').forEach((m) => {
                if (!m.classList.contains('hidden')) window.closeModal(m.id);
            });
        }
    });

    document.querySelectorAll('[data-backdrop-close="true"]').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) window.closeModal(backdrop.id);
        });
    });

    // -------------------------------------------------------------------------
    // 3. Departmental Reports Table & Instant Filter
    // -------------------------------------------------------------------------
    function reportMatchesFilters(rpt, ignoreStatus = false) {
        const q = reportFilterState.search.trim().toLowerCase();
        if (!ignoreStatus && reportFilterState.status !== 'all' && rpt.status !== reportFilterState.status) return false;
        if (reportFilterState.dept && rpt.department?.code !== reportFilterState.dept) return false;
        if (reportFilterState.period && rpt.period_type !== reportFilterState.period) return false;
        if (reportFilterState.date && formatWitaIsoDate(rpt.period_date) !== reportFilterState.date) return false;
        if (q) {
            const hay = `${rpt.report_code} ${rpt.title} ${rpt.creator?.name || ''} ${rpt.department?.code || ''} ${rpt.department?.name || ''} ${rpt.template?.name || ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    }

    // Ringkasan semua departemen tampil selama belum memilih departemen, memfilter, atau membuka daftar lengkap.
    function isReportsOverview() {
        const f = reportFilterState;
        return (boot.accessibleDepts || []).length > 1 && !f.dept && !f.list && !f.search.trim() && !f.period && !f.date && f.status === 'all';
    }

    function updateKpiRow() {
        const shown = (id) => document.getElementById(id)?.classList.contains('hidden') === false;
        document.getElementById('report-kpi-row')?.classList.toggle('hidden', shown('tab-panel-pomec') || (shown('tab-panel-reports') && !isReportsOverview()));
    }

    // Satu kartu per departemen, lalu laporan yang masih menunggu.
    function renderReportsOverview() {
        const grid = document.getElementById('reports-overview-cards');
        const waitingList = document.getElementById('reports-overview-waiting');
        if (!grid || !waitingList) return;
        const deptName = (d) => (IS_EN ? (d.name_en || d.name) : d.name);
        const card = 'bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)]';

        grid.innerHTML = (boot.accessibleDepts || []).map((dept) => {
            const own = REPORTS_DATA.filter((r) => r.department?.code === dept.code);
            const parts = REPORT_STATUS_META.map((m) => ({ ...m, count: own.filter((r) => r.status === m.key).length })).filter((m) => m.count);
            const reviewed = own.filter((r) => r.status === 'reviewed').length;
            const latest = own.reduce((max, r) => {
                const d = formatWitaIsoDate(r.period_date);
                return d > max ? d : max;
            }, '');

            return `<div class="${card} flex flex-col hover:border-teal transition">
                <button type="button" data-open-dept="${escapeHtml(dept.code)}" class="grow flex flex-col gap-3 p-4 text-left cursor-pointer">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-navy text-[10px] font-bold tracking-wider">${escapeHtml(dept.code)}</span>
                        <span class="flex-1 truncate text-sm font-heading font-bold text-navy">${escapeHtml(deptName(dept))}</span>
                        <span class="text-teal text-xs" aria-hidden="true">&rarr;</span>
                    </span>
                    <span class="flex items-baseline justify-between gap-2">
                        <span><span class="text-2xl font-heading font-extrabold text-navy">${own.length}</span> <span class="text-xs text-slate-500">${IS_EN ? (own.length === 1 ? 'report' : 'reports') : 'laporan'}</span></span>
                        <span class="text-[11px] text-slate-500">${own.length ? `${Math.round((reviewed / own.length) * 100)}% ${IS_EN ? 'approved' : 'disetujui'}` : ''}</span>
                    </span>
                    <span class="flex gap-0.5 h-2 rounded-full overflow-hidden bg-slate-100" aria-hidden="true">${parts.map((m) => `<span style="flex:${m.count} 1 0%;background-color:${m.color}"></span>`).join('')}</span>
                    <span class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600 min-h-4">${parts.length
                        ? parts.map((m) => `<span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full" style="background-color:${m.color}"></span>${m.count} ${escapeHtml(m.label)}</span>`).join('')
                        : (IS_EN ? 'No reports yet' : 'Belum ada laporan')}</span>
                    <span class="mt-auto text-[11px] text-slate-400">${latest ? `${IS_EN ? 'Latest period' : 'Periode terakhir'}: ${escapeHtml(formatWitaDateLabel(latest, IS_EN))}` : '&nbsp;'}</span>
                </button>
            </div>`;
        }).join('');

        const since = (r) => r.submitted_at || r.updated_at;
        const waiting = REPORTS_DATA.filter((r) => ['submitted', 'in_review', 'returned'].includes(r.status))
            .sort((a, b) => new Date(since(a)) - new Date(since(b)));
        const rows = waiting.slice(0, 6).map((r) => `<li>
            <button type="button" data-open-report="${Number(r.id)}" class="w-full flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-left hover:bg-slate-50/70 transition cursor-pointer">
                <span class="w-10 text-[10px] font-bold text-slate-500">${escapeHtml(r.department?.code || '-')}</span>
                <span class="flex-1 min-w-[180px] truncate font-semibold text-navy">${escapeHtml(r.title)}</span>
                <span class="text-slate-500 whitespace-nowrap">${IS_EN ? 'since' : 'sejak'} ${escapeHtml(formatWitaDateLabel(since(r), IS_EN))}</span>
                ${statusBadgeHtml(r.status)}
            </button>
        </li>`);
        if (waiting.length > 6) rows.push(`<li class="px-5 py-2.5 text-slate-500">${IS_EN ? `${waiting.length - 6} more in all reports.` : `${waiting.length - 6} lainnya ada di semua laporan.`}</li>`);
        waitingList.innerHTML = rows.join('') || `<li class="px-5 py-6 text-center text-slate-400">${IS_EN ? 'Nothing is waiting.' : 'Tidak ada laporan yang menunggu.'}</li>`;
    }

    document.getElementById('reports-overview')?.addEventListener('click', (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        if (target.dataset.openDept) {
            window.selectReportDepartment(target.dataset.openDept);
            window.scrollTo({ top: 0 });
        } else if (target.dataset.openReport) {
            window.openReportDetail(Number(target.dataset.openReport));
        } else if (target.hasAttribute('data-show-all-reports')) {
            reportFilterState.list = true;
            renderReportsTable();
            renderReportHistories();
        }
    });

    function renderReportsTable() {
        const tbody = document.getElementById('reports-tbody');
        const emptyOnboard = document.getElementById('reports-empty-onboarding');
        const emptyFilter = document.getElementById('reports-empty-filter');
        if (!tbody) return;

        const q = reportFilterState.search.trim().toLowerCase();
        const filtered = REPORTS_DATA.filter((rpt) => reportMatchesFilters(rpt));
        try {
            syncReportDepartmentSubmenus();
        } catch (err) {
            console.error('Failed to sync department submenus:', err);
        }
        const overview = isReportsOverview();
        document.getElementById('reports-overview')?.classList.toggle('hidden', !overview);
        document.getElementById('reports-list-view')?.classList.toggle('hidden', overview);
        document.getElementById('reports-back-btn')?.classList.toggle('hidden', overview);
        updateKpiRow();
        if (overview) {
            renderReportsOverview();
            return;
        }
        try {
            renderReportsInsights(filtered);
        } catch (err) {
            console.error('Failed to render report analytics:', err);
        }

        const countEl = document.getElementById('report-filter-count');
        if (countEl) countEl.textContent = `${filtered.length}/${REPORTS_DATA.length}`;
        const hasActiveFilter = !!(q || reportFilterState.dept || reportFilterState.period || reportFilterState.date || reportFilterState.status !== 'all');
        document.getElementById('report-reset-btn')?.classList.toggle('hidden', !hasActiveFilter);

        emptyOnboard?.classList.toggle('hidden', REPORTS_DATA.length > 0);
        emptyFilter?.classList.toggle('hidden', REPORTS_DATA.length === 0 || filtered.length > 0);

        const filterSig = JSON.stringify(reportFilterState);
        const pageItems = paginateAndSort(
            filtered,
            tableStates.reports,
            filterSig,
            {
                title: (r) => r.title || '',
                department: (r) => (IS_EN ? (r.department?.name_en || r.department?.name) : r.department?.name) || '',
                period_date: (r) => formatWitaIsoDate(r.period_date) || '',
                creator: (r) => r.creator?.name || '',
                status: (r) => r.status || '',
            },
            'reports-pagination',
            renderReportsTable
        );

        tbody.innerHTML = pageItems.map((rpt) => {
            const deptName = IS_EN ? (rpt.department?.name_en || rpt.department?.name) : rpt.department?.name;
            const tplName = IS_EN ? (rpt.template?.name_en || rpt.template?.name) : rpt.template?.name;
            const periodStr = formatWitaDateLabel(rpt.period_date, IS_EN);
            const rowCount = Array.isArray(rpt.rows_data) ? rpt.rows_data.length : 0;

            return `
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="py-4 px-5 align-middle min-w-[240px]">
                        <div class="font-semibold text-navy leading-snug">${escapeHtml(rpt.title)}</div>
                        <div class="text-xs font-mono-num text-slate-500 mt-0.5">${escapeHtml(rpt.report_code)} &bull; ${rowCount} ${T.rows}</div>
                    </td>
                    <td class="py-4 px-5 align-middle min-w-[200px]">
                        <div class="text-xs font-semibold text-slate-700">${escapeHtml(deptName)}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">${escapeHtml(tplName)}</div>
                    </td>
                    <td class="py-4 px-5 align-middle whitespace-nowrap text-xs font-mono-num text-slate-600">
                        <div>${escapeHtml(periodStr)} <span class="text-[10px] uppercase text-slate-400">(${escapeHtml(rpt.period_type)})</span></div>
                        <div class="text-[10px] text-slate-400 mt-0.5">${escapeHtml(formatWita(rpt.reviewed_at || rpt.submitted_at || rpt.updated_at, false))}</div>
                    </td>
                    <td class="py-4 px-5 align-middle whitespace-nowrap text-xs text-slate-600">${escapeHtml(rpt.creator?.name || '-')}</td>
                    <td class="py-4 px-5 align-middle whitespace-nowrap">${statusBadgeHtml(rpt.status)}</td>
                    <td class="py-4 px-5 align-middle text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <button type="button" onclick="openReportDetail(${rpt.id})" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-navy hover:text-white text-navy transition cursor-pointer">
                                ${canReviewReport(rpt) ? T.review : T.view}
                            </button>
                            ${canEditReport(rpt) && !rpt.pomec_import_id ? `
                                <button type="button" onclick="openReportEditor(${rpt.id})" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-teal/10 text-teal hover:bg-teal hover:text-white transition cursor-pointer">
                                    ${T.edit}
                                </button>` : ''}
                            ${(canEditReport(rpt) && rpt.status === 'draft') ? `
                                <button type="button" onclick="openDeleteDraftModal(${rpt.id}, '${escapeHtml(rpt.report_code)}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                                    ${T.del}
                                </button>` : ''}
                            <a href="/reports/${rpt.id}/export/excel" class="inline-block px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50">XLSX</a>
                            <a href="/reports/${rpt.id}/export/pdf" class="inline-block px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 text-crimson-dark hover:bg-rose-50">PDF</a>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // -------------------------------------------------------------------------
    // 3b. Reports Analytics (charts follow filters, click-to-filter)
    // -------------------------------------------------------------------------
    const REPORT_STATUS_META = [
        { key: 'reviewed', label: IS_EN ? 'Reviewed' : 'Disetujui HOD', color: '#059669' },
        { key: 'in_review', label: IS_EN ? 'In Review' : 'Sedang Direview', color: '#1E6B7B' },
        { key: 'submitted', label: IS_EN ? 'Submitted' : 'Menunggu Review', color: '#D97706' },
        { key: 'returned', label: IS_EN ? 'Returned' : 'Perlu Revisi', color: '#F04E30' },
        { key: 'draft', label: 'Draft', color: '#CBD5E1' },
    ];

    const REDUCED_CHART_MOTION = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
    const revealedChartTabs = new Set([boot.activeTab || 'dashboard']);
    const isDarkTheme = () => document.documentElement.classList.contains('dark');
    const chartTextColor = () => (isDarkTheme() ? '#8D96A8' : '#64748B');
    const chartStrongLabelColor = () => (isDarkTheme() ? '#E8ECF4' : '#0F2942');
    const chartGridColor = () => (isDarkTheme() ? 'rgba(255, 255, 255, 0.065)' : 'rgba(148, 163, 184, 0.14)');
    const chartLwbpBarColor = () => (isDarkTheme() ? '#4FA3C7' : '#0F2942');
    const chartPointBorderColor = () => (isDarkTheme() ? '#11151E' : '#FFFFFF');
    const chartEmptyTrackColor = () => (isDarkTheme() ? ['#1F2636'] : ['#E2E8F0']);

    function applyChartThemeDefaults() {
        Chart.defaults.color = chartTextColor();
        Chart.defaults.borderColor = chartGridColor();
        if (Chart.defaults.plugins?.legend?.labels) {
            Chart.defaults.plugins.legend.labels.color = chartTextColor();
        }
        if (Chart.defaults.plugins?.tooltip) {
            Chart.defaults.plugins.tooltip.backgroundColor = isDarkTheme() ? 'rgba(11, 14, 21, 0.96)' : 'rgba(15, 41, 66, 0.94)';
            Chart.defaults.plugins.tooltip.titleColor = '#F8FAFC';
            Chart.defaults.plugins.tooltip.bodyColor = isDarkTheme() ? '#D4DAE6' : '#E2E8F0';
            Chart.defaults.plugins.tooltip.borderColor = isDarkTheme() ? 'rgba(255, 255, 255, 0.14)' : 'rgba(148, 163, 184, 0.18)';
            Chart.defaults.plugins.tooltip.borderWidth = 1;
        }
    }

    function configureExecutiveCharts() {
        Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        // Preserve existing keys on Chart.defaults.animation (type/fn/from/to/delay/loop) so Chart.js keeps color/number interpolators.
        Chart.defaults.animation.duration = REDUCED_CHART_MOTION ? 0 : 540;
        Chart.defaults.animation.easing = 'easeOutQuart';
        Chart.defaults.transitions.active.animation.duration = REDUCED_CHART_MOTION ? 0 : 150;
        Chart.defaults.transitions.resize.animation.duration = 0;

        Object.assign(Chart.defaults.plugins.tooltip, {
            backgroundColor: 'rgba(15, 41, 66, 0.95)',
            titleColor: '#F8FAFC',
            bodyColor: '#E2E8F0',
            footerColor: '#94A3B8',
            borderColor: 'rgba(255, 255, 255, 0.12)',
            borderWidth: 1,
            cornerRadius: 10,
            padding: 10,
            boxPadding: 5,
            usePointStyle: true,
            caretSize: 5,
            titleFont: { family: "'Plus Jakarta Sans', 'Inter', sans-serif", size: 12, weight: '700' },
            bodyFont: { family: "'JetBrains Mono', ui-monospace, monospace", size: 11 },
            footerFont: { family: "'Inter', sans-serif", size: 10, weight: '500' },
        });
        applyChartThemeDefaults();
    }
    configureExecutiveCharts();

    function chartStagger(stepMs = 20, maxMs = 220) {
        if (REDUCED_CHART_MOTION) return { duration: 0 };
        return {
            duration: 540,
            easing: 'easeOutQuart',
            delay(ctx) {
                if (ctx.type !== 'data' || ctx.mode !== 'default') return 0;
                return Math.min(maxMs, ctx.dataIndex * stepMs + ctx.datasetIndex * 30);
            },
        };
    }

    function doughnutAnimation() {
        return REDUCED_CHART_MOTION
            ? { duration: 0 }
            : { animateRotate: true, animateScale: false, duration: 640, easing: 'easeOutQuart' };
    }

    function makeChartAreaGradient(topColor, bottomColor) {
        return ({ chart }) => {
            const { ctx, chartArea } = chart;
            if (!chartArea) return topColor;
            const grad = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            grad.addColorStop(0, topColor);
            grad.addColorStop(1, bottomColor);
            return grad;
        };
    }

    function upsertChart(canvas, config) {
        const existing = Chart.getChart(canvas);
        if (existing && existing.config.type === config.type && existing.data.datasets.length === config.data.datasets.length) {
            existing.data.labels = config.data.labels;
            config.data.datasets.forEach((ds, idx) => {
                Object.assign(existing.data.datasets[idx], ds);
            });
            existing.options = config.options;
            existing.update();
            return existing;
        }
        existing?.destroy();
        return new Chart(canvas, config);
    }

    function applyReportStatusPills() {
        document.querySelectorAll('.report-status-btn').forEach((b) => {
            const active = b.dataset.status === reportFilterState.status;
            b.className = 'report-status-btn px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer ' +
                (active ? 'bg-navy text-white border-navy' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100');
        });
    }

    window.setReportStatusFilter = function (status) {
        reportFilterState.status = reportFilterState.status === status ? 'all' : status;
        applyReportStatusPills();
        renderReportsTable();
        renderReportHistories();
    };

    function syncReportDepartmentSubmenus() {
        const activeDept = reportFilterState.dept || '';
        const depts = Array.isArray(boot.accessibleDepts) ? boot.accessibleDepts : [];
        const onReportsTab = !document.getElementById('tab-panel-reports')?.classList.contains('hidden');

        const sel = document.getElementById('report-dept-filter');
        if (sel && sel.value !== activeDept) {
            sel.value = activeDept;
        }

        const countsByDept = {};
        REPORTS_DATA.forEach((rpt) => {
            const code = rpt.department?.code;
            if (!code) return;
            countsByDept[code] = (countsByDept[code] || 0) + 1;
        });

        const onPomecTab = !document.getElementById('tab-panel-pomec')?.classList.contains('hidden');
        document.querySelectorAll('.sidebar-dept-btn').forEach((btn) => {
            const code = btn.dataset.sidebarDept || '';
            // Di halaman POMEC, baris Engineering yang menyala.
            const active = (onReportsTab && code === activeDept) || (onPomecTab && code === 'ENG');

            // Toggle only state classes so the structural layout from Blade is preserved.
            [
                ['bg-navy/10', active],
                ['text-navy', active],
                ['font-semibold', active],
                ['text-slate-500', !active],
                ['font-medium', !active],
                ['hover:bg-slate-100/80', !active],
                ['hover:text-navy', !active],
            ].forEach(([cls, on]) => btn.classList.toggle(cls, on));

            const chipEl = btn.querySelector('.sidebar-dept-chip');
            if (chipEl) {
                chipEl.classList.toggle('bg-navy', active);
                chipEl.classList.toggle('text-white', active);
                chipEl.classList.toggle('bg-slate-100', !active);
                chipEl.classList.toggle('text-slate-500', !active);
            }

            const cEl = btn.querySelector('[data-sidebar-dept-count]');
            if (cEl) {
                cEl.textContent = code === '' ? String(REPORTS_DATA.length) : String(countsByDept[code] || 0);
                cEl.classList.toggle('bg-navy', active);
                cEl.classList.toggle('text-white', active);
                cEl.classList.toggle('bg-white', !active);
                cEl.classList.toggle('border', !active);
                cEl.classList.toggle('border-slate-200', !active);
                cEl.classList.toggle('text-slate-600', !active);
            }
        });

        const deptObj = activeDept ? depts.find((d) => d.code === activeDept) : (depts.length === 1 ? depts[0] : null);
        const multiDept = depts.length > 1;

        const badgeEl = document.getElementById('reports-active-dept-badge');
        const titleEl = document.getElementById('reports-active-dept-title');
        const subtitleEl = document.getElementById('reports-active-dept-subtitle');

        if (badgeEl) {
            badgeEl.textContent = deptObj ? deptObj.code : (IS_EN ? 'ALL DEPTS' : 'SEMUA DEPT');
        }
        if (titleEl) {
            titleEl.textContent = deptObj
                ? (IS_EN ? (deptObj.name_en || deptObj.name) : deptObj.name)
                : (isReportsOverview()
                    ? (IS_EN ? 'Department Overview' : 'Ringkasan Departemen')
                    : (IS_EN ? 'All Departmental Reports' : 'Semua Laporan Departemen'));
        }
        if (subtitleEl) {
            subtitleEl.textContent = activeDept && deptObj
                ? (deptObj.description || (IS_EN ? 'Focused report workspace & approval queue for this department.' : 'Ruang laporan, analitik, dan histori khusus departemen ini.'))
                : (multiDept
                    ? (isReportsOverview()
                        ? (IS_EN ? 'Pick a department to see its reports, charts, and history.' : 'Pilih departemen untuk melihat laporan, grafik, dan historinya.')
                        : (IS_EN ? 'Reports and history from every department.' : 'Daftar laporan dan histori seluruh departemen.'))
                    : (IS_EN ? 'Department report review and archive.' : 'Pemeriksaan dan arsip laporan departemen Anda.'));
        }
        const createBtn = document.getElementById('reports-dept-create-btn');
        const createLbl = document.getElementById('reports-dept-create-label');
        if (createBtn) {
            const matchingTpl = activeDept ? TEMPLATES_DATA.find((t) => t.department?.code === activeDept) : TEMPLATES_DATA[0];
            createBtn.classList.toggle('hidden', !matchingTpl);
            if (createLbl) {
                createLbl.textContent = activeDept && matchingTpl
                    ? `${IS_EN ? 'New ' : 'Buat Laporan '}${activeDept}`
                    : (IS_EN ? 'Create Report' : 'Buat Laporan');
            }
        }

        if (onReportsTab) {
            const url = new URL(window.location);
            if (activeDept) {
                url.searchParams.set('dept', activeDept);
            } else {
                url.searchParams.delete('dept');
            }
            window.history.replaceState({}, '', url);
        }
    }

    // Klik menu induk: buka ringkasan semua departemen; kalau sudah di sana, lipat/buka submenu.
    window.openReportsMenu = function () {
        const onReports = !document.getElementById('tab-panel-reports')?.classList.contains('hidden');
        if (onReports && !reportFilterState.dept && !reportFilterState.list) {
            window.switchWorkspaceTab('reports', { toggleSubmenu: true });
            return;
        }
        const hasSubmenu = !!document.getElementById('sidebar-reports-submenu');
        if (!hasSubmenu) {
            window.switchWorkspaceTab('reports');
            return;
        }
        window.selectReportDepartment('');
        window.switchWorkspaceTab('reports');
        window.scrollTo({ top: 0 });
    };

    window.selectReportDepartment = function (deptCode = '') {
        if (deptCode === 'ENG' && hasPomec()) {
            closeMobileSidebar();
            window.switchWorkspaceTab('pomec');
            window.scrollTo({ top: 0 });
            return;
        }
        // "Semua Departemen" selalu kembali ke ringkasan yang bersih.
        if (!deptCode) clearReportFilters();
        reportFilterState.dept = deptCode || '';
        const panelHidden = document.getElementById('tab-panel-reports')?.classList.contains('hidden');
        if (panelHidden) {
            window.switchWorkspaceTab('reports');
        }
        closeMobileSidebar();
        renderReportsTable();
        renderReportHistories();
    };

    window.createReportForActiveDept = function () {
        const activeDept = reportFilterState.dept || '';
        const tpl = activeDept
            ? TEMPLATES_DATA.find((t) => t.department?.code === activeDept)
            : TEMPLATES_DATA[0];
        window.openReportEditor(null, tpl ? tpl.id : null);
    };

    function setReportDeptFilter(deptCode) {
        const sel = document.getElementById('report-dept-filter');
        if (!sel) return false;
        const hasOption = Array.from(sel.options).some((o) => o.value === deptCode);
        if (!hasOption) return false;
        reportFilterState.dept = reportFilterState.dept === deptCode ? '' : deptCode;
        reportFilterState.list = true;
        sel.value = reportFilterState.dept;
        return true;
    }

    function formatDurationHours(hours) {
        if (!Number.isFinite(hours) || hours < 0) return '-';
        if (hours < 1) return `${Math.max(1, Math.round(hours * 60))} ${IS_EN ? 'min' : 'mnt'}`;
        if (hours < 48) return `${hours.toFixed(1).replace('.', IS_EN ? '.' : ',')} ${IS_EN ? 'hrs' : 'jam'}`;
        return `${(hours / 24).toFixed(1).replace('.', IS_EN ? '.' : ',')} ${IS_EN ? 'days' : 'hari'}`;
    }

    function renderReportsInsights(filtered) {
        const body = document.getElementById('reports-insights-body');
        if (!body) return;

        // Base set ignores the status filter so the doughnut always shows the full composition.
        const base = REPORTS_DATA.filter((rpt) => reportMatchesFilters(rpt, true));
        const countBy = (list, key) => list.filter((r) => r.status === key).length;

        // --- Mini cards ---
        const reviewedCount = countBy(base, 'reviewed');
        const approvalPct = base.length > 0 ? Math.round((reviewedCount / base.length) * 100) : 0;
        const actionable = base.filter((r) => canReviewReport(r) || (canEditReport(r) && r.status === 'returned'));
        const toReview = actionable.filter((r) => canReviewReport(r)).length;
        const toFix = actionable.length - toReview;

        const reviewDurations = base
            .filter((r) => r.status === 'reviewed' && r.submitted_at && r.reviewed_at)
            .map((r) => (new Date(r.reviewed_at) - new Date(r.submitted_at)) / 3600000)
            .filter((h) => Number.isFinite(h) && h >= 0);
        const avgHours = reviewDurations.length ? reviewDurations.reduce((a, b) => a + b, 0) / reviewDurations.length : NaN;

        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setText('ri-total', filtered.length);
        setText('ri-total-sub', `${IS_EN ? 'of' : 'dari'} ${REPORTS_DATA.length} ${IS_EN ? 'accessible reports' : 'laporan yang dapat diakses'}`);
        setText('ri-approval', `${approvalPct}%`);
        const bar = document.getElementById('ri-approval-bar');
        if (bar) bar.style.width = `${approvalPct}%`;
        setText('ri-action', actionable.length);
        setText('ri-action-sub', `${toReview} ${IS_EN ? 'to review' : 'perlu direview'} • ${toFix} ${IS_EN ? 'to revise' : 'perlu diperbaiki'}`);
        setText('ri-avg-review', reviewDurations.length ? formatDurationHours(avgHours) : '-');
        setText('ri-avg-review-sub', reviewDurations.length
            ? `${reviewDurations.length} ${IS_EN ? 'reviewed reports' : 'laporan disetujui'}`
            : (IS_EN ? 'no reviewed reports yet' : 'belum ada laporan disetujui'));

        // Skip heavy chart work while the charts section is collapsed.
        if (body.classList.contains('hidden')) {
            renderReportsActionQueue(actionable);
            return;
        }

        // --- Status doughnut ---
        const statusCanvas = document.getElementById('reports-chart-status');
        if (statusCanvas) {
            const counts = REPORT_STATUS_META.map((m) => countBy(base, m.key));
            const hasAny = counts.some((c) => c > 0);
            const activeStatus = reportFilterState.status;
            setText('reports-chart-status-center', activeStatus !== 'all' ? countBy(base, activeStatus) : base.length);

            upsertChart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: REPORT_STATUS_META.map((m) => m.label),
                    datasets: [{
                        data: hasAny ? counts : [1],
                        backgroundColor: hasAny
                            ? REPORT_STATUS_META.map((m) => (activeStatus === 'all' || activeStatus === m.key) ? m.color : `${m.color}33`)
                            : chartEmptyTrackColor(),
                        borderWidth: 0,
                        borderRadius: hasAny ? 4 : 0,
                        spacing: hasAny ? 2 : 0,
                        hoverOffset: hasAny ? 5 : 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '76%',
                    rotation: -90,
                    animation: doughnutAnimation(),
                    plugins: { legend: { display: false }, tooltip: { enabled: hasAny } },
                    onHover: (evt, els) => { evt.native.target.style.cursor = (hasAny && els.length) ? 'pointer' : 'default'; },
                    onClick: (evt, els) => {
                        if (!hasAny || !els.length) return;
                        window.setReportStatusFilter(REPORT_STATUS_META[els[0].index].key);
                    },
                },
            });

            const legendEl = document.getElementById('reports-chart-status-legend');
            if (legendEl) {
                legendEl.innerHTML = REPORT_STATUS_META.map((m, i) => {
                    const pct = base.length ? Math.round((counts[i] / base.length) * 100) : 0;
                    const active = activeStatus === m.key;
                    return `<button type="button" onclick="setReportStatusFilter('${m.key}')"
                        class="flex items-center justify-between gap-2 px-2 py-1 rounded-lg transition cursor-pointer ${active ? 'bg-slate-100 ring-1 ring-slate-300' : 'hover:bg-slate-50'}">
                        <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full" style="background:${m.color}"></span>${escapeHtml(m.label)}</span>
                        <span class="font-mono-num font-semibold text-navy">${counts[i]} <span class="text-slate-400 font-normal">(${pct}%)</span></span>
                    </button>`;
                }).join('');
            }
        }

        // --- 14-day trend (stacked by status group) ---
        const trendCanvas = document.getElementById('reports-chart-trend');
        if (trendCanvas) {
            const todayIso = formatWitaIsoDate(new Date());
            const dates = base.map((r) => formatWitaIsoDate(r.period_date)).filter(Boolean).sort();
            const latest = dates.length ? dates[dates.length - 1] : todayIso;
            // Window ends today; but if every report is older than 14 days, anchor on the latest report date
            // (and on future-dated reports when they exist) so the chart is never empty while data exists.
            const windowStart = shiftIsoDate(todayIso, -13);
            let endIso = todayIso;
            if (latest > todayIso) endIso = latest;
            else if (dates.length && latest < windowStart) endIso = latest;
            const days = Array.from({ length: 14 }, (_, i) => shiftIsoDate(endIso, i - 13));
            const groups = [
                { label: IS_EN ? 'Reviewed' : 'Disetujui', color: '#059669', match: (s) => s === 'reviewed' },
                { label: IS_EN ? 'Pending Review' : 'Menunggu Review', color: '#D97706', match: (s) => ['submitted', 'in_review'].includes(s) },
                { label: IS_EN ? 'Returned' : 'Perlu Revisi', color: '#F04E30', match: (s) => s === 'returned' },
                { label: 'Draft', color: '#CBD5E1', match: (s) => s === 'draft' },
            ];
            const activeDate = reportFilterState.date;
            setText('reports-chart-trend-range', `${formatWitaDateLabel(days[0], IS_EN)} – ${formatWitaDateLabel(days[13], IS_EN)}`);

            upsertChart(trendCanvas, {
                type: 'bar',
                data: {
                    labels: days.map((d) => new Intl.DateTimeFormat(IS_EN ? 'en-GB' : 'id-ID', { timeZone: 'Asia/Makassar', day: '2-digit', month: 'short' }).format(new Date(`${d}T12:00:00+08:00`))),
                    datasets: groups.map((g) => ({
                        label: g.label,
                        data: days.map((d) => base.filter((r) => formatWitaIsoDate(r.period_date) === d && g.match(r.status)).length),
                        backgroundColor: days.map((d) => (!activeDate || activeDate === d) ? g.color : `${g.color}40`),
                        borderRadius: { topLeft: 4, topRight: 4 },
                        stack: 'status',
                        barPercentage: 0.65,
                        categoryPercentage: 0.72,
                        maxBarThickness: 24,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: chartStagger(16, 200),
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' } } },
                        tooltip: {
                            callbacks: {
                                title: (items) => formatWitaDateLabel(days[items[0].dataIndex], IS_EN),
                                footer: (items) => `${IS_EN ? 'Total' : 'Total'}: ${items.reduce((a, it) => a + it.parsed.y, 0)} • ${IS_EN ? 'click to filter' : 'klik untuk filter'}`,
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } },
                        y: { stacked: true, beginAtZero: true, grid: { color: chartGridColor() }, border: { display: false }, ticks: { precision: 0, font: { size: 11 } } },
                    },
                    onHover: (evt, els) => { evt.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const iso = days[els[0].index];
                        reportFilterState.date = reportFilterState.date === iso ? '' : iso;
                        const dt = document.getElementById('report-date-filter');
                        if (dt) dt.value = reportFilterState.date;
                        renderReportsTable();
                        renderReportHistories();
                    },
                },
            });
        }

        // --- Department stacked horizontal bars ---
        const deptCanvas = document.getElementById('reports-chart-dept');
        const deptBox = deptCanvas?.parentElement.parentElement;
        deptBox?.classList.toggle('hidden', !!reportFilterState.dept);
        deptBox?.nextElementSibling?.classList.toggle('lg:col-span-3', !!reportFilterState.dept);
        if (deptCanvas) {
            const deptMap = new Map();
            base.forEach((r) => {
                const code = r.department?.code || '-';
                if (code === '-' || code === 'ANG') return;
                if (!deptMap.has(code)) deptMap.set(code, { code, name: IS_EN ? (r.department?.name_en || r.department?.name) : r.department?.name, items: [] });
                deptMap.get(code).items.push(r);
            });
            const depts = Array.from(deptMap.values()).sort((a, b) => b.items.length - a.items.length);
            const hasDept = depts.length > 0;

            // Grow the chart height with the number of departments so bars never get cramped.
            const wrap = deptCanvas.parentElement;
            if (wrap) wrap.style.height = `${Math.max(180, depts.length * 34 + 60)}px`;

            upsertChart(deptCanvas, {
                type: 'bar',
                data: {
                    labels: hasDept ? depts.map((d) => d.code) : [IS_EN ? 'No data' : 'Tidak ada data'],
                    datasets: REPORT_STATUS_META.map((m) => ({
                        label: m.label,
                        data: hasDept ? depts.map((d) => d.items.filter((r) => r.status === m.key).length) : [0],
                        backgroundColor: m.color,
                        borderRadius: 4,
                        stack: 'dept',
                        barThickness: 16,
                    })),
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: chartStagger(22, 180),
                    interaction: { mode: 'nearest', intersect: true },
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' } } },
                        tooltip: {
                            callbacks: {
                                title: (items) => {
                                    const d = depts[items[0].dataIndex];
                                    return d ? `${d.name || d.code} (${d.code})` : '';
                                },
                                footer: () => (IS_EN ? 'Click to filter department & status' : 'Klik untuk filter departemen & status'),
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, beginAtZero: true, grid: { color: chartGridColor() }, border: { display: false }, ticks: { precision: 0, font: { size: 11 } } },
                        y: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 11, weight: '600' }, color: chartStrongLabelColor() } },
                    },
                    onHover: (evt, els) => { evt.native.target.style.cursor = (hasDept && els.length) ? 'pointer' : 'default'; },
                    onClick: (evt, els) => {
                        if (!hasDept || !els.length) return;
                        const d = depts[els[0].index];
                        const st = REPORT_STATUS_META[els[0].datasetIndex]?.key;
                        const deptChanged = setReportDeptFilter(d.code);
                        if (st) {
                            reportFilterState.status = (reportFilterState.status === st && !deptChanged) ? 'all' : st;
                            applyReportStatusPills();
                        }
                        renderReportsTable();
                        renderReportHistories();
                    },
                },
            });
        }

        renderReportsActionQueue(actionable);
    }

    function shiftIsoDate(iso, deltaDays) {
        const d = new Date(`${iso}T12:00:00+08:00`);
        d.setUTCDate(d.getUTCDate() + deltaDays);
        return formatWitaIsoDate(d);
    }

    function renderReportsActionQueue(actionable) {
        const list = document.getElementById('reports-action-list');
        const countEl = document.getElementById('reports-action-count');
        if (countEl) countEl.textContent = actionable.length;
        if (!list) return;

        if (actionable.length === 0) {
            list.innerHTML = `<li class="py-8 text-center text-slate-400">${IS_EN ? 'All clear — nothing needs your action.' : 'Semua beres — tidak ada yang perlu ditindaklanjuti.'}</li>`;
            return;
        }

        const priority = { returned: 0, submitted: 1, in_review: 2 };
        const sorted = [...actionable].sort((a, b) =>
            (priority[a.status] ?? 9) - (priority[b.status] ?? 9) ||
            new Date(a.submitted_at || a.updated_at) - new Date(b.submitted_at || b.updated_at));

        list.innerHTML = sorted.slice(0, 12).map((r) => {
            const isFix = r.status === 'returned';
            const waitingFrom = r.submitted_at || r.updated_at;
            const waitHours = waitingFrom ? (Date.now() - new Date(waitingFrom)) / 3600000 : NaN;
            const btn = isFix
                ? `<button type="button" onclick="openReportEditor(${r.id})" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-crimson text-white hover:bg-crimson-dark transition cursor-pointer">${IS_EN ? 'Revise' : 'Perbaiki'}</button>`
                : `<button type="button" onclick="openReportDetail(${r.id})" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-navy text-white hover:bg-navy-light transition cursor-pointer">${T.review}</button>`;
            return `<li class="py-2.5 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono-num font-bold text-navy">${escapeHtml(r.report_code)}</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono-num text-[10px] font-semibold">${escapeHtml(r.department?.code || '')}</span>
                    </div>
                    <div class="text-slate-600 truncate max-w-[200px]">${escapeHtml(r.title)}</div>
                    <div class="text-[10px] font-mono-num ${isFix ? 'text-crimson-dark' : (waitHours > 24 ? 'text-amber-700 font-semibold' : 'text-slate-400')}">
                        ${isFix ? (IS_EN ? 'Returned for revision' : 'Dikembalikan untuk revisi') : `${IS_EN ? 'Waiting' : 'Menunggu'} ${formatDurationHours(waitHours)}`}
                    </div>
                </div>
                ${btn}
            </li>`;
        }).join('');
    }

    (function initReportsInsightsToggle() {
        const toggle = document.getElementById('reports-insights-toggle');
        const body = document.getElementById('reports-insights-body');
        if (!toggle || !body) return;
        const KEY = 'sbwm_reports_insights_hidden';
        const apply = (hidden) => {
            body.classList.toggle('hidden', hidden);
            toggle.textContent = hidden ? (IS_EN ? 'Show Charts' : 'Tampilkan Grafik') : (IS_EN ? 'Hide Charts' : 'Sembunyikan Grafik');
        };
        try { apply(localStorage.getItem(KEY) === '1'); } catch { apply(false); }
        toggle.addEventListener('click', () => {
            const hidden = !body.classList.contains('hidden');
            apply(hidden);
            try { localStorage.setItem(KEY, hidden ? '1' : '0'); } catch {}
            if (!hidden) renderReportsTable();
        });
    })();

    document.getElementById('report-search')?.addEventListener('input', (e) => {
        reportFilterState.search = e.target.value;
        renderReportsTable();
        renderReportHistories();
    });

    document.getElementById('report-dept-filter')?.addEventListener('change', (e) => {
        reportFilterState.dept = e.target.value;
        reportFilterState.list = true;
        renderReportsTable();
        renderReportHistories();
    });

    document.getElementById('report-period-filter')?.addEventListener('change', (e) => {
        reportFilterState.period = e.target.value;
        renderReportsTable();
        renderReportHistories();
    });

    document.getElementById('report-date-filter')?.addEventListener('change', (e) => {
        reportFilterState.date = e.target.value;
        renderReportsTable();
        renderReportHistories();
    });

    document.getElementById('history-action-filter')?.addEventListener('change', (e) => {
        historyActionFilter = e.target.value;
        renderReportHistories();
    });

    document.querySelectorAll('.report-status-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            reportFilterState.status = btn.dataset.status;
            applyReportStatusPills();
            renderReportsTable();
            renderReportHistories();
        });
    });

    window.jumpToReportsPreset = function ({ status = null, period = null, scrollToHistory = false } = {}) {
        reportFilterState.list = true;
        window.switchWorkspaceTab('reports');
        if (status !== null) {
            reportFilterState.status = status;
            applyReportStatusPills();
        }
        if (period !== null) {
            reportFilterState.period = period;
            const p = document.getElementById('report-period-filter');
            if (p) p.value = period;
        }
        renderReportsTable();
        renderReportHistories();
        if (scrollToHistory) {
            setTimeout(() => {
                document.getElementById('reports-history-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        }
    };

    window.resetReportFilters = function () {
        clearReportFilters();
        // Reset di daftar lengkap tetap di daftar, bukan melompat ke ringkasan.
        reportFilterState.list = true;
        renderReportsTable();
        renderReportHistories();
    };

    function clearReportFilters() {
        reportFilterState = { search: '', dept: '', period: '', date: '', status: 'all', list: false };
        historyActionFilter = '';
        const s = document.getElementById('report-search');
        const ts = document.getElementById('topbar-quick-search');
        const d = document.getElementById('report-dept-filter');
        const p = document.getElementById('report-period-filter');
        const dt = document.getElementById('report-date-filter');
        const ha = document.getElementById('history-action-filter');
        if (s) s.value = '';
        if (ts) ts.value = '';
        if (d) d.value = '';
        if (p) p.value = '';
        if (dt) dt.value = '';
        if (ha) ha.value = '';
        applyReportStatusPills();
    }

    // -------------------------------------------------------------------------
    // 4. Dynamic Per-Department Table Editor
    // -------------------------------------------------------------------------
    function getSelectedEditorTemplate() {
        const tplId = Number(document.getElementById('editor-template-select')?.value);
        return TEMPLATES_DATA.find((t) => t.id === tplId) || TEMPLATES_DATA[0];
    }

    function renderEditorFooter(cols) {
        const footEl = document.getElementById('editor-table-foot');
        if (!footEl) return;
        const hasSum = cols.some((c) => c.sum);
        if (!hasSum || editorRows.length === 0) {
            footEl.innerHTML = '';
            return;
        }
        footEl.innerHTML = `<tr>
            <td class="py-3 px-3 text-[11px] uppercase text-slate-500">${T.total}</td>
            ${cols.map((c) => {
                if (!c.sum) return '<td class="py-3 px-3"></td>';
                const total = editorRows.reduce((acc, r) => acc + (parseFloat(r[c.key]) || 0), 0);
                const formatted = c.type === 'currency'
                    ? 'Rp ' + total.toLocaleString('id-ID')
                    : total.toLocaleString('id-ID');
                return `<td class="py-3 px-3 text-right font-mono-num text-navy whitespace-nowrap">${formatted}</td>`;
            }).join('')}
            <td></td>
        </tr>`;
    }

    function renderDynamicEditorTable() {
        const tpl = getSelectedEditorTemplate();
        if (!tpl) return;

        document.getElementById('editor-template-subtitle').textContent =
            `${tpl.department?.name || ''} — ${IS_EN ? (tpl.description_en || tpl.description) : tpl.description}`;

        const cols = tpl.columns_schema || [];
        const rowBadge = document.getElementById('editor-row-count');
        if (rowBadge) rowBadge.textContent = `${editorRows.length} ${T.rows}`;

        document.getElementById('editor-table-head').innerHTML = `<tr>
            <th class="py-3 px-3 w-10">#</th>
            ${cols.map((c) => {
                const isNum = ['number', 'currency'].includes(c.type);
                const reqMark = c.required ? ' <span class="text-crimson">*</span>' : '';
                return `<th class="py-3 px-3 whitespace-nowrap ${isNum ? 'text-right min-w-[125px]' : 'min-w-[145px]'}">${escapeHtml(IS_EN ? (c.label_en || c.label) : c.label)}${reqMark}</th>`;
            }).join('')}
            <th class="py-3 px-3 w-20 text-center"></th>
        </tr>`;

        document.getElementById('editor-table-body').innerHTML = editorRows.map((row, rIdx) => `<tr>
            <td class="py-2.5 px-3 font-mono-num text-slate-400">${rIdx + 1}</td>
            ${cols.map((c) => {
                const val = row[c.key] ?? '';
                if (c.type === 'select') {
                    return `<td class="py-2.5 px-2.5 min-w-[145px]">
                        <select onchange="updateEditorCell(${rIdx}, '${c.key}', this.value)" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs">
                            ${(c.options || []).map((opt) => `<option value="${escapeHtml(opt)}" ${val === opt ? 'selected' : ''}>${escapeHtml(opt)}</option>`).join('')}
                        </select>
                    </td>`;
                }
                const isNum = ['number', 'currency'].includes(c.type);
                return `<td class="py-2.5 px-2.5 ${isNum ? 'min-w-[125px]' : 'min-w-[145px]'}">
                    <input type="${isNum ? 'number' : 'text'}" step="any" ${isNum ? 'min="0"' : ''} value="${escapeHtml(val)}"
                        data-editor-row="${rIdx}" data-editor-key="${escapeHtml(c.key)}"
                        oninput="updateEditorCell(${rIdx}, '${c.key}', this.value, ${isNum})"
                        class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal ${isNum ? 'text-right font-mono-num' : ''}">
                </td>`;
            }).join('')}
            <td class="py-2.5 px-2 text-center whitespace-nowrap">
                <button type="button" onclick="duplicateEditorRow(${rIdx})" class="text-slate-400 hover:text-teal px-1.5 py-1 cursor-pointer" title="${IS_EN ? 'Duplicate row' : 'Duplikasi baris'}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
                <button type="button" onclick="removeEditorRow(${rIdx})" class="text-crimson hover:text-crimson-dark font-bold px-1.5 py-1 cursor-pointer" title="${T.del}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </td>
        </tr>`).join('');

        renderEditorFooter(cols);
    }

    window.duplicateEditorRow = function (idx) {
        if (!editorRows[idx]) return;
        editorRows.splice(idx + 1, 0, JSON.parse(JSON.stringify(editorRows[idx])));
        renderDynamicEditorTable();
    };

    window.autoFillEditorTitle = function (force = false) {
        if (!force && editorTitleTouched) return;
        const tpl = getSelectedEditorTemplate();
        const titleEl = document.getElementById('editor-title');
        const dateEl = document.getElementById('editor-period-date');
        if (!tpl || !titleEl) return;
        const tplName = IS_EN ? (tpl.name_en || tpl.name) : tpl.name;
        const dateStr = dateEl?.value ? formatWitaDateLabel(dateEl.value, IS_EN) : '';
        titleEl.value = dateStr ? `${tplName} — ${dateStr}` : tplName;
        if (force) editorTitleTouched = false;
    };

    window.clearEditorAttachment = function (evt) {
        if (evt) { evt.preventDefault(); evt.stopPropagation(); }
        const fileEl = document.getElementById('editor-attachment');
        const lblEl = document.getElementById('editor-attachment-label');
        const clrEl = document.getElementById('editor-attachment-clear');
        if (fileEl) fileEl.value = '';
        if (lblEl) lblEl.textContent = IS_EN ? 'Click to choose PDF, Excel, CSV, or Image' : 'Klik untuk pilih PDF, Excel, CSV, atau Gambar';
        clrEl?.classList.add('hidden');
    };

    document.getElementById('editor-title')?.addEventListener('input', () => {
        editorTitleTouched = true;
    });
    document.getElementById('editor-period-date')?.addEventListener('change', () => {
        if (!document.getElementById('editor-report-id')?.value) window.autoFillEditorTitle(false);
    });
    document.getElementById('editor-attachment')?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        const lblEl = document.getElementById('editor-attachment-label');
        const clrEl = document.getElementById('editor-attachment-clear');
        if (!file) return window.clearEditorAttachment();
        const kb = (file.size / 1024).toFixed(0);
        if (lblEl) lblEl.textContent = `${file.name} (${kb} KB)`;
        clrEl?.classList.remove('hidden');
    });

    window.updateEditorCell = function (rowIdx, key, value, isNum = false) {
        if (!editorRows[rowIdx]) return;
        editorRows[rowIdx][key] = isNum ? (value === '' ? 0 : Number(value)) : value;
        if (isNum) {
            const tpl = getSelectedEditorTemplate();
            renderEditorFooter(tpl?.columns_schema || []);
        }
    };

    window.addEditorRow = function () {
        const tpl = getSelectedEditorTemplate();
        const emptyRow = {};
        (tpl?.columns_schema || []).forEach((c) => {
            emptyRow[c.key] = c.type === 'select' ? (c.options?.[0] || '') : (['number', 'currency'].includes(c.type) ? 0 : '');
        });
        editorRows.push(emptyRow);
        renderDynamicEditorTable();
    };

    window.removeEditorRow = function (idx) {
        if (editorRows.length <= 1) {
            window.showToast(T.minOneRow, 'error');
            return;
        }
        editorRows.splice(idx, 1);
        renderDynamicEditorTable();
    };

    window.loadTemplateDefaultRows = function () {
        const tpl = getSelectedEditorTemplate();
        editorRows = JSON.parse(JSON.stringify(tpl?.default_rows || []));
        if (editorRows.length === 0) window.addEditorRow();
        else renderDynamicEditorTable();
    };

    document.getElementById('editor-template-select')?.addEventListener('change', () => {
        window.loadTemplateDefaultRows();
        if (!document.getElementById('editor-report-id')?.value) window.autoFillEditorTitle(false);
    });

    window.openReportEditor = function (reportId = null, templateId = null) {
        // Laporan POMEC diperbaiki dari detailnya (unggah ulang), bukan lewat form.
        if (reportId && REPORTS_DATA.find((r) => r.id === reportId)?.pomec_import_id) return window.openReportDetail(reportId);
        if (!boot.manualReports) return;
        showInlineError('editor-form-error', '');
        const select = document.getElementById('editor-template-select');
        document.getElementById('editor-report-id').value = reportId || '';
        window.clearEditorAttachment();
        const existingAtt = document.getElementById('editor-existing-attachment');
        const revBanner = document.getElementById('editor-revision-banner');
        const revText = document.getElementById('editor-revision-note-text');

        if (reportId) {
            const rpt = REPORTS_DATA.find((r) => r.id === reportId);
            if (!rpt) return;
            editorTitleTouched = true;
            document.getElementById('editor-modal-title').textContent = `${T.editReport} — ${rpt.report_code}`;
            select.value = rpt.report_template_id;
            select.disabled = true;
            document.getElementById('editor-title').value = rpt.title;
            document.getElementById('editor-period-type').value = rpt.period_type;
            document.getElementById('editor-period-date').value = formatWitaIsoDate(rpt.period_date);
            document.getElementById('editor-summary-notes').value = rpt.summary_notes || '';
            if (revBanner && revText) {
                const showRev = rpt.status === 'returned' && rpt.review_note;
                revText.textContent = showRev ? rpt.review_note : '';
                revBanner.classList.toggle('hidden', !showRev);
            }
            if (existingAtt) {
                existingAtt.textContent = rpt.attachment_name ? `${T.downloadAttachment}: ${rpt.attachment_name}` : '';
                existingAtt.classList.toggle('hidden', !rpt.attachment_name);
            }
            editorRows = JSON.parse(JSON.stringify(rpt.rows_data || []));
            renderDynamicEditorTable();
        } else {
            editorTitleTouched = false;
            document.getElementById('editor-modal-title').textContent = T.createReport;
            select.disabled = false;
            if (templateId) select.value = templateId;
            document.getElementById('editor-period-type').value = 'daily';
            document.getElementById('editor-period-date').value = formatWitaIsoDate(new Date());
            document.getElementById('editor-summary-notes').value = '';
            revBanner?.classList.add('hidden');
            if (existingAtt) existingAtt.classList.add('hidden');
            window.loadTemplateDefaultRows();
            window.autoFillEditorTitle(false);
        }

        window.openModal('modal-report-editor');
    };

    window.submitReportForm = async function (intent) {
        showInlineError('editor-form-error', '');
        const reportId = document.getElementById('editor-report-id').value;
        const title = document.getElementById('editor-title').value.trim();
        if (!title) {
            showInlineError('editor-form-error', T.titleRequired);
            document.getElementById('editor-title')?.focus();
            return;
        }
        if (editorRows.length === 0) {
            showInlineError('editor-form-error', T.minOneRow);
            return;
        }

        const tpl = getSelectedEditorTemplate();
        const cols = tpl?.columns_schema || [];
        for (let r = 0; r < editorRows.length; r++) {
            for (const c of cols) {
                if (!c.required) continue;
                const val = editorRows[r][c.key];
                if (val === null || val === undefined || String(val).trim() === '') {
                    const colName = IS_EN ? (c.label_en || c.label) : c.label;
                    showInlineError('editor-form-error', IS_EN
                        ? `Row ${r + 1}: "${colName}" is required.`
                        : `Baris ${r + 1}: kolom "${colName}" wajib diisi.`);
                    document.querySelector(`[data-editor-row="${r}"][data-editor-key="${c.key}"]`)?.focus();
                    return;
                }
            }
        }

        const draftBtn = document.getElementById('editor-save-draft-btn');
        const submitBtn = document.getElementById('editor-submit-btn');
        if (draftBtn) draftBtn.disabled = true;
        if (submitBtn) submitBtn.disabled = true;

        const fd = new FormData();
        fd.append('report_template_id', document.getElementById('editor-template-select').value);
        fd.append('title', title);
        fd.append('period_type', document.getElementById('editor-period-type').value);
        fd.append('period_date', document.getElementById('editor-period-date').value);
        fd.append('summary_notes', document.getElementById('editor-summary-notes').value);
        fd.append('rows_data', JSON.stringify(editorRows));
        fd.append('intent', intent);

        const fileInput = document.getElementById('editor-attachment');
        if (fileInput.files[0]) fd.append('attachment', fileInput.files[0]);

        const url = reportId ? `/reports/${reportId}` : '/reports';
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) {
                showInlineError('editor-form-error', extractErrorMessage(data, T.saveFailed));
                return;
            }
            window.showToast(data.message, 'success');
            if (data.report) upsertReportInState(data.report);
            window.closeModal('modal-report-editor');
            window.switchWorkspaceTab('reports');
            // Dari ringkasan, langsung buka departemen laporan yang baru disimpan.
            if (isReportsOverview() && data.report?.department?.code) window.selectReportDepartment(data.report.department.code);
        } catch {
            showInlineError('editor-form-error', T.networkError);
        } finally {
            if (draftBtn) draftBtn.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
        }
    };

    // -------------------------------------------------------------------------
    // 5. Report Detail, HOD Review & Draft Deletion
    // -------------------------------------------------------------------------
    window.openReportDetail = function (reportId) {
        const rpt = REPORTS_DATA.find((r) => r.id === reportId);
        if (!rpt) return;

        document.getElementById('detail-code-badge').textContent = `${rpt.report_code} • ${rpt.template?.code || ''}`;
        document.getElementById('detail-title').textContent = rpt.title;

        const cols = rpt.template?.columns_schema || [];
        const rows = rpt.rows_data || [];
        const deptName = IS_EN ? (rpt.department?.name_en || rpt.department?.name) : rpt.department?.name;

        const tableHtml = `
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full min-w-[640px] text-left border-collapse text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase">
                        <tr>
                            <th class="py-3 px-4 w-10">#</th>
                            ${cols.map((c) => `<th class="py-3 px-4 ${['number', 'currency'].includes(c.type) ? 'text-right' : ''}">${escapeHtml(IS_EN ? (c.label_en || c.label) : c.label)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        ${rows.map((r, idx) => `
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 px-4 font-mono-num text-slate-400">${idx + 1}</td>
                                ${cols.map((c) => {
                                    let val = r[c.key] ?? '';
                                    if (c.type === 'date' && val) val = formatWitaDateLabel(val, IS_EN);
                                    else if (c.type === 'currency' && val !== null && val !== '' && !isNaN(val)) val = 'Rp ' + Math.round(Number(val)).toLocaleString('id-ID');
                                    else if (c.type === 'number' && !isNaN(val)) val = Number(val).toLocaleString('id-ID');
                                    return `<td class="py-3 px-4 ${['number', 'currency'].includes(c.type) ? 'text-right font-mono-num font-medium whitespace-nowrap' : ''}">${escapeHtml(val)}</td>`;
                                }).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        const historiesHtml = (rpt.histories || []).map((h) => `
            <div class="flex items-start justify-between gap-2 text-xs py-1.5 border-b border-slate-100 last:border-0">
                <div>
                    <span class="font-semibold text-navy">${escapeHtml(h.user?.name || 'System')}</span>
                    <span class="text-slate-500">• ${escapeHtml((h.action || '').toUpperCase())}</span>
                    ${h.note ? `<div class="text-slate-600 mt-0.5">${escapeHtml(h.note)}</div>` : ''}
                </div>
                <span class="font-mono-num text-[11px] text-slate-400 shrink-0">${escapeHtml(formatWita(h.created_at, true))}</span>
            </div>
        `).join('');

        const hodReviewBox = canReviewReport(rpt) ? `
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-3">
                <div class="text-xs font-bold text-navy uppercase tracking-wider">${T.hodVerification}</div>
                <div id="hod-review-error" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-crimson-dark"></div>
                <textarea id="hod-review-note" rows="2" placeholder="${escapeHtml(T.reviewPlaceholder)}"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-amber-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/30"></textarea>
                <div class="flex flex-wrap gap-2">
                    ${rpt.status === 'submitted' ? `
                        <button type="button" onclick="submitHodReview(${rpt.id}, 'in_review')" class="px-4 py-2 rounded-xl bg-teal hover:bg-teal-light text-white text-xs font-semibold cursor-pointer">
                            ${IS_EN ? 'Start Review (Stage 3)' : 'Mulai Pemeriksaan (Review)'}
                        </button>` : ''}
                    <button type="button" onclick="submitHodReview(${rpt.id}, 'reviewed')" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold cursor-pointer">
                        ${T.markReviewed}
                    </button>
                    <button type="button" onclick="submitHodReview(${rpt.id}, 'returned')" class="px-4 py-2 rounded-xl bg-crimson hover:bg-crimson-dark text-white text-xs font-semibold cursor-pointer">
                        ${T.returnRevision}
                    </button>
                </div>
            </div>
        ` : '';

        // Laporan POMEC: isi dari Excel. Staf mengirim draft atau mengunggah ulang perbaikan.
        const canInputRpt = CURRENT_USER.role === 'it_admin' || (CURRENT_USER.inputtableDepartmentIds || []).includes(rpt.department_id);
        const pomecBtn = (action, label, tone) => `<button type="button" onclick="pomecReportAction(${rpt.id}, '${action}')" class="px-4 py-2 rounded-xl text-xs font-semibold transition cursor-pointer ${tone}">${label}</button>`;
        const pomecActions = rpt.pomec_import_id ? `
            <div class="flex flex-wrap items-center gap-2 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80">
                <span class="text-xs text-slate-600 mr-auto">${IS_EN ? 'Data from the POMEC Excel upload.' : 'Data dari unggahan Excel POMEC.'}</span>
                ${pomecBtn('view', IS_EN ? 'View in POMEC' : 'Lihat di POMEC', 'border border-slate-200 bg-white text-navy hover:bg-slate-100')}
                ${canInputRpt && rpt.status === 'draft' ? pomecBtn('delete', IS_EN ? 'Delete Draft' : 'Hapus Draft', 'border border-rose-200 bg-white text-crimson-dark hover:bg-rose-50') : ''}
                ${canInputRpt && rpt.status === 'draft' ? pomecBtn('submit', IS_EN ? 'Submit to HOD' : 'Kirim ke HOD', 'bg-navy hover:bg-navy-light text-white') : ''}
                ${canInputRpt && rpt.status === 'returned' ? pomecBtn('reupload', IS_EN ? 'Re-upload Correction' : 'Unggah Ulang Perbaikan', 'bg-navy hover:bg-navy-light text-white') : ''}
            </div>` : '';

        const stageOrder = { draft: 1, submitted: 2, in_review: 3, returned: 4, reviewed: 5 };
        const currentStep = stageOrder[rpt.status] || 1;
        const stepCls = (stepIdx, activeTone) => {
            if (rpt.status === 'returned' && stepIdx === 4) return 'bg-rose-50 border-rose-300 text-crimson-dark font-bold';
            if (currentStep > stepIdx || (currentStep === 5 && stepIdx <= 5)) return 'bg-emerald-50 border-emerald-200 text-emerald-700 font-semibold';
            if (currentStep === stepIdx) return activeTone;
            return 'bg-slate-50 border-slate-200 text-slate-400';
        };
        const workflowStepperHtml = `
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-[11px]">
                <div class="px-3 py-2 rounded-xl border ${stepCls(1, 'bg-navy text-white border-navy font-bold')}">1. Draft <span class="block text-[10px] opacity-80">Admin / Staff</span></div>
                <div class="px-3 py-2 rounded-xl border ${stepCls(2, 'bg-amber-50 border-amber-300 text-amber-800 font-bold')}">2. Submitted <span class="block text-[10px] opacity-80">Admin / Staff</span></div>
                <div class="px-3 py-2 rounded-xl border ${stepCls(3, 'bg-cyan-50 border-cyan-300 text-teal font-bold')}">3. Review <span class="block text-[10px] opacity-80">HOD</span></div>
                <div class="px-3 py-2 rounded-xl border ${stepCls(4, 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold')}">${rpt.status === 'returned' ? '4A. Returned / Revision' : '4B. Reviewed'} <span class="block text-[10px] opacity-80">HOD</span></div>
                <div class="px-3 py-2 rounded-xl border ${rpt.status === 'reviewed' ? 'bg-navy text-white border-navy font-bold' : 'bg-slate-50 border-slate-200 text-slate-400'}">5. Monitoring <span class="block text-[10px] opacity-80">Management</span></div>
            </div>
        `;

        document.getElementById('detail-body').innerHTML = `
            ${workflowStepperHtml}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80">
                <div><div class="text-slate-400">${T.department}</div><div class="font-semibold text-navy">${escapeHtml(deptName)}</div></div>
                <div><div class="text-slate-400">${T.period}</div><div class="font-mono-num font-semibold text-navy">${escapeHtml(formatWitaDateLabel(rpt.period_date, IS_EN))} (${escapeHtml(rpt.period_type)})</div></div>
                <div><div class="text-slate-400">${T.author}</div><div class="font-semibold text-navy">${escapeHtml(rpt.creator?.name || '-')}</div></div>
                <div><div class="text-slate-400">Status</div><div>${statusBadgeHtml(rpt.status)}</div></div>
            </div>

            ${tableHtml}

            ${rpt.summary_notes ? `<div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs"><strong class="text-navy">${T.summaryNotes}:</strong> ${escapeHtml(rpt.summary_notes)}</div>` : ''}
            ${rpt.review_note ? `<div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs"><strong class="text-navy">${T.reviewNotes}:</strong> ${escapeHtml(rpt.review_note)}</div>` : ''}
            ${rpt.attachment_name ? `<div class="text-xs"><a href="/reports/${rpt.id}/attachment" class="inline-flex items-center gap-1.5 font-semibold text-teal hover:underline"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>${T.downloadAttachment}: ${escapeHtml(rpt.attachment_name)}</a></div>` : ''}

            ${pomecActions}
            ${hodReviewBox}

            <div class="border border-slate-200/80 rounded-2xl p-4 space-y-2">
                <div class="text-xs font-bold text-navy uppercase tracking-wider">${T.workflowHistory}</div>
                ${historiesHtml || '<div class="text-xs text-slate-400">-</div>'}
            </div>
        `;

        const detailFooter = document.getElementById('detail-footer');
        if (detailFooter) {
            detailFooter.innerHTML = `
                <div class="flex flex-wrap gap-2">
                    <a href="/reports/${rpt.id}/export/excel" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-navy hover:bg-slate-100 transition">Export Excel (.xlsx)</a>
                    <a href="/reports/${rpt.id}/export/pdf" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-crimson-dark hover:bg-rose-50 transition">Export PDF</a>
                </div>
                <button type="button" onclick="closeModal('modal-report-detail')" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">${T.close}</button>
            `;
        }

        window.openModal('modal-report-detail');
    };

    window.submitHodReview = async function (reportId, decision) {
        showInlineError('hod-review-error', '');
        const note = document.getElementById('hod-review-note')?.value || '';
        try {
            const res = await fetch(`/reports/${reportId}/review`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: JSON.stringify({ decision, review_note: note }),
            });
            const data = await res.json();
            if (!res.ok) {
                showInlineError('hod-review-error', extractErrorMessage(data, T.saveFailed));
                return;
            }
            window.showToast(data.message, 'success');
            if (data.report) upsertReportInState(data.report);
            if (decision === 'in_review') {
                window.openReportDetail(reportId);
            } else {
                window.closeModal('modal-report-detail');
            }
        } catch {
            showInlineError('hod-review-error', T.networkError);
        }
    };

    window.openDeleteDraftModal = function (reportId, reportCode) {
        document.getElementById('delete-report-id').value = reportId;
        document.getElementById('delete-report-label').textContent = reportCode;
        window.openModal('modal-delete-report');
    };

    window.confirmDeleteDraft = async function () {
        const id = Number(document.getElementById('delete-report-id').value);
        try {
            const res = await fetch(`/reports/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
            REPORTS_DATA = REPORTS_DATA.filter((r) => r.id !== id);
            if (data.pomec_import_id) removePomecImport(data.pomec_import_id);
            renderReportsTable();
            refreshKpis();
            window.showToast(data.message, 'success');
            window.closeModal('modal-delete-report');
        } catch {
            window.showToast(T.networkError, 'error');
        }
    };

    // -------------------------------------------------------------------------
    // 6. POMEC: satu bulan + satu utilitas terpilih, unggah, preview & simpan
    // -------------------------------------------------------------------------
    const pomecRoot = document.getElementById('pomec-root');
    // Dari PomecRecord::TYPES (label, satuan, ada biaya atau tidak).
    const POMEC_TYPES = pomecRoot ? JSON.parse(pomecRoot.dataset.types) : {};
    // Warna melekat pada utilitas; rincian memakai gradasi warna yang sama (lolos validator palet, terang & gelap).
    const pomecColor = (type) => ({ electricity: '#D97706', water: isDarkTheme() ? '#3B82F6' : '#2563EB', gas: '#A21CAF', solar: '#059669' })[type];
    const pomecShades = (type) => ({
        electricity: isDarkTheme() ? ['#D97706', '#FCD34D'] : ['#D97706', '#92400E'],
        water: isDarkTheme() ? ['#1D4ED8', '#60A5FA', '#DBEAFE'] : ['#7CB0F5', '#2563EB', '#16306E'],
    })[type];
    // Hari disebut menonjol kalau minimal 25% di atas rata-rata.
    const POMEC_HIGH = 1.25;
    // Gas dipakai per ganti tabung dan solar sesekali: per hari tidak terbaca, jadi digambar mingguan.
    const POMEC_SPARSE = ['gas', 'solar'];
    const pomecViewFor = (type) => (pomecView === 'day' && POMEC_SPARSE.includes(type) ? 'week' : pomecView);
    const pomecPeriod = (view) => ({ day: IS_EN ? 'Daily' : 'Harian', week: IS_EN ? 'Weekly' : 'Mingguan', month: IS_EN ? 'Monthly' : 'Bulanan' })[view];
    const pomecLabel = (type) => POMEC_TYPES[type]?.[IS_EN ? 'en' : 'id'] || type;
    const pomecUnit = (type) => POMEC_TYPES[type]?.unit || '';
    const pomecQty = (v) => Number(v || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const pomecRupiah = (v) => (v === null || v === undefined ? '-' : `Rp ${Math.round(Number(v)).toLocaleString('id-ID')}`);
    const pomecSum = (rows, key) => rows.reduce((s, r) => s + Number(r[key] || 0), 0);
    const pomecMonthOf = (rec) => formatWitaIsoDate(rec.reading_date).slice(0, 7);
    const pomecMonthName = (month) => (month
        ? new Intl.DateTimeFormat(IS_EN ? 'en-GB' : 'id-ID', { timeZone: 'Asia/Makassar', month: 'long', year: 'numeric' }).format(new Date(`${month}-15T12:00:00+08:00`))
        : '-');

    // { 'YYYY-MM-DD': total pemakaian } dari sekumpulan baris.
    function pomecDaily(rows) {
        return rows.reduce((days, r) => {
            const d = formatWitaIsoDate(r.reading_date);
            days[d] = (days[d] || 0) + Number(r.total_usage || 0);
            return days;
        }, {});
    }

    function pomecDailyAverage(rows) {
        const days = Object.keys(pomecDaily(rows)).length;
        return days ? pomecSum(rows, 'total_usage') / days : 0;
    }

    function pomecPrevMonth(month) {
        const [y, m] = month.split('-').map(Number);
        return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
    }

    // Dibandingkan per hari supaya bulan yang belum lengkap tidak terlihat "turun".
    function pomecTrend(rows, type) {
        const prev = pomecPrevMonth(pomecMonth);
        const before = pomecDailyAverage(POMEC_RECORDS.filter((r) => r.utility_type === type && pomecMonthOf(r) === prev));
        const name = pomecMonthName(prev);
        // Tanpa data bulan lalu tidak ada yang perlu ditampilkan.
        if (!before) return { text: '', tone: '' };
        const pct = Math.round(((pomecDailyAverage(rows) - before) / before) * 1000) / 10;
        if (!pct) {
            return { text: IS_EN ? `Same per day as ${name}` : `Sama per hari dengan ${name}`, tone: 'text-slate-500' };
        }
        const up = pct > 0;
        const word = IS_EN ? (up ? 'up' : 'down') : (up ? 'naik' : 'turun');
        return {
            text: `${up ? '▲' : '▼'} ${word} ${pomecQty(Math.abs(pct))}% ${IS_EN ? 'per day vs' : 'per hari dari'} ${name}`,
            tone: up ? 'text-crimson-dark' : 'text-emerald-700',
        };
    }

    function renderPomecStats(rows) {
        const unit = pomecUnit(pomecType);
        const stats = document.getElementById('pomec-stats');
        const meters = document.getElementById('pomec-meters');
        stats.classList.toggle('hidden', !rows.length);

        const days = Object.entries(pomecDaily(rows));
        const total = pomecSum(rows, 'total_usage');
        const [peakDate, peak] = days.reduce((max, day) => (day[1] > max[1] ? day : max), ['', -1]);
        const wbpShare = total ? Math.round((pomecSum(rows, 'wbp_value') / total) * 1000) / 10 : 0;
        const items = [[
            IS_EN ? 'Monthly total' : 'Total bulan ini',
            `${pomecQty(total)} ${unit}`,
            pomecType === 'electricity'
                ? `${IS_EN ? 'Peak hours (WBP)' : 'Beban puncak (WBP)'} ${pomecQty(wbpShare)}%`
                : `${days.length} ${IS_EN ? 'days recorded' : 'hari tercatat'}`,
        ]];

        if (POMEC_TYPES[pomecType].cost) {
            const priced = rows.filter((r) => r.cost !== null);
            const cost = pomecSum(priced, 'cost');
            const usage = pomecSum(priced, 'total_usage');
            items.push([
                IS_EN ? 'Cost before tax' : 'Biaya sebelum pajak',
                priced.length ? pomecRupiah(cost) : '-',
                priced.length && usage ? `± ${pomecRupiah(cost / usage)} ${IS_EN ? 'per' : 'per'} ${unit}` : (IS_EN ? 'Not in this data' : 'Tidak ada di data ini'),
            ]);
        } else {
            const used = days.filter(([, v]) => v > 0).length;
            items.push([IS_EN ? 'Days with usage' : 'Hari ada pemakaian', `${used} ${IS_EN ? 'days' : 'hari'}`, `${days.length - used} ${IS_EN ? 'days with none' : 'hari tanpa pemakaian'}`]);
        }

        const trend = rows.length ? pomecTrend(rows, pomecType) : { text: '', tone: '' };
        items.push(
            [IS_EN ? 'Average per day' : 'Rata-rata per hari', `${pomecQty(pomecDailyAverage(rows))} ${unit}`, trend.text, trend.tone],
            [IS_EN ? 'Highest day' : 'Hari tertinggi', `${pomecQty(peak)} ${unit}`, peakDate ? formatWitaDateLabel(peakDate, IS_EN) : ''],
        );

        stats.innerHTML = items.map(([label, value, hint, tone = 'text-slate-400']) => `<div>
            <dt class="text-[11px] text-slate-500">${label}</dt>
            <dd class="text-base font-heading font-bold font-mono-num text-navy">${value}</dd>
            <dd class="text-[11px] ${tone}">${hint}</dd>
        </div>`).join('');

        // Rincian per meter hanya berguna kalau meternya lebih dari satu.
        const byMeter = [...new Set(rows.map((r) => r.meter_name))].sort().map((name) => {
            const own = rows.filter((r) => r.meter_name === name);
            return { name, usage: pomecSum(own, 'total_usage'), cost: own.some((r) => r.cost !== null) ? pomecSum(own, 'cost') : null };
        });
        meters.classList.toggle('hidden', byMeter.length < 2);
        // Warna bar meter air sama dengan warnanya di grafik.
        const shades = pomecType === 'water' ? pomecShades(pomecType) : [pomecColor(pomecType)];
        meters.innerHTML = byMeter.map((m, i) => {
            const pct = total ? Math.round((m.usage / total) * 1000) / 10 : 0;
            return `<div class="space-y-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs">
                    <span class="font-semibold text-navy">${escapeHtml(m.name)} <span class="font-normal text-slate-400">${pomecQty(pct)}%</span></span>
                    <span class="font-mono-num text-slate-600">${pomecQty(m.usage)} ${unit}${m.cost === null ? '' : ` · ${pomecRupiah(m.cost)}`}</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width: ${pct}%; background-color: ${shades[i % shades.length]}"></div></div>
            </div>`;
        }).join('');
    }

    function pomecMonths() {
        return [...new Set(POMEC_RECORDS.map(pomecMonthOf))].sort().reverse();
    }

    const pomecReportOf = (importId) => REPORTS_DATA.find((r) => r.pomec_import_id === importId);
    const POMEC_STATUS = {
        draft: [IS_EN ? 'Draft' : 'Draft', 'bg-slate-100 text-slate-600 border-slate-200'],
        submitted: [IS_EN ? 'Awaiting HOD' : 'Menunggu HOD', 'bg-amber-50 text-amber-800 border-amber-200'],
        in_review: [IS_EN ? 'In review' : 'Diperiksa', 'bg-cyan-50 text-teal border-cyan-200'],
        returned: [IS_EN ? 'Returned' : 'Dikembalikan', 'bg-rose-50 text-crimson-dark border-rose-200'],
        reviewed: [IS_EN ? 'Approved' : 'Disetujui', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    };
    const pomecStatusBadge = (status) => {
        const [label, tone] = POMEC_STATUS[status] || [status || '-', 'bg-slate-100 text-slate-600 border-slate-200'];
        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-[11px] font-semibold whitespace-nowrap ${tone}">${escapeHtml(label)}</span>`;
    };
    const pomecUnitHtml = (type) => `<span class="text-slate-400 font-normal">${escapeHtml(pomecUnit(type))}</span>`;

    // Buang batch (dan barisnya) dari tampilan setelah dihapus atau diganti unggahan ulang.
    function removePomecImport(importId) {
        POMEC_RECORDS = POMEC_RECORDS.filter((r) => r.pomec_import_id !== importId);
        POMEC_IMPORTS = POMEC_IMPORTS.filter((i) => i.id !== importId);
        renderPomec();
        renderPomecImports();
    }

    // Aksi dari modal detail laporan POMEC.
    window.pomecReportAction = async function (reportId, action) {
        const rpt = REPORTS_DATA.find((r) => r.id === reportId);
        const imp = POMEC_IMPORTS.find((i) => i.id === rpt?.pomec_import_id);
        if (!rpt) return;
        if (action === 'view' || action === 'reupload') {
            window.closeModal('modal-report-detail');
            window.switchWorkspaceTab('pomec');
            if (imp) {
                pomecType = imp.utility_type;
                pomecMonth = imp.period_month;
                renderPomec();
            }
            window.scrollTo({ top: 0 });
            if (action === 'reupload') openPomecUpload(rpt);
            return;
        }
        if (action === 'delete') {
            const ok = window.confirm(IS_EN
                ? `Delete draft ${rpt.report_code}? Its uploaded data is removed from the charts.`
                : `Hapus draft ${rpt.report_code}? Data unggahannya ikut hilang dari grafik.`);
            if (!ok) return;
        }
        try {
            const res = await fetch(`/reports/${reportId}${action === 'submit' ? '/submit' : ''}`, {
                method: action === 'submit' ? 'POST' : 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
            window.showToast(data.message, 'success');
            if (action === 'submit') {
                upsertReportInState(data.report);
                window.openReportDetail(reportId);
            } else {
                REPORTS_DATA = REPORTS_DATA.filter((r) => r.id !== reportId);
                removePomecImport(data.pomec_import_id);
                renderReportsTable();
                refreshKpis();
                window.closeModal('modal-report-detail');
            }
        } catch {
            window.showToast(T.networkError, 'error');
        }
    };

    // Status unggahan harian: tanggal terakhir per utilitas dibanding kemarin (WITA).
    function renderPomecFreshness() {
        const box = document.getElementById('pomec-freshness');
        if (!box) return;
        const dayNo = (iso) => {
            const [y, m, d] = iso.split('-').map(Number);
            return Date.UTC(y, m - 1, d) / 864e5;
        };
        const yesterday = dayNo(formatWitaIsoDate(new Date())) - 1;
        // Antrean laporan: satu ringkasan yang bisa diklik, bukan diulang per utilitas.
        const queue = [
            ['submitted', IS_EN ? 'awaiting HOD' : 'menunggu HOD', 'bg-amber-50 text-amber-800 border-amber-200', ['submitted', 'in_review']],
            ['returned', IS_EN ? 'returned' : 'dikembalikan', 'bg-rose-50 text-crimson-dark border-rose-200', ['returned']],
            ['draft', IS_EN ? 'in draft' : 'masih draft', 'bg-slate-50 text-slate-600 border-slate-200', ['draft']],
        ].map(([filter, label, tone, statuses]) => {
            const n = POMEC_IMPORTS.filter((imp) => statuses.includes(pomecReportOf(imp.id)?.status)).length;
            return n ? `<button type="button" data-pomec-queue="${filter}" class="px-2.5 py-1 rounded-full border font-semibold transition hover:opacity-80 cursor-pointer ${tone}">${n} ${IS_EN ? 'reports' : 'laporan'} ${label}</button>` : '';
        }).join('');
        box.innerHTML = `<span class="font-semibold text-slate-600">${IS_EN ? 'Latest update' : 'Pembaruan terakhir'}</span>` + Object.keys(POMEC_TYPES).map((type) => {
            const latest = POMEC_RECORDS.reduce((max, r) => (r.utility_type === type && r.reading_date > max ? r.reading_date : max), '');
            const late = latest ? yesterday - dayNo(latest) : 0;
            // Data tetap tampil di grafik; titik kuning = ada data yang belum disetujui HOD.
            const pending = POMEC_RECORDS.some((r) => r.utility_type === type && pomecReportOf(r.pomec_import_id)?.status !== 'reviewed');
            const dot = !latest ? 'bg-slate-300' : (late > 0 || pending ? 'bg-amber-500' : 'bg-emerald-500');
            let note = escapeHtml(latest ? formatWitaDateLabel(latest, IS_EN) : (IS_EN ? 'none yet' : 'belum ada'));
            if (late > 0) note += ` · <span class="text-amber-700 font-semibold">${IS_EN ? `${late} day(s) behind` : `tertinggal ${late} hari`}</span>`;
            const title = pending ? ` title="${IS_EN ? 'Some data not yet approved by HOD' : 'Ada data yang belum disetujui HOD'}"` : '';
            return `<span class="inline-flex items-center gap-1.5"${title}><span class="w-1.5 h-1.5 rounded-full ${dot}" aria-hidden="true"></span><span class="font-semibold text-navy">${escapeHtml(pomecLabel(type))}</span>${note}</span>`;
        }).join('') + (queue ? `<span class="flex flex-wrap gap-1.5 ml-auto">${queue}</span>` : '');
    }

    function renderPomec() {
        if (!pomecRoot) return;
        renderPomecFreshness();
        const months = pomecMonths();
        if (!months.includes(pomecMonth)) pomecMonth = months[0] || '';

        const select = document.getElementById('pomec-month-filter');
        select.innerHTML = months.length
            ? months.map((m) => `<option value="${m}">${escapeHtml(pomecMonthName(m))}</option>`).join('')
            : `<option value="">${IS_EN ? 'No data yet' : 'Belum ada data'}</option>`;
        select.value = pomecMonth;

        const monthRecords = POMEC_RECORDS.filter((r) => pomecMonthOf(r) === pomecMonth);
        const [year, monthNo] = (pomecMonth || '0-0').split('-').map(Number);
        const daysInMonth = new Date(year, monthNo, 0).getDate();
        document.querySelectorAll('.pomec-tab').forEach((tab) => {
            tab.setAttribute('aria-pressed', tab.dataset.pomecOpen === pomecType ? 'true' : 'false');
        });

        // Tombol mengikuti grafik yang benar-benar tampil: gas dan solar tidak punya grafik harian.
        document.querySelectorAll('[data-pomec-view]').forEach((btn) => {
            btn.setAttribute('aria-pressed', btn.dataset.pomecView === pomecViewFor(pomecType) ? 'true' : 'false');
            btn.disabled = btn.dataset.pomecView === 'day' && POMEC_SPARSE.includes(pomecType);
            btn.title = btn.disabled ? (IS_EN ? 'Gas and diesel are charted per week' : 'Gas dan solar digambar per minggu') : '';
        });

        const showAll = pomecType === 'all';
        document.getElementById('pomec-overview').classList.toggle('hidden', !showAll);
        document.getElementById('pomec-detail').classList.toggle('hidden', showAll);
        if (showAll) {
            renderPomecOverview(monthRecords, daysInMonth);
            return;
        }

        const rows = monthRecords.filter((r) => r.utility_type === pomecType);
        document.getElementById('pomec-detail-title').textContent = `${pomecLabel(pomecType)} · ${pomecMonthName(pomecMonth)}`;
        const usageTitle = {
            day: IS_EN ? 'Daily usage' : 'Pemakaian harian',
            week: IS_EN ? 'Weekly usage' : 'Pemakaian mingguan',
            month: IS_EN ? 'Monthly usage' : 'Pemakaian bulanan',
        }[pomecViewFor(pomecType)];
        document.getElementById('pomec-detail-note').textContent = `${usageTitle} (${pomecUnit(pomecType)})`;
        ['excel', 'pdf'].forEach((format) => {
            const link = document.getElementById(`pomec-export-${format}`);
            link.href = link.dataset.href.replace('__type__', pomecType) + (pomecMonth ? `?period_month=${pomecMonth}` : '');
        });

        renderPomecStats(rows);
        renderPomecRows();
        renderPomecChart(rows);
    }

    // Tabel rincian harian (TanStack): satuan di kepala kolom, kolom meter/biaya hanya bila relevan.
    let pomecRecordsTable;
    let pomecRecordsKey = '';
    let pomecMultiMeter = false;
    const dash = '<span class="text-slate-300">—</span>';

    function renderPomecRows() {
        pomecRecordsTable ??= createDataTable({
            tableId: 'pomec-records-table',
            pagerId: 'pomec-pagination',
            sorting: [{ id: 'reading_date', desc: true }],
            empty: () => (IS_EN ? 'No data for this month yet.' : 'Belum ada data untuk bulan ini.'),
            columns: [
                { id: 'reading_date', header: () => (IS_EN ? 'Date' : 'Tanggal'), accessor: (r) => r.reading_date, tdClass: 'font-mono-num text-slate-600 whitespace-nowrap', cell: (r) => escapeHtml(formatWitaDateLabel(r.reading_date, IS_EN)) },
                { id: 'meter_name', header: 'Meter', accessor: (r) => r.meter_name, visible: () => pomecMultiMeter, tdClass: 'font-medium text-navy whitespace-nowrap', cell: (r) => escapeHtml(r.meter_name) },
                {
                    id: 'total_usage',
                    header: () => `${IS_EN ? 'Usage' : 'Pemakaian'} (${pomecUnit(pomecType)})`,
                    accessor: (r) => Number(r.total_usage || 0),
                    align: 'right',
                    tdClass: 'font-mono-num whitespace-nowrap',
                    cell: (r) => `<span class="font-semibold text-navy">${pomecQty(r.total_usage)}</span>${r.utility_type === 'electricity' ? `<div class="text-[10px] text-slate-500">LWBP ${pomecQty(r.lwbp_value)} · WBP ${pomecQty(r.wbp_value)}</div>` : ''}`,
                },
                {
                    id: 'cost',
                    header: () => (IS_EN ? 'Cost before tax (IDR)' : 'Biaya sebelum pajak (Rp)'),
                    accessor: (r) => (r.cost === null ? -1 : Number(r.cost)),
                    align: 'right',
                    visible: () => !!POMEC_TYPES[pomecType]?.cost,
                    tdClass: 'font-mono-num text-slate-600 whitespace-nowrap',
                    cell: (r) => (r.cost === null ? dash : Math.round(Number(r.cost)).toLocaleString('id-ID')),
                },
                { id: 'notes', header: () => (IS_EN ? 'Notes' : 'Catatan'), tdClass: 'text-slate-500 min-w-[160px]', cell: (r) => (r.notes ? escapeHtml(r.notes) : dash) },
            ],
        });
        if (!pomecRecordsTable) return;

        // Urut meter dulu: pengurutan stabil menjaga baris pada tanggal yang sama tetap berurutan.
        const rows = POMEC_RECORDS.filter((r) => r.utility_type === pomecType && pomecMonthOf(r) === pomecMonth)
            .sort((a, b) => a.meter_name.localeCompare(b.meter_name));
        pomecMultiMeter = new Set(rows.map((r) => r.meter_name)).size > 1;
        const key = `${pomecType}|${pomecMonth}`;
        pomecRecordsTable.setData(rows, { resetPage: key !== pomecRecordsKey });
        pomecRecordsKey = key;
    }

    function pomecBarOptions(buckets, format, legend, ticks, view) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: chartStagger(10, 200),
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: legend,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        font: { size: 11, weight: '600' },
                    },
                },
                tooltip: {
                    filter: (item) => item.dataset.type !== 'line',
                    // Footer berisi perbandingan rata-rata, jadi harus tetap mudah dibaca.
                    footerColor: '#CBD5E1',
                    callbacks: {
                        title: (items) => (items.length ? buckets[items[0].dataIndex].title : ''),
                        label: (item) => `${item.dataset.label}: ${format(item.raw)}`,
                        footer: (items) => {
                            if (!items.length) return '';
                            const datasets = items[0].chart.data.datasets;
                            const average = datasets.find((d) => d.type === 'line')?.data[0];
                            const total = items.reduce((sum, item) => sum + Number(item.raw || 0), 0);
                            const lines = datasets.filter((d) => d.type !== 'line').length > 1 ? [`Total: ${format(total)}`] : [];
                            const { days } = buckets[items[0].dataIndex];
                            if (average) {
                                const diff = Math.round((total / average - 1) * 100);
                                lines.push(diff === 0
                                    ? (IS_EN ? 'Same as average' : 'Sama dengan rata-rata')
                                    : `${Math.abs(diff)}% ${diff > 0 ? (IS_EN ? 'above' : 'di atas') : (IS_EN ? 'below' : 'di bawah')} ${IS_EN ? 'average' : 'rata-rata'}`);
                                lines.push(`${IS_EN ? 'Average' : 'Rata-rata'}: ${format(average)}`);
                            } else if (view !== 'day' && days) {
                                // Per hari supaya minggu atau bulan yang belum penuh tetap bisa dibandingkan.
                                lines.push(`${IS_EN ? 'Per day' : 'Per hari'}: ${format(total / days)} (${days} ${IS_EN ? 'days' : 'hari'})`);
                            }
                            return lines;
                        },
                    },
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkipPadding: 8 } },
                y: { stacked: true, beginAtZero: true, grid: { color: chartGridColor() }, border: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: ticks, callback: (v) => format(v, true) } },
            },
        };
    }

    function pomecBar(label, data, color, stacked = false) {
        // Celah 2px antar tumpukan.
        return { label, data, backgroundColor: color, borderRadius: 3, maxBarThickness: 22, borderColor: chartPointBorderColor(), borderWidth: { top: stacked ? 2 : 0 } };
    }

    // Angka di atas batang yang perlu diperhatikan: hari menonjol atau bulan terpilih.
    const pomecMarker = (marks, format) => ({
        id: 'pomecMarker',
        afterDatasetsDraw(chart) {
            const { ctx, chartArea } = chart;
            let lastRight = -Infinity;
            ctx.save();
            ctx.font = `700 10px ${Chart.defaults.font.family}`;
            ctx.fillStyle = isDarkTheme() ? '#E2E8F0' : '#0F2942';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            marks.forEach((marked, i) => {
                if (!marked) return;
                const bars = chart.data.datasets.flatMap((d, di) => (d.type !== 'line' && d.data[i] !== null && chart.isDatasetVisible(di)
                    ? [{ value: d.data[i], bar: chart.getDatasetMeta(di).data[i] }]
                    : []));
                if (!bars.length) return;
                const text = format(bars.reduce((sum, b) => sum + b.value, 0), true);
                const half = ctx.measureText(text).width / 2;
                const x = Math.min(Math.max(bars[0].bar.x, chartArea.left + half), chartArea.right - half);
                // Angka yang akan menimpa angka sebelumnya dilewati; tooltip tetap memuatnya.
                if (x - half < lastRight + 4) return;
                lastRight = x + half;
                ctx.fillText(text, x, Math.min(...bars.map((b) => b.bar.y)) - 3);
            });
            ctx.restore();
        },
    });

    // Tambah garis rata-rata harian dan tandai hari yang menonjol.
    function pomecWithAverage(bars) {
        const totals = bars[0].data.map((_, i) => {
            const values = bars.map((b) => b.data[i]).filter((v) => v !== null);
            return values.length ? values.reduce((sum, v) => sum + v, 0) : null;
        });
        const recorded = totals.filter((v) => v !== null);
        const average = recorded.reduce((sum, v) => sum + v, 0) / (recorded.length || 1);
        let high = totals.map((v) => v !== null && average > 0 && v >= average * POMEC_HIGH);
        // Kalau lebih dari seperempat hari "menonjol" (mis. gas: hari ganti tabung vs hari kosong), itu pola biasa.
        if (high.filter(Boolean).length > recorded.length / 4) high = high.map(() => false);
        const muted = isDarkTheme() ? '#94A3B8' : '#475569';

        return { marks: high, datasets: [
            ...bars,
            { type: 'line', label: IS_EN ? 'Average' : 'Rata-rata', data: totals.map(() => average), borderColor: muted, backgroundColor: muted, borderWidth: 1.5, borderDash: [5, 4], pointRadius: 0, pointHoverRadius: 0, pointStyle: 'line', order: -1 },
        ] };
    }

    // Kunci kelompok satu baris: tanggalnya, Senin minggu itu (dipotong di awal bulan), atau bulannya.
    function pomecBucketKey(rec, view) {
        const date = formatWitaIsoDate(rec.reading_date);
        if (view === 'month') return date.slice(0, 7);
        if (view === 'day') return date;
        const [y, m, d] = date.split('-').map(Number);
        const monday = d - ((new Date(Date.UTC(y, m - 1, d)).getUTCDay() + 6) % 7);
        return `${date.slice(0, 7)}-${String(Math.max(1, monday)).padStart(2, '0')}`;
    }

    // Satu batang per kelompok: hari atau minggu di bulan terpilih, atau semua bulan yang punya data.
    function pomecBuckets(view) {
        if (!pomecMonth) return [];
        if (view === 'month') {
            const short = new Intl.DateTimeFormat(IS_EN ? 'en-GB' : 'id-ID', { timeZone: 'Asia/Makassar', month: 'short', year: 'numeric' });
            return pomecMonths().reverse().map((m) => ({ key: m, label: short.format(new Date(`${m}-15T12:00:00+08:00`)), title: pomecMonthName(m) }));
        }
        const [y, m] = pomecMonth.split('-').map(Number);
        const days = Array.from({ length: new Date(y, m, 0).getDate() }, (_, i) => `${pomecMonth}-${String(i + 1).padStart(2, '0')}`);
        if (view === 'day') return days.map((d) => ({ key: d, label: Number(d.slice(8)), title: formatWitaDateLabel(d, IS_EN) }));
        const weeks = new Map();
        days.forEach((d) => {
            const key = pomecBucketKey({ reading_date: d }, view);
            weeks.set(key, [...(weeks.get(key) || []), d]);
        });
        return [...weeks].map(([key, own]) => {
            const first = Number(own[0].slice(8));
            const last = Number(own[own.length - 1].slice(8));
            const range = first === last ? `${first}` : `${first}–${last}`;
            return { key, label: range, title: `${first === last ? '' : `${own[0].slice(8)}–`}${formatWitaDateLabel(own[own.length - 1], IS_EN)}` };
        });
    }

    // Baris yang digambar: bulan terpilih saja, kecuali tampilan bulanan.
    const pomecChartRows = (monthRows, allRows) => (pomecView === 'month' ? allRows : monthRows);

    // series = [{ label, rows, key, color }]
    function pomecDrawChart(canvas, series, { name, format, legend = false, ticks = 6, view = pomecView }) {
        // Tanggal tercatat per kelompok, dihitung sekali jalan.
        const recorded = {};
        series.forEach((item) => item.rows.forEach((r) => {
            (recorded[pomecBucketKey(r, view)] ||= new Set()).add(formatWitaIsoDate(r.reading_date));
        }));
        const buckets = pomecBuckets(view)
            .map((bucket) => ({ ...bucket, days: recorded[bucket.key]?.size || 0 }))
            // Bulan tanpa data untuk grafik ini tidak perlu digambar sebagai kolom kosong.
            .filter((bucket) => view !== 'month' || bucket.days);
        const bars = series.map((item) => {
            const sums = {};
            item.rows.forEach((r) => {
                const key = pomecBucketKey(r, view);
                sums[key] = (sums[key] || 0) + Number(r[item.key] || 0);
            });
            const data = buckets.map((b) => (b.key in sums ? Number(sums[b.key].toFixed(2)) : null));
            return pomecBar(item.label, data, item.color, series.length > 1);
        });
        let datasets = bars;
        let marks = [];
        if (view === 'day') ({ datasets, marks } = pomecWithAverage(bars));
        if (view === 'month') marks = buckets.map((b) => b.key === pomecMonth);
        // Minggu dan bulan menampilkan jumlah hari di bawah labelnya.
        const labels = buckets.map((b) => (view === 'day' ? b.label : [b.label, `${b.days} ${IS_EN ? 'days' : 'hari'}`]));

        // Selalu dibuat ulang: jenis dan jumlah dataset berubah antar tampilan.
        Chart.getChart(canvas)?.destroy();
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', `${pomecPeriod(view)}: ${name}`);
        const options = pomecBarOptions(buckets, format, legend, ticks, view);
        // Ruang untuk angka di atas batang tertinggi.
        if (marks.some(Boolean)) options.scales.y.grace = '10%';
        new Chart(canvas, { type: 'bar', data: { labels, datasets }, options, plugins: [pomecMarker(marks, format)] });
    }

    // Satu sumbu per grafik: biaya (sama-sama rupiah) boleh digabung, pemakaian beda satuan dipisah.
    function renderPomecOverview(monthRecords, daysInMonth) {
        const hints = {
            day: IS_EN ? 'Dashed line: daily average. Numbers: days 25% or more above it.' : 'Garis putus-putus: rata-rata harian. Angka: hari yang 25% atau lebih di atas rata-rata.',
            week: IS_EN ? 'One bar per week (Monday to Sunday), with the number of recorded days.' : 'Satu batang per minggu (Senin–Minggu), dengan jumlah hari tercatat.',
            month: IS_EN ? 'One bar per month. The number marks the selected month.' : 'Satu batang per bulan. Angka menandai bulan terpilih.',
        };
        document.getElementById('pomec-chart-hint').textContent = hints[pomecView];

        const priced = monthRecords.filter((r) => r.cost !== null);
        const chartPriced = pomecChartRows(priced, POMEC_RECORDS.filter((r) => r.cost !== null));
        const costTypes = Object.keys(POMEC_TYPES).filter((t) => chartPriced.some((r) => r.utility_type === t));
        const costCanvas = document.getElementById('pomec-chart-cost');
        // Tanpa data biaya: kartunya disembunyikan, bukan ditampilkan kosong.
        document.getElementById('pomec-cost-card').classList.toggle('hidden', !costTypes.length);
        if (!costTypes.length) {
            Chart.getChart(costCanvas)?.destroy();
        } else {
            const monthTypes = costTypes.filter((t) => priced.some((r) => r.utility_type === t));
            document.getElementById('pomec-cost-title').textContent = `${IS_EN ? 'Cost' : 'Biaya'} ${pomecMonthName(pomecMonth)}`;
            document.getElementById('pomec-month-cost').textContent = priced.length ? pomecRupiah(pomecSum(priced, 'cost')) : '-';
            document.getElementById('pomec-cost-note').textContent = monthTypes.length
                ? `${monthTypes.map(pomecLabel).join(' + ')}, ${IS_EN ? 'before tax. Gas and diesel files have no cost.' : 'sebelum pajak. File gas dan solar tidak memuat biaya.'}`
                : (IS_EN ? 'No cost data for this month.' : 'Belum ada data biaya bulan ini.');
            const compact = new Intl.NumberFormat(IS_EN ? 'en-GB' : 'id-ID', { notation: 'compact', maximumFractionDigits: 1 });
            pomecDrawChart(
                costCanvas,
                costTypes.map((t) => ({ label: pomecLabel(t), rows: chartPriced.filter((r) => r.utility_type === t), key: 'cost', color: pomecColor(t) })),
                { name: IS_EN ? 'Cost per utility' : 'Biaya per utilitas', format: (v, short) => (short ? `Rp ${compact.format(v)}` : pomecRupiah(v)), legend: true },
            );
        }

        Object.keys(POMEC_TYPES).forEach((type) => {
            const canvas = document.getElementById(`pomec-mini-${type}`);
            const rows = monthRecords.filter((r) => r.utility_type === type);
            const chartRows = pomecChartRows(rows, POMEC_RECORDS.filter((r) => r.utility_type === type));
            const card = canvas.closest('[data-mini]');
            const recorded = Object.keys(pomecDaily(rows)).length;
            const trend = rows.length ? pomecTrend(rows, type) : { text: '', tone: '' };
            let sub = IS_EN ? 'No data this month.' : 'Belum ada data bulan ini.';
            if (rows.length) {
                sub = rows.some((r) => r.cost !== null)
                    ? pomecRupiah(pomecSum(rows, 'cost'))
                    : `${IS_EN ? 'Average' : 'Rata-rata'} ${pomecQty(pomecDailyAverage(rows))} ${pomecUnit(type)}/${IS_EN ? 'day' : 'hari'}`;
                // Jumlah hari hanya disebut kalau bulannya belum lengkap.
                if (recorded < daysInMonth) sub += ` · ${recorded}/${daysInMonth} ${IS_EN ? 'days' : 'hari'}`;
                if (pomecViewFor(type) !== pomecView) sub += IS_EN ? ' · weekly chart' : ' · grafik per minggu';
            }
            card.querySelector('[data-total]').textContent = pomecQty(pomecSum(rows, 'total_usage'));
            card.querySelector('[data-sub]').textContent = sub;
            card.querySelector('[data-trend]').textContent = trend.text;
            card.querySelector('[data-trend]').className = `font-semibold ${trend.tone}`;
            // Tanpa data, kartu cukup satu baris keterangan.
            card.querySelector('[data-value]').classList.toggle('hidden', !rows.length);
            canvas.parentElement.classList.toggle('hidden', !chartRows.length);
            if (!chartRows.length) {
                Chart.getChart(canvas)?.destroy();
                return;
            }
            pomecDrawChart(
                canvas,
                [{ label: pomecLabel(type), rows: chartRows, key: 'total_usage', color: pomecColor(type) }],
                {
                    name: `${IS_EN ? 'Usage' : 'Pemakaian'} ${pomecLabel(type)}`,
                    // Satuan sudah tertulis di kartu, sumbu dan angka batang cukup angkanya.
                    format: (v, short) => (short ? pomecQty(v) : `${pomecQty(v)} ${pomecUnit(type)}`),
                    ticks: 4,
                    view: pomecViewFor(type),
                },
            );
        });
    }

    function renderPomecChart(monthRows) {
        const canvas = document.getElementById('pomec-chart-trend');
        if (!canvas) return;

        const rows = pomecChartRows(monthRows, POMEC_RECORDS.filter((r) => r.utility_type === pomecType));
        const unit = pomecUnit(pomecType);
        const meterNames = [...new Set(rows.map((r) => r.meter_name))].sort();
        const shades = pomecShades(pomecType);
        // Tanpa data, area grafik disembunyikan; tabel sudah menjelaskan.
        canvas.parentElement.parentElement.classList.toggle('hidden', !rows.length);
        if (!rows.length) {
            Chart.getChart(canvas)?.destroy();
            return;
        }
        let series;
        if (pomecType === 'electricity') {
            series = [{ label: 'LWBP', rows, key: 'lwbp_value', color: shades[0] }, { label: 'WBP', rows, key: 'wbp_value', color: shades[1] }];
        } else if (shades && meterNames.length > 1) {
            series = meterNames.map((name, i) => ({ label: name, rows: rows.filter((r) => r.meter_name === name), key: 'total_usage', color: shades[i % shades.length] }));
        } else {
            series = [{ label: pomecLabel(pomecType), rows, key: 'total_usage', color: pomecColor(pomecType) }];
        }

        pomecDrawChart(canvas, series, {
            name: `${IS_EN ? 'Usage' : 'Pemakaian'} ${pomecLabel(pomecType)}`,
            format: (v, short) => (short ? pomecQty(v) : `${pomecQty(v)} ${unit}`),
            legend: true,
            view: pomecViewFor(pomecType),
        });
    }

    // Tabel laporan POMEC (TanStack): satu baris per unggahan beserta status laporannya.
    let pomecReportsTable;
    let pomecReportsFilter = '';
    const POMEC_STATUS_ORDER = { returned: 0, draft: 1, submitted: 2, in_review: 3, reviewed: 4 };

    function renderPomecImports() {
        if (!document.getElementById('pomec-imports-tbody')) return;
        document.getElementById('pomec-batch-count').textContent = POMEC_IMPORTS.length;
        const canDelete = pomecRoot.dataset.canDelete === '1';

        // Unggahan harian: tampilkan tanggal yang dibawa tiap batch, bukan hanya bulannya.
        const ranges = {};
        POMEC_RECORDS.forEach((r) => {
            const range = (ranges[r.pomec_import_id] ||= [r.reading_date, r.reading_date]);
            if (r.reading_date < range[0]) range[0] = r.reading_date;
            if (r.reading_date > range[1]) range[1] = r.reading_date;
        });
        const rangeLabel = (imp) => {
            const [first, last] = ranges[imp.id] || [];
            if (!first) return pomecMonthName(imp.period_month);
            return first === last ? formatWitaDateLabel(last, IS_EN) : `${Number(first.slice(8))}–${formatWitaDateLabel(last, IS_EN)}`;
        };

        pomecReportsTable ??= createDataTable({
            tableId: 'pomec-reports-table',
            pagerId: 'pomec-reports-pagination',
            sorting: [{ id: 'uploaded', desc: true }],
            empty: () => (POMEC_IMPORTS.length
                ? (IS_EN ? 'No reports with this status.' : 'Tidak ada laporan dengan status ini.')
                : (IS_EN ? 'Nothing uploaded yet.' : 'Belum ada file yang diunggah.')),
            columns: [
                {
                    id: 'report',
                    header: () => (IS_EN ? 'Report' : 'Laporan'),
                    accessor: (x) => x.rpt?.report_code || x.imp.batch_code,
                    tdClass: 'min-w-[200px]',
                    cell: (x) => `<div class="font-semibold text-navy">${escapeHtml(x.rpt?.report_code || x.imp.batch_code)}</div>
                        <div class="text-[10px] text-slate-400" title="${escapeHtml(x.imp.batch_code)} · SHA-256: ${escapeHtml(x.imp.file_hash_sha256 || '')}">${escapeHtml(x.imp.original_filename)}</div>`,
                },
                {
                    id: 'utility',
                    header: () => (IS_EN ? 'Utility' : 'Utilitas'),
                    accessor: (x) => pomecLabel(x.imp.utility_type),
                    tdClass: 'whitespace-nowrap',
                    cell: (x) => `<span class="inline-flex items-center gap-2 font-medium text-navy"><span class="w-2 h-2 rounded-full" style="background-color:${pomecColor(x.imp.utility_type)}" aria-hidden="true"></span>${escapeHtml(pomecLabel(x.imp.utility_type))}</span>`,
                },
                {
                    id: 'period',
                    header: () => (IS_EN ? 'Period' : 'Periode'),
                    accessor: (x) => (ranges[x.imp.id] || [x.imp.period_month])[0],
                    tdClass: 'whitespace-nowrap text-slate-600',
                    cell: (x) => escapeHtml(rangeLabel(x.imp)),
                },
                {
                    // Satuan berbeda antar utilitas, jadi kolom ini tidak diurutkan.
                    id: 'usage',
                    header: () => (IS_EN ? 'Usage' : 'Pemakaian'),
                    align: 'right',
                    tdClass: 'font-mono-num whitespace-nowrap',
                    cell: (x) => `<span class="font-semibold text-navy">${pomecQty(x.imp.total_usage)}</span> ${pomecUnitHtml(x.imp.utility_type)}
                        ${x.imp.total_cost === null || x.imp.total_cost === undefined ? '' : `<div class="text-[10px] text-slate-500">${pomecRupiah(x.imp.total_cost)}</div>`}`,
                },
                {
                    id: 'uploaded',
                    header: () => (IS_EN ? 'Submitted by (WITA)' : 'Diunggah oleh (WITA)'),
                    accessor: (x) => x.imp.committed_at || '',
                    tdClass: 'text-slate-600 whitespace-nowrap',
                    cell: (x) => `<div>${escapeHtml(x.imp.uploader?.name || '-')}</div><div class="text-[10px] font-mono-num text-slate-400">${escapeHtml(formatWita(x.imp.committed_at, true))}</div>`,
                },
                {
                    id: 'status',
                    header: 'Status',
                    accessor: (x) => POMEC_STATUS_ORDER[x.rpt?.status] ?? 9,
                    tdClass: 'whitespace-nowrap',
                    cell: (x) => (x.rpt ? pomecStatusBadge(x.rpt.status) : dash),
                },
                {
                    id: 'actions',
                    header: () => (IS_EN ? 'Action' : 'Aksi'),
                    align: 'right',
                    tdClass: 'whitespace-nowrap',
                    cell: (x) => `${x.rpt ? `<button type="button" data-pomec-report="${Number(x.rpt.id)}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-navy hover:text-white text-navy text-xs font-semibold transition cursor-pointer">${canReviewReport(x.rpt) ? T.review : T.view}</button>` : ''}
                        ${canDelete ? `<button type="button" data-pomec-delete="${Number(x.imp.id)}" class="ml-1 px-2.5 py-1.5 rounded-xl text-slate-400 hover:text-crimson-dark hover:bg-rose-50 text-xs font-semibold transition cursor-pointer disabled:opacity-50">${IS_EN ? 'Delete' : 'Hapus'}</button>` : ''}`,
                },
            ],
        });
        if (!pomecReportsTable) return;

        const statusOf = (imp) => pomecReportOf(imp.id)?.status || '';
        document.querySelectorAll('[data-pomec-status]').forEach((btn) => {
            const key = btn.dataset.pomecStatus;
            btn.setAttribute('aria-pressed', key === pomecReportStatus ? 'true' : 'false');
            const count = key === 'all' ? POMEC_IMPORTS.length : POMEC_IMPORTS.filter((imp) => statusOf(imp) === key).length;
            btn.querySelector('[data-count]').textContent = count ? count : '';
        });
        const data = POMEC_IMPORTS
            .filter((imp) => pomecReportStatus === 'all' || statusOf(imp) === pomecReportStatus)
            .map((imp) => ({ imp, rpt: pomecReportOf(imp.id) }));
        pomecReportsTable.setData(data, { resetPage: pomecReportStatus !== pomecReportsFilter });
        pomecReportsFilter = pomecReportStatus;
    }

    document.querySelectorAll('[data-pomec-status]').forEach((btn) => btn.addEventListener('click', () => {
        pomecReportStatus = btn.dataset.pomecStatus;
        renderPomecImports();
    }));
    // Ringkasan antrean di atas halaman langsung membuka daftar laporan dengan status itu.
    document.getElementById('pomec-freshness')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pomec-queue]');
        if (!btn) return;
        pomecReportStatus = btn.dataset.pomecQueue;
        renderPomecImports();
        document.getElementById('pomec-reports-title')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('pomec-imports-tbody')?.addEventListener('click', async (e) => {
        const open = e.target.closest('[data-pomec-report]');
        if (open) return window.openReportDetail(Number(open.dataset.pomecReport));
        const btn = e.target.closest('[data-pomec-delete]');
        if (!btn) return;
        const id = Number(btn.dataset.pomecDelete);
        const imp = POMEC_IMPORTS.find((i) => i.id === id);
        if (!imp) return;
        const what = `${pomecLabel(imp.utility_type)} ${pomecMonthName(imp.period_month)}`;
        const question = IS_EN
            ? `Delete ${what} (${imp.total_rows} rows) from "${imp.original_filename}"?\n\nThese readings disappear from the charts. You can then upload the corrected file.`
            : `Hapus data ${what} (${imp.total_rows} baris) dari file "${imp.original_filename}"?\n\nData ini hilang dari grafik. Setelah itu file yang benar bisa diunggah lagi.`;
        if (!window.confirm(question)) return;

        btn.disabled = true;
        try {
            const res = await fetch(`/pomec/imports/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) {
                window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
                btn.disabled = false;
                return;
            }
            // Laporannya ikut terhapus di server.
            REPORTS_DATA = REPORTS_DATA.filter((r) => r.pomec_import_id !== id);
            removePomecImport(id);
            renderReportsTable();
            refreshKpis();
            window.showToast(data.message, 'success');
        } catch {
            window.showToast(T.networkError, 'error');
            btn.disabled = false;
        }
    });

    document.querySelectorAll('[data-pomec-view]').forEach((btn) => {
        btn.addEventListener('click', () => {
            pomecView = btn.dataset.pomecView;
            renderPomec();
        });
    });

    document.getElementById('pomec-month-filter')?.addEventListener('change', (e) => {
        pomecMonth = e.target.value;
        renderPomec();
    });

    document.querySelectorAll('[data-pomec-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            pomecType = btn.dataset.pomecOpen;
            renderPomec();
        });
    });

    const pomecDialog = document.getElementById('pomec-upload-panel');

    // Pesan ditampilkan di dalam dialog: toast akan tertutup latar dialog.
    function showPomecMessage(message, tone = 'error') {
        const banner = document.getElementById('pomec-hash-banner');
        const tones = {
            ok: 'bg-emerald-50 border-emerald-200 text-emerald-800',
            warn: 'bg-amber-50 border-amber-200 text-amber-800',
            error: 'bg-rose-50 border-rose-200 text-crimson-dark',
        };
        banner.className = `p-3 rounded-xl border text-xs font-semibold whitespace-pre-line ${tones[tone]}`;
        banner.textContent = message;
    }

    function resetPomecPreview() {
        currentPomecPreviewToken = null;
        document.getElementById('pomec-preview-box')?.classList.add('hidden');
        document.getElementById('pomec-hash-banner')?.classList.add('hidden');
        document.getElementById('pomec-commit-btn')?.classList.add('hidden');
        document.getElementById('pomec-draft-btn')?.classList.add('hidden');
        document.getElementById('pomec-preview-btn')?.classList.remove('hidden');
    }

    function renderPomecFile() {
        const file = document.getElementById('pomec-file-input')?.files?.[0];
        const name = document.getElementById('pomec-file-name');
        const info = document.getElementById('pomec-file-info');
        if (!name || !info) return;
        name.textContent = file ? file.name : name.dataset.empty;
        info.textContent = file ? `${Math.max(1, Math.round(file.size / 1024))} KB` : info.dataset.empty;
    }

    // replace = laporan yang dikembalikan HOD: jenis dan bulan dikunci, isi batch lamanya diganti.
    function openPomecUpload(replace = null) {
        if (!pomecDialog) return;
        const typeEl = document.getElementById('pomec-utility-type');
        const monthEl = document.getElementById('pomec-period-month');
        const imp = replace ? POMEC_IMPORTS.find((i) => i.id === replace.pomec_import_id) : null;
        pomecReplace = replace && imp ? { id: replace.id, type: imp.utility_type, month: imp.period_month } : null;
        if (pomecReplace) {
            typeEl.value = pomecReplace.type;
            monthEl.value = pomecReplace.month;
        } else if (pomecType !== 'all') {
            // Ikuti utilitas yang sedang dilihat.
            typeEl.value = pomecType;
        }
        typeEl.disabled = !!pomecReplace;
        monthEl.disabled = !!pomecReplace;
        const subtitle = document.getElementById('pomec-upload-subtitle');
        subtitle.dataset.default ??= subtitle.textContent;
        document.getElementById('pomec-upload-title').textContent = pomecReplace
            ? `${IS_EN ? 'Re-upload Correction' : 'Unggah Ulang Perbaikan'} · ${replace.report_code}`
            : (IS_EN ? 'Upload Daily Data' : 'Unggah Data Harian');
        subtitle.textContent = pomecReplace
            ? `${IS_EN ? 'HOD note' : 'Catatan HOD'}: ${replace.review_note || '-'} ${IS_EN ? 'The corrected file replaces this report\'s data and is sent back to the HOD.' : 'File perbaikan menggantikan isi laporan ini dan dikirim ulang ke HOD.'}`
            : subtitle.dataset.default;
        pomecDialog.showModal();
    }

    document.getElementById('pomec-upload-toggle')?.addEventListener('click', () => openPomecUpload());
    pomecDialog?.querySelectorAll('[data-pomec-close]').forEach((btn) => btn.addEventListener('click', () => pomecDialog.close()));
    pomecDialog?.addEventListener('close', () => {
        document.getElementById('pomec-preview-form').reset();
        pomecReplace = null;
        document.getElementById('pomec-utility-type').disabled = false;
        document.getElementById('pomec-period-month').disabled = false;
        renderPomecFile();
        resetPomecPreview();
    });

    // Pilihan berubah = preview lama tidak berlaku lagi.
    document.getElementById('pomec-preview-form')?.addEventListener('change', () => {
        renderPomecFile();
        resetPomecPreview();
    });

    document.getElementById('pomec-preview-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        if (pomecReplace) {
            // Isian yang dikunci tidak ikut terkirim, jadi diisi manual.
            fd.set('utility_type', pomecReplace.type);
            fd.set('period_month', pomecReplace.month);
            fd.set('replace_report_id', pomecReplace.id);
        }
        const btn = document.getElementById('pomec-preview-btn');
        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = btn.dataset.busy;
        resetPomecPreview();

        try {
            const res = await fetch('/pomec/preview', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) {
                showPomecMessage(extractErrorMessage(data, T.saveFailed));
                return;
            }

            currentPomecPreviewToken = data.preview_token;
            const p = data.preview;
            const rows = p.rows || [];
            const canSave = !p.is_duplicate_file && p.valid_count > 0;

            const dateLabel = (d) => formatWitaDateLabel(d, IS_EN);
            const dateList = (dates) => {
                const unique = [...new Set(dates)].sort();
                return unique.length > 4
                    ? `${dateLabel(unique[0])} … ${dateLabel(unique[unique.length - 1])} (${unique.length} ${IS_EN ? 'dates' : 'tanggal'})`
                    : unique.map(dateLabel).join(', ');
            };

            // Pemeriksaan untuk unggahan harian: baru, sudah tersimpan, berbeda, bermasalah, tanggal terlewat.
            let message;
            let tone = 'error';
            if (p.is_duplicate_file) {
                message = IS_EN
                    ? `This exact file was already uploaded (${p.duplicate_batch}). Nothing new to save.`
                    : `File yang sama persis sudah pernah diunggah (${p.duplicate_batch}). Tidak ada data baru untuk disimpan.`;
                tone = 'warn';
            } else if (!rows.length) {
                message = (p.errors || []).join(' ');
            } else {
                const newDates = rows.filter((r) => r.status === 'valid').map((r) => r.reading_date);
                const total = `${pomecQty(p.total_usage)} ${pomecUnit(p.utility_type)}${p.total_cost === null ? '' : ` · ${pomecRupiah(p.total_cost)}`}`;
                const lines = [];
                if (p.valid_count) {
                    lines.push(IS_EN
                        ? `New: ${p.valid_count} rows (${dateList(newDates)}), total ${total}.`
                        : `Baru: ${p.valid_count} data (${dateList(newDates)}), total ${total}.`);
                } else {
                    lines.push(p.last_saved_date
                        ? (IS_EN ? `No new dates. Data up to ${dateLabel(p.last_saved_date)} is already saved.` : `Tidak ada tanggal baru. Data s/d ${dateLabel(p.last_saved_date)} sudah tersimpan.`)
                        : (IS_EN ? 'No rows can be saved.' : 'Tidak ada data yang bisa disimpan.'));
                }
                if (p.existing_count) lines.push(IS_EN ? `${p.existing_count} rows already saved earlier, skipped.` : `${p.existing_count} data sudah tersimpan sebelumnya, dilewati.`);
                if (p.changed_count) lines.push(IS_EN ? `${p.changed_count} rows differ from what is saved and are not overwritten.` : `${p.changed_count} data berbeda dengan yang tersimpan dan tidak ditimpa.`);
                if (p.error_count) lines.push(IS_EN ? `${p.error_count} rows have problems and are skipped.` : `${p.error_count} data bermasalah dan dilewati.`);
                if (p.missing_dates?.length) {
                    lines.push(IS_EN
                        ? `Missing dates: ${dateList(p.missing_dates)}. Not saved yet and not readable in the file (incomplete meter readings?).`
                        : `Tanggal terlewat: ${dateList(p.missing_dates)}. Belum tersimpan dan tidak terbaca di file (angka meter belum lengkap?).`);
                }
                message = lines.join('\n');
                // Hijau hanya kalau semuanya beres; sudah-tersimpan saja bukan masalah.
                const warnings = p.changed_count || p.error_count || p.missing_dates?.length;
                tone = canSave && !warnings ? 'ok' : 'warn';
            }
            showPomecMessage(message, tone);

            // Data yang sudah tersimpan tidak perlu ditampilkan; yang perlu perhatian di atas.
            const statusLabel = IS_EN
                ? { valid: 'New', changed: 'Differs', error: 'Problem' }
                : { valid: 'Baru', changed: 'Berbeda', error: 'Bermasalah' };
            const statusTone = { valid: 'bg-emerald-50 text-emerald-700', changed: 'bg-amber-50 text-amber-800', error: 'bg-rose-50 text-crimson-dark' };
            const rank = { error: 0, changed: 1, valid: 2 };
            const shown = rows.filter((r) => r.status !== 'existing')
                .sort((a, b) => rank[a.status] - rank[b.status] || a.reading_date.localeCompare(b.reading_date));
            document.getElementById('pomec-preview-rows').innerHTML = shown.map((r) => `
                <tr>
                    <td class="py-2.5 px-4 font-mono-num whitespace-nowrap">${escapeHtml(dateLabel(r.reading_date))}</td>
                    <td class="py-2.5 px-4">
                        <div class="font-medium text-navy">${escapeHtml(r.meter_name)}</div>
                        ${r.errors?.length ? `<div class="text-[11px] mt-0.5 ${r.status === 'changed' ? 'text-amber-800' : 'text-crimson-dark'}">${escapeHtml(r.errors.join(', '))}</div>` : ''}
                    </td>
                    <td class="py-2.5 px-4 text-right font-mono-num whitespace-nowrap">${pomecQty(r.total_usage)} ${pomecUnit(p.utility_type)}${r.cost === null ? '' : `<div class="text-[10px] text-slate-500">${pomecRupiah(r.cost)}</div>`}</td>
                    <td class="py-2.5 px-4 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ${statusTone[r.status]}">${statusLabel[r.status]}</span>
                    </td>
                </tr>
            `).join('');

            // "Periksa File" diganti tombol simpan kalau ada data baru: draft dulu, atau langsung ke HOD.
            const commit = document.getElementById('pomec-commit-btn');
            const draft = document.getElementById('pomec-draft-btn');
            commit.disabled = false;
            draft.disabled = false;
            commit.textContent = pomecReplace
                ? (IS_EN ? 'Send Correction to HOD' : 'Kirim Ulang ke HOD')
                : (IS_EN ? 'Save & Submit to HOD' : 'Simpan & Kirim ke HOD');
            commit.classList.toggle('hidden', !canSave);
            draft.classList.toggle('hidden', !canSave || !!pomecReplace);
            btn.classList.toggle('hidden', canSave);
            document.getElementById('pomec-preview-box').classList.toggle('hidden', !shown.length || p.is_duplicate_file);
        } catch {
            showPomecMessage(T.networkError);
        } finally {
            btn.disabled = false;
            btn.textContent = label;
        }
    });

    async function commitPomec(intent) {
        if (!currentPomecPreviewToken) return;
        const btn = document.getElementById(intent === 'draft' ? 'pomec-draft-btn' : 'pomec-commit-btn');
        btn.disabled = true;
        try {
            const res = await fetch('/pomec/commit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: JSON.stringify({ preview_token: currentPomecPreviewToken, intent }),
            });
            const data = await res.json();
            if (!res.ok) {
                showPomecMessage(extractErrorMessage(data, T.saveFailed));
                btn.disabled = false;
                return;
            }
            pomecDialog.close();
            window.showToast(data.message, 'success');
            if (data.replaced_import_id) {
                POMEC_RECORDS = POMEC_RECORDS.filter((r) => r.pomec_import_id !== data.replaced_import_id);
                POMEC_IMPORTS = POMEC_IMPORTS.filter((i) => i.id !== data.replaced_import_id);
            }
            if (data.import) {
                POMEC_RECORDS = [...(data.import.records || []), ...POMEC_RECORDS];
                POMEC_IMPORTS.unshift(data.import);
                // Langsung tampilkan data yang baru disimpan.
                pomecType = data.import.utility_type;
                pomecMonth = data.import.period_month;
            }
            if (data.report) upsertReportInState(data.report);
            renderPomec();
            renderPomecImports();
        } catch {
            showPomecMessage(T.networkError);
            btn.disabled = false;
        }
    }
    document.getElementById('pomec-commit-btn')?.addEventListener('click', () => commitPomec('submit'));
    document.getElementById('pomec-draft-btn')?.addEventListener('click', () => commitPomec('draft'));

    // -------------------------------------------------------------------------
    // 7. Topology, Profile Password & Admin Controls
    // -------------------------------------------------------------------------
    window.openTopologyEditor = function (comp) {
        document.getElementById('topo-id').value = comp.id;
        document.getElementById('topo-modal-title').textContent = comp.name;
        document.getElementById('topo-status').value = comp.status;
        document.getElementById('topo-gm-id').value = comp.gm_summary;
        document.getElementById('topo-gm-en').value = comp.gm_summary_en;
        document.getElementById('topo-it-detail').value = comp.it_technical_detail;
        document.getElementById('topo-host').value = comp.host_or_endpoint || '';
        window.openModal('modal-topology-edit');
    };

    document.getElementById('topology-edit-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('topo-id').value;
        const res = await fetch(`/topology/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            body: JSON.stringify({
                status: document.getElementById('topo-status').value,
                gm_summary: document.getElementById('topo-gm-id').value,
                gm_summary_en: document.getElementById('topo-gm-en').value,
                it_technical_detail: document.getElementById('topo-it-detail').value,
                host_or_endpoint: document.getElementById('topo-host').value,
            }),
        });
        const data = await res.json();
        if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
        window.showToast(data.message, 'success');
        window.closeModal('modal-topology-edit');
        setTimeout(() => { window.location.href = '/?tab=topology'; }, 350);
    });

    window.runTopologyHealthCheck = async function () {
        try {
            const res = await fetch('/topology/healthcheck', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
            window.showToast(data.message, 'success');
            setTimeout(() => { window.location.href = '/?tab=topology'; }, 350);
        } catch {
            window.showToast(T.networkError, 'error');
        }
    };

    document.getElementById('change-password-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        showInlineError('cp-form-error', '');
        const res = await fetch('/profile/password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            body: JSON.stringify({
                current_password: document.getElementById('cp-current').value,
                password: document.getElementById('cp-new').value,
                password_confirmation: document.getElementById('cp-confirm').value,
            }),
        });
        const data = await res.json();
        if (!res.ok) return showInlineError('cp-form-error', extractErrorMessage(data, T.saveFailed));
        window.showToast(data.message, 'success');
        e.target.reset();
        window.closeModal('modal-change-password');
    });

    window.generateStrongPassword = function (inputId) {
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const nums = '23456789';
        const all = upper + lower + nums;
        let pwd = upper[Math.floor(Math.random() * upper.length)]
            + lower[Math.floor(Math.random() * lower.length)]
            + nums[Math.floor(Math.random() * nums.length)];
        for (let i = 0; i < 7; i++) {
            pwd += all[Math.floor(Math.random() * all.length)];
        }
        const el = document.getElementById(inputId);
        if (el) {
            el.value = pwd;
            el.select?.();
        }
    };

    function refreshAdminStats() {
        const t = document.getElementById('admin-stat-total');
        const a = document.getElementById('admin-stat-active');
        const i = document.getElementById('admin-stat-inactive');
        const s = document.getElementById('admin-stat-sessions');
        if (t) t.textContent = MANAGED_USERS.length;
        if (a) a.textContent = MANAGED_USERS.filter((u) => u.is_active).length;
        if (i) i.textContent = MANAGED_USERS.filter((u) => !u.is_active).length;
        if (s) s.textContent = MANAGED_USERS.reduce((acc, u) => acc + Number(u.active_sessions_count || 0), 0);
    }

    function renderAdminUsers() {
        const tbody = document.getElementById('admin-users-tbody');
        const emptyEl = document.getElementById('admin-users-empty');
        if (!tbody) return;

        const q = adminFilterState.search.trim().toLowerCase();
        const filtered = MANAGED_USERS.filter((u) => {
            if (adminFilterState.role && u.role !== adminFilterState.role) return false;
            if (adminFilterState.dept) {
                const matchPrimary = String(u.department_id || '') === adminFilterState.dept;
                const matchExtra = Array.isArray(u.extra_permissions) && u.extra_permissions.some((p) => String(p.department_id) === adminFilterState.dept);
                if (!matchPrimary && !matchExtra) return false;
            }
            if (adminFilterState.status === 'active' && !u.is_active) return false;
            if (adminFilterState.status === 'inactive' && u.is_active) return false;
            if (q) {
                const extraStr = (u.extra_permissions || []).map((p) => `${p.department?.code || ''} ${p.department?.name || ''}`).join(' ');
                const hay = `${u.name} ${u.email} ${u.role} ${u.department?.code || ''} ${u.department?.name || ''} ${extraStr}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const uCountEl = document.getElementById('admin-users-count');
        if (uCountEl) uCountEl.textContent = `${filtered.length}/${MANAGED_USERS.length}`;
        const hasAdminFilter = !!(q || adminFilterState.role || adminFilterState.dept || adminFilterState.status !== 'all');
        document.getElementById('admin-users-reset-btn')?.classList.toggle('hidden', !hasAdminFilter);

        emptyEl?.classList.toggle('hidden', filtered.length > 0);

        const roleBadges = {
            gm: 'bg-navy text-white',
            hod: 'bg-teal/10 text-teal border border-teal/20',
            staff: 'bg-slate-100 text-slate-700 border border-slate-200',
            it_admin: 'bg-amber-50 text-amber-800 border border-amber-200',
        };

        const pageUsers = paginateAndSort(
            filtered,
            tableStates.users,
            JSON.stringify(adminFilterState),
            {
                name: (u) => u.name || '',
                role: (u) => u.role || '',
                department: (u) => ['gm', 'it_admin'].includes(u.role) ? 'Executive' : (u.department?.name || ''),
                last_login_at: (u) => u.last_login_at || '',
                is_active: (u) => u.is_active ? 1 : 0,
            },
            'admin-users-pagination',
            renderAdminUsers
        );

        tbody.innerHTML = pageUsers.map((u) => {
            const initials = escapeHtml((u.name || 'U').slice(0, 2).toUpperCase());
            const isSelf = u.id === CURRENT_USER.id;
            const deptText = ['gm', 'it_admin'].includes(u.role)
                ? (IS_EN ? 'Executive (All Departments)' : 'Executive (Semua Departemen)')
                : (u.department ? `${u.department.name} (${u.department.code})` : '-');
            const lastLogin = u.last_login_at ? formatWita(u.last_login_at, true) : (IS_EN ? 'Never' : 'Belum pernah');
            const sessions = Number(u.active_sessions_count || 0);
            const extraPerms = Array.isArray(u.extra_permissions) ? u.extra_permissions : [];
            const permColors = {
                review: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                input: 'bg-cyan-50 text-teal border-cyan-200',
                read: 'bg-slate-100 text-slate-600 border-slate-200',
            };
            const permBadgesHtml = (u.is_read_only || extraPerms.length > 0)
                ? `<div class="flex flex-wrap gap-1 mt-1">
                    ${u.is_read_only ? '<span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-600 font-mono-num text-[10px] font-semibold">PRIMARY: READ-ONLY</span>' : ''}
                    ${extraPerms.map((p) => `<span class="px-1.5 py-0.5 rounded border font-mono-num text-[10px] font-semibold ${permColors[p.access_level] || permColors.read}">+${escapeHtml(p.department?.code || p.department_id)}: ${escapeHtml((p.access_level || '').toUpperCase())}</span>`).join('')}
                </div>`
                : '';

            return `<tr class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-5 align-middle min-w-[230px]">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-navy/10 text-navy font-heading font-bold text-xs flex items-center justify-center shrink-0">${initials}</span>
                        <div class="min-w-0">
                            <div class="font-semibold text-navy flex items-center gap-1.5">
                                <span class="truncate">${escapeHtml(u.name)}</span>
                                ${isSelf ? `<span class="text-[10px] px-1.5 py-0.5 rounded bg-teal/10 text-teal font-semibold">${IS_EN ? 'You' : 'Anda'}</span>` : ''}
                            </div>
                            <div class="font-mono-num text-[11px] text-slate-500 truncate mt-0.5">${escapeHtml(u.email)}</div>
                        </div>
                    </div>
                </td>
                <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-mono-num font-semibold ${roleBadges[u.role] || roleBadges.staff}">
                        ${escapeHtml((u.role || '').toUpperCase())}
                    </span>
                </td>
                <td class="py-3.5 px-5 align-middle min-w-[200px] space-y-1">
                    <div class="text-slate-700 font-medium">${escapeHtml(deptText)}</div>
                    ${permBadgesHtml}
                </td>
                <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                    <div class="font-mono-num text-[11px] text-slate-700">${escapeHtml(lastLogin)}</div>
                    <div class="text-[11px] text-slate-400 font-mono-num mt-0.5">
                        ${sessions > 0 ? `<span class="text-emerald-700 font-semibold">${sessions} ${IS_EN ? 'active session(s)' : 'sesi aktif'}</span>` : (IS_EN ? '0 active sessions' : '0 sesi aktif')}
                        ${u.last_login_ip ? ` &bull; ${escapeHtml(u.last_login_ip)}` : ''}
                    </div>
                </td>
                <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                    <button type="button" onclick="toggleAdminUserStatus(${u.id})" ${isSelf ? 'disabled' : ''}
                        title="${isSelf ? (IS_EN ? 'Cannot deactivate own account' : 'Tidak dapat menonaktifkan akun sendiri') : (IS_EN ? 'Click to toggle status' : 'Klik untuk ubah status')}"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold border transition ${isSelf ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer'} ${u.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-crimson-dark border-rose-200 hover:bg-rose-100'}">
                        ${u.is_active ? (IS_EN ? 'Active' : 'Aktif') : (IS_EN ? 'Inactive' : 'Nonaktif')}
                    </button>
                </td>
                <td class="py-3.5 px-5 align-middle text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <button type="button" onclick="openUserModalById(${u.id})" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-navy hover:text-white text-navy font-semibold transition cursor-pointer">
                            ${T.edit}
                        </button>
                        <button type="button" onclick="openAdminResetPasswordModalById(${u.id})" class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-600 hover:text-white text-amber-800 font-semibold transition cursor-pointer">
                            Reset PW
                        </button>
                        ${(!isSelf && sessions > 0) ? `
                            <button type="button" onclick="revokeAdminUserSessions(${u.id})" class="px-2.5 py-1.5 rounded-lg bg-teal/10 hover:bg-teal hover:text-white text-teal font-semibold transition cursor-pointer">
                                ${IS_EN ? 'Kick' : 'Putus Sesi'}
                            </button>` : ''}
                        ${!isSelf ? `
                            <button type="button" onclick="openDeleteUserModal(${u.id})" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-crimson hover:text-white text-crimson-dark font-semibold transition cursor-pointer">
                                ${T.del}
                            </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        refreshAdminStats();
    }

    function isAuditAlertEvent(log) {
        const ev = String(log?.event || '').toLowerCase();
        return ev.includes('fail') || ev.includes('block') || ev.includes('delete') || ev.includes('revoke') || ev.includes('disable') || ev.includes('denied');
    }

    function auditModuleBadgeCss(moduleName) {
        const map = {
            Auth: 'bg-navy/10 text-navy border-navy/30',
            RBAC: 'bg-amber-50 text-amber-800 border-amber-200',
            Reports: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            POMEC: 'bg-cyan-50 text-teal border-cyan-200',
            Topology: 'bg-slate-100 text-slate-700 border-slate-200',
            Chatbot: 'bg-teal/10 text-teal border-teal/20',
            Templates: 'bg-slate-100 text-navy border-slate-200',
            Database: 'bg-rose-50 text-crimson-dark border-rose-200',
        };
        return map[moduleName] || 'bg-slate-100 text-slate-700 border-slate-200';
    }

    function auditEventBadgeCss(log) {
        if (isAuditAlertEvent(log)) return 'bg-rose-50 text-crimson-dark border-rose-200';
        const ev = String(log?.event || '').toLowerCase();
        if (ev.includes('created') || ev.includes('review') || ev.includes('enabled') || ev.includes('restore')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        return 'bg-slate-100 text-navy border-slate-200';
    }

    function syncAuditModulePills() {
        document.querySelectorAll('.audit-module-pill').forEach((btn) => {
            const active = (btn.dataset.auditModule || '') === auditModuleFilter;
            btn.className = 'audit-module-pill px-3 py-1 rounded-xl text-xs font-semibold border transition cursor-pointer ' +
                (active ? 'bg-navy text-white border-navy' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100');
        });
    }

    function getFilteredAuditLogs() {
        const q = auditSearchQuery.trim().toLowerCase();
        return AUDIT_LOGS.filter((log) => {
            if (auditModuleFilter && log.module !== auditModuleFilter) return false;
            const actorName = log.user?.name || 'System/Guest';
            if (auditActorFilter && actorName !== auditActorFilter) return false;
            if (auditSeverityFilter === 'alert' && !isAuditAlertEvent(log)) return false;
            if (auditSeverityFilter === 'security' && log.module !== 'Auth' && log.module !== 'RBAC') return false;
            if (auditSeverityFilter === 'data' && log.module !== 'Reports' && log.module !== 'POMEC' && log.module !== 'Templates') return false;
            if (auditDateFrom && formatWitaIsoDate(log.created_at) < auditDateFrom) return false;
            if (q) {
                const metaText = log.metadata ? JSON.stringify(log.metadata) : '';
                const hay = `${log.event || ''} ${log.description || ''} ${actorName} ${log.user?.email || ''} ${log.module || ''} ${log.ip_address || ''} ${metaText}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function renderAdminAuditLogs() {
        const listEl = document.getElementById('admin-audit-list');
        if (!listEl) return;

        syncAuditModulePills();
        const filtered = getFilteredAuditLogs();

        const setElText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val);
        };
        setElText('admin-audit-count', filtered.length);
        setElText('audit-kpi-total', filtered.length);
        setElText('audit-kpi-security', filtered.filter((l) => l.module === 'Auth' || l.module === 'RBAC').length);
        setElText('audit-kpi-ops', filtered.filter((l) => l.module === 'Reports' || l.module === 'POMEC').length);
        setElText('audit-kpi-alerts', filtered.filter(isAuditAlertEvent).length);

        const resetBtn = document.getElementById('admin-audit-reset-btn');
        const hasFilters = Boolean(auditModuleFilter || auditSearchQuery.trim() || auditActorFilter || auditSeverityFilter || auditDateFrom);
        if (resetBtn) resetBtn.classList.toggle('hidden', !hasFilters);

        if (filtered.length === 0) {
            listEl.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-xs text-slate-400">${IS_EN ? 'No audit entries match your filter criteria.' : 'Belum ada catatan audit yang cocok dengan kriteria filter.'}</td></tr>`;
            renderPaginationFooter('admin-audit-pagination', tableStates.audit, 0, 1, 0, 0, renderAdminAuditLogs);
            return;
        }

        const filterSig = `${auditModuleFilter}|${auditSearchQuery.trim().toLowerCase()}|${auditActorFilter}|${auditSeverityFilter}|${auditDateFrom}`;
        const pageLogs = paginateAndSort(
            filtered,
            tableStates.audit,
            filterSig,
            {
                created_at: (l) => l.created_at || '',
                module: (l) => l.module || '',
                event: (l) => l.event || '',
                actor: (l) => l.user?.name || 'System/Guest',
            },
            'admin-audit-pagination',
            renderAdminAuditLogs
        );

        listEl.innerHTML = pageLogs.map((log) => {
            const actorName = log.user?.name || 'System/Guest';
            const metaKeys = log.metadata && typeof log.metadata === 'object' ? Object.keys(log.metadata) : [];
            const metaBtn = metaKeys.length > 0
                ? `<button type="button" onclick="openAuditDetailModal(${Number(log.id)})" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-navy hover:text-white text-[10px] font-mono-num font-semibold text-teal border border-slate-200 transition cursor-pointer">Payload (${metaKeys.length})</button>`
                : '';

            return `
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="py-3.5 px-5 align-top whitespace-nowrap font-mono-num text-[11px] text-slate-500">${escapeHtml(formatWita(log.created_at, true))}</td>
                    <td class="py-3.5 px-5 align-top whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold border ${auditModuleBadgeCss(log.module)}">${escapeHtml(log.module || '-')}</span>
                    </td>
                    <td class="py-3.5 px-5 align-top whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-lg text-[11px] font-mono-num font-semibold border ${auditEventBadgeCss(log)}">${escapeHtml(log.event || '-')}</span>
                    </td>
                    <td class="py-3.5 px-5 align-top whitespace-nowrap">
                        <div class="font-semibold text-navy">${escapeHtml(actorName)}</div>
                        <div class="font-mono-num text-[10px] text-slate-400 mt-0.5">${escapeHtml(log.ip_address || '-')}</div>
                    </td>
                    <td class="py-3.5 px-5 align-top text-slate-600 leading-snug max-w-md">
                        <div>${escapeHtml(log.description || '-')}</div>
                        ${metaBtn}
                    </td>
                </tr>`;
        }).join('');
    }

    function renderAuditWorkflowLogs() {
        const tbody = document.getElementById('audit-workflow-tbody');
        if (!tbody) return;

        const q = auditWorkflowSearch.trim().toLowerCase();
        const filtered = REPORT_HISTORIES.filter((h) => {
            if (auditWorkflowActionFilter && h.action !== auditWorkflowActionFilter) return false;
            if (q) {
                const hay = `${h.report?.report_code || ''} ${h.report?.department?.code || ''} ${h.user?.name || ''} ${h.action || ''} ${h.note || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const countEl = document.getElementById('audit-workflow-count');
        if (countEl) countEl.textContent = filtered.length;

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-slate-400">${IS_EN ? 'No workflow audit entries match your filter.' : 'Tidak ada jejak revisi laporan yang cocok.'}</td></tr>`;
            renderPaginationFooter('audit-workflow-pagination', tableStates.auditWorkflow, 0, 1, 0, 0, renderAuditWorkflowLogs);
            return;
        }

        const pageItems = paginateAndSort(
            filtered,
            tableStates.auditWorkflow,
            `${auditWorkflowActionFilter}|${q}`,
            { created_at: (h) => h.created_at || '' },
            'audit-workflow-pagination',
            renderAuditWorkflowLogs
        );

        const actionCss = {
            reviewed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            submitted: 'bg-amber-50 text-amber-800 border-amber-200',
            returned: 'bg-rose-50 text-crimson-dark border-rose-200',
            in_review: 'bg-cyan-50 text-teal border-cyan-200',
        };

        tbody.innerHTML = pageItems.map((h) => `
            <tr class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-5 font-mono-num text-[11px] text-slate-500 whitespace-nowrap">${escapeHtml(formatWita(h.created_at, true))}</td>
                <td class="py-3.5 px-5 font-mono-num font-semibold text-navy whitespace-nowrap">${escapeHtml(h.report?.report_code || '-')}</td>
                <td class="py-3.5 px-5 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-navy font-mono-num text-[10px] font-semibold">${escapeHtml(h.report?.department?.code || '-')}</span>
                </td>
                <td class="py-3.5 px-5 whitespace-nowrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold border ${actionCss[h.action] || 'bg-slate-100 text-slate-700 border-slate-200'}">${escapeHtml(String(h.action || '').toUpperCase())}</span>
                </td>
                <td class="py-3.5 px-5 text-slate-700 font-medium whitespace-nowrap">${escapeHtml(h.user?.name || 'System')}</td>
                <td class="py-3.5 px-5 text-slate-600">${escapeHtml(h.note || '-')}</td>
            </tr>`).join('');
    }

    window.switchAuditSubTab = function (subKey) {
        const isWorkflow = subKey === 'workflow';
        document.getElementById('audit-subpanel-system')?.classList.toggle('hidden', isWorkflow);
        document.getElementById('audit-subpanel-workflow')?.classList.toggle('hidden', !isWorkflow);
        document.querySelectorAll('.audit-subtab-btn').forEach((btn) => {
            const active = btn.dataset.auditSubtab === subKey;
            btn.className = 'audit-subtab-btn px-3 py-1.5 rounded-lg transition cursor-pointer ' +
                (active ? 'bg-navy text-white shadow-2xs' : 'text-slate-600 hover:text-navy');
        });
        if (isWorkflow) renderAuditWorkflowLogs();
    };

    window.resetAuditFilters = function () {
        auditModuleFilter = '';
        auditSearchQuery = '';
        auditActorFilter = '';
        auditSeverityFilter = '';
        auditDateFrom = '';
        const assignVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        assignVal('admin-audit-search', '');
        assignVal('admin-audit-module-filter', '');
        assignVal('admin-audit-actor-filter', '');
        assignVal('admin-audit-severity-filter', '');
        assignVal('admin-audit-date-from', '');
        renderAdminAuditLogs();
    };

    window.openAuditDetailModal = function (logId) {
        const log = AUDIT_LOGS.find((item) => Number(item.id) === Number(logId));
        if (!log) return;
        const setEl = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };
        setEl('audit-modal-subtitle', formatWita(log.created_at, true));
        setEl('audit-modal-event', `${log.module || '-'} • ${log.event || '-'}`);
        setEl('audit-modal-actor', `${log.user?.name || 'System/Guest'} (${log.ip_address || '-'})`);
        setEl('audit-modal-desc', log.description || '-');
        const metaEl = document.getElementById('audit-modal-meta');
        if (metaEl) {
            metaEl.textContent = log.metadata && Object.keys(log.metadata).length > 0
                ? JSON.stringify(log.metadata, null, 2)
                : '{}';
        }
        window.openModal('modal-audit-detail');
    };

    window.exportAuditLogsCsv = function () {
        const rows = getFilteredAuditLogs();
        if (rows.length === 0) {
            return window.showToast(IS_EN ? 'No audit rows to export.' : 'Tidak ada baris audit untuk diekspor.', 'error');
        }
        const csvCell = (val) => `"${String(val ?? '').replace(/"/g, '""')}"`;
        const header = ['Timestamp (WITA)', 'Module', 'Event', 'Actor', 'IP Address', 'Description', 'Metadata'];
        const lines = [header.map(csvCell).join(',')];
        rows.forEach((l) => {
            lines.push([
                formatWita(l.created_at, true),
                l.module || '',
                l.event || '',
                l.user?.name || 'System/Guest',
                l.ip_address || '',
                l.description || '',
                l.metadata ? JSON.stringify(l.metadata) : '',
            ].map(csvCell).join(','));
        });
        const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `swissbel-audit-trail-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        window.showToast(IS_EN ? 'Audit CSV downloaded.' : 'File CSV audit berhasil diunduh.', 'success');
    };

    window.resetAdminUserFilters = function () {
        adminFilterState = { search: '', role: '', dept: '', status: 'all' };
        const s = document.getElementById('admin-user-search');
        const r = document.getElementById('admin-user-role-filter');
        const d = document.getElementById('admin-user-dept-filter');
        if (s) s.value = '';
        if (r) r.value = '';
        if (d) d.value = '';
        document.querySelectorAll('.admin-status-btn').forEach((b) => {
            const active = b.dataset.status === 'all';
            b.className = 'admin-status-btn px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer ' +
                (active ? 'bg-navy text-white border-navy' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100');
        });
        renderAdminUsers();
    };

    document.getElementById('admin-user-search')?.addEventListener('input', (e) => {
        adminFilterState.search = e.target.value;
        renderAdminUsers();
    });
    document.getElementById('admin-user-role-filter')?.addEventListener('change', (e) => {
        adminFilterState.role = e.target.value;
        renderAdminUsers();
    });
    document.getElementById('admin-user-dept-filter')?.addEventListener('change', (e) => {
        adminFilterState.dept = e.target.value;
        renderAdminUsers();
    });
    document.querySelectorAll('.admin-status-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            adminFilterState.status = btn.dataset.status;
            document.querySelectorAll('.admin-status-btn').forEach((b) => {
                const active = b.dataset.status === adminFilterState.status;
                b.className = 'admin-status-btn px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer ' +
                    (active ? 'bg-navy text-white border-navy' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100');
            });
            renderAdminUsers();
        });
    });
    document.getElementById('admin-audit-search')?.addEventListener('input', (e) => {
        auditSearchQuery = e.target.value;
        renderAdminAuditLogs();
    });
    document.getElementById('admin-audit-module-filter')?.addEventListener('change', (e) => {
        auditModuleFilter = e.target.value;
        renderAdminAuditLogs();
    });
    document.getElementById('admin-audit-actor-filter')?.addEventListener('change', (e) => {
        auditActorFilter = e.target.value;
        renderAdminAuditLogs();
    });
    document.getElementById('admin-audit-severity-filter')?.addEventListener('change', (e) => {
        auditSeverityFilter = e.target.value;
        renderAdminAuditLogs();
    });
    document.getElementById('admin-audit-date-from')?.addEventListener('change', (e) => {
        auditDateFrom = e.target.value;
        renderAdminAuditLogs();
    });
    document.querySelectorAll('.audit-module-pill').forEach((btn) => {
        btn.addEventListener('click', () => {
            auditModuleFilter = btn.dataset.auditModule || '';
            const sel = document.getElementById('admin-audit-module-filter');
            if (sel) sel.value = auditModuleFilter;
            renderAdminAuditLogs();
        });
    });
    document.getElementById('audit-workflow-search')?.addEventListener('input', (e) => {
        auditWorkflowSearch = e.target.value;
        renderAuditWorkflowLogs();
    });
    document.getElementById('audit-workflow-action-filter')?.addEventListener('change', (e) => {
        auditWorkflowActionFilter = e.target.value;
        renderAuditWorkflowLogs();
    });

    function upsertManagedUser(userObj) {
        const idx = MANAGED_USERS.findIndex((u) => u.id === userObj.id);
        if (idx >= 0) MANAGED_USERS[idx] = userObj;
        else MANAGED_USERS.push(userObj);
        renderAdminUsers();
    }

    window.openUserModalById = function (userId) {
        const u = MANAGED_USERS.find((item) => item.id === userId);
        if (u) window.openUserModal(u);
    };

    window.openAdminResetPasswordModalById = function (userId) {
        const u = MANAGED_USERS.find((item) => item.id === userId);
        if (u) window.openAdminResetPasswordModal(u);
    };

    function syncUserPermissionControls() {
        const role = document.getElementById('user-role')?.value || 'staff';
        const deptSelect = document.getElementById('user-dept-id');
        const isScopedRole = ['hod', 'staff'].includes(role);
        if (deptSelect) {
            if (!isScopedRole) deptSelect.value = '';
            deptSelect.disabled = !isScopedRole;
            deptSelect.classList.toggle('bg-slate-100', !isScopedRole);
            deptSelect.classList.toggle('text-slate-500', !isScopedRole);
            deptSelect.classList.toggle('cursor-not-allowed', !isScopedRole);
        }
        const primaryDeptId = String(deptSelect?.value || '');

        document.getElementById('user-readonly-wrap')?.classList.toggle('hidden', !isScopedRole);
        document.getElementById('user-extra-perms-section')?.classList.toggle('hidden', !isScopedRole);
        document.getElementById('user-all-scope-notice')?.classList.toggle('hidden', isScopedRole);

        document.querySelectorAll('.user-extra-perm-row').forEach((row) => {
            const rowDeptId = String(row.dataset.deptId || '');
            const isPrimary = primaryDeptId !== '' && rowDeptId === primaryDeptId;
            const pill = row.querySelector('.user-primary-pill');
            const select = row.querySelector('.user-extra-perm-select');
            pill?.classList.toggle('hidden', !isPrimary);
            if (select) {
                if (isPrimary) {
                    select.value = '';
                    select.disabled = true;
                    select.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    select.disabled = false;
                    select.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
        });
    }

    window.presetExtraPerms = function (level) {
        document.querySelectorAll('.user-extra-perm-select').forEach((sel) => {
            if (!sel.disabled) sel.value = level;
        });
    };

    document.getElementById('user-role')?.addEventListener('change', syncUserPermissionControls);
    document.getElementById('user-dept-id')?.addEventListener('change', syncUserPermissionControls);

    window.openUserModal = function (u = null) {
        showInlineError('user-form-error', '');
        document.getElementById('user-edit-id').value = u ? u.id : '';
        document.getElementById('user-password-wrap').classList.toggle('hidden', !!u);
        document.getElementById('user-password').required = !u;
        document.getElementById('user-active-wrap').classList.toggle('hidden', !u);

        document.querySelectorAll('.user-extra-perm-select').forEach((sel) => {
            sel.value = '';
        });

        if (u) {
            document.getElementById('user-modal-title').textContent = `${T.editUser}: ${u.name}`;
            document.getElementById('user-name').value = u.name;
            document.getElementById('user-email').value = u.email;
            document.getElementById('user-role').value = u.role;
            document.getElementById('user-dept-id').value = u.department_id || '';
            document.getElementById('user-active').checked = !!u.is_active;
            document.getElementById('user-read-only').checked = !!u.is_read_only;
            (u.extra_permissions || []).forEach((perm) => {
                const sel = document.querySelector(`.user-extra-perm-select[data-dept-id="${perm.department_id}"]`);
                if (sel) sel.value = perm.access_level;
            });
        } else {
            document.getElementById('user-modal-title').textContent = T.addUser;
            document.getElementById('user-name').value = '';
            document.getElementById('user-email').value = '';
            document.getElementById('user-password').value = '';
            document.getElementById('user-role').value = 'staff';
            document.getElementById('user-dept-id').value = '';
            document.getElementById('user-read-only').checked = false;
        }
        syncUserPermissionControls();
        window.openModal('modal-user-form');
    };

    document.getElementById('user-crud-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        showInlineError('user-form-error', '');
        const id = document.getElementById('user-edit-id').value;
        const url = id ? `/admin/users/${id}` : '/admin/users';
        const method = id ? 'PUT' : 'POST';
        const role = document.getElementById('user-role').value;
        const extraPerms = [];
        if (['hod', 'staff'].includes(role)) {
            document.querySelectorAll('.user-extra-perm-select').forEach((sel) => {
                if (!sel.disabled && sel.value) {
                    extraPerms.push({
                        department_id: Number(sel.dataset.deptId),
                        access_level: sel.value,
                    });
                }
            });
        }

        const payload = {
            name: document.getElementById('user-name').value.trim(),
            email: document.getElementById('user-email').value.trim(),
            role,
            department_id: document.getElementById('user-dept-id').value || null,
            is_read_only: ['hod', 'staff'].includes(role) ? !!document.getElementById('user-read-only')?.checked : false,
            department_permissions: extraPerms,
        };
        if (id) {
            payload.is_active = document.getElementById('user-active').checked;
        } else {
            payload.password = document.getElementById('user-password').value;
        }

        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) return showInlineError('user-form-error', extractErrorMessage(data, T.saveFailed));
        if (data.user) upsertManagedUser(data.user);
        window.showToast(data.message, 'success');
        window.closeModal('modal-user-form');
    });

    window.toggleAdminUserStatus = async function (userId) {
        const res = await fetch(`/admin/users/${userId}/toggle-status`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
        if (data.user) upsertManagedUser(data.user);
        window.showToast(data.message, 'success');
    };

    window.revokeAdminUserSessions = async function (userId) {
        const res = await fetch(`/admin/users/${userId}/revoke-sessions`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
        if (data.user) upsertManagedUser(data.user);
        window.showToast(data.message, 'success');
    };

    window.openDeleteUserModal = function (userId) {
        const u = MANAGED_USERS.find((item) => item.id === userId);
        if (!u) return;
        document.getElementById('delete-user-id').value = u.id;
        document.getElementById('delete-user-label').textContent = `${u.name} (${u.email})`;
        window.openModal('modal-delete-user');
    };

    window.confirmDeleteUser = async function () {
        const id = Number(document.getElementById('delete-user-id').value);
        const res = await fetch(`/admin/users/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) {
            window.closeModal('modal-delete-user');
            return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
        }
        MANAGED_USERS = MANAGED_USERS.filter((u) => u.id !== id);
        renderAdminUsers();
        window.showToast(data.message, 'success');
        window.closeModal('modal-delete-user');
    };

    window.openAdminResetPasswordModal = function (u) {
        showInlineError('arp-form-error', '');
        document.getElementById('arp-user-id').value = u.id;
        document.getElementById('arp-title').textContent = `Reset Password: ${u.name}`;
        document.getElementById('arp-password').value = '';
        window.openModal('modal-admin-reset-password');
    };

    document.getElementById('admin-reset-password-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        showInlineError('arp-form-error', '');
        const id = document.getElementById('arp-user-id').value;
        const res = await fetch(`/admin/users/${id}/reset-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            body: JSON.stringify({ password: document.getElementById('arp-password').value }),
        });
        const data = await res.json();
        if (!res.ok) return showInlineError('arp-form-error', extractErrorMessage(data, T.saveFailed));
        window.showToast(data.message, 'success');
        window.closeModal('modal-admin-reset-password');
    });

    window.toggleTemplateStatus = async function (templateId) {
        const res = await fetch(`/admin/templates/${templateId}/toggle`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
        window.showToast(data.message, 'success');
        setTimeout(() => { window.location.href = '/?tab=admin'; }, 350);
    };

    // -------------------------------------------------------------------------
    // 8. Floating Pop-Up AI Assistant (#chat-launcher + #chat-widget)
    // -------------------------------------------------------------------------
    (function initChatWidget() {
        const launcher = document.getElementById('chat-launcher');
        const widget = document.getElementById('chat-widget');
        const body = document.getElementById('chat-body');
        if (!widget || !body) return;

        const headerBernieWrap = document.getElementById('chat-header-bernie');
        const headerBernieBtn = document.getElementById('chat-header-bernie-btn');

        const handle = document.getElementById('chat-resize-handle');
        const expandBtn = document.getElementById('chat-expand-btn');
        const minBtn = document.getElementById('chat-minimize-btn');
        const resetBtn = document.getElementById('chat-reset-btn');
        const messagesEl = document.getElementById('chat-messages');
        const filterBarEl = document.getElementById('chat-filter-bar');
        const chatInputEl = document.getElementById('chat-input');
        const chatSendBtnEl = document.getElementById('chat-send-btn');
        const initialWelcome = messagesEl.innerHTML;
        const STORAGE_KEY = 'sbwm_chat_size_v1';

        const CHAT_SOURCE_LABELS = IS_EN
            ? { department_reports: 'Department Reports', report_explain: 'AI Report Analysis', report_stats: 'Report Statistics', report_detail: 'Report Detail', report_compare: 'KPI Comparison', report_row_search: 'Table Row Search', cross_analysis: 'Cross-Module Analysis', pomec_records: 'POMEC Utilities', pomec_compare: 'POMEC Comparison', pomec_trend: 'POMEC Trend', topology_components: 'System Topology', data_availability: 'Data Availability', departments: 'Department List', 'Application Guide': 'System Guide', Glossary: 'Glossary', FAQ: 'FAQ', 'Filter Mode': 'Mode Filter', 'RBAC Guard': 'Access Guard', 'Security Guardrail': 'Security Guard', small_talk: 'Assistant', greeting: 'Welcome', unmatched: 'Unrecognised', out_of_scope: 'Out of Scope' }
            : { department_reports: 'Laporan Departemen', report_explain: 'Analisis AI Laporan', report_stats: 'Statistik Laporan', report_detail: 'Detail Laporan', report_compare: 'Perbandingan KPI', report_row_search: 'Pencarian Baris Tabel', cross_analysis: 'Analisis Lintas Modul', pomec_records: 'Utilitas POMEC', pomec_compare: 'Perbandingan POMEC', pomec_trend: 'Tren POMEC', topology_components: 'Topologi Sistem', data_availability: 'Ketersediaan Data', departments: 'Daftar Departemen', 'Application Guide': 'Panduan Sistem', Glossary: 'Glosarium', FAQ: 'FAQ', 'Filter Mode': 'Mode Filter', 'RBAC Guard': 'Pembatasan Akses', 'Security Guardrail': 'Pengamanan Sistem', small_talk: 'Asisten', greeting: 'Sapaan', unmatched: 'Belum Dikenali', out_of_scope: 'Di Luar Topik' };

        let isExpanded = false;
        let isOpen = false;
        let activeChatTrack = null;
        let isSendingChat = false;

        function setHeaderBernie(state, revertMs = 0) {
            const svg = headerBernieWrap?.querySelector('[data-bernie-state]');
            if (!svg) return;
            svg.dataset.bernieState = state;
            if (revertMs > 0) {
                window.setTimeout(() => {
                    if (!isSendingChat && svg.dataset.bernieState === state) {
                        svg.dataset.bernieState = 'idle';
                    }
                }, revertMs);
            }
        }

        // Contained Bernie Walk strictly inside #chat-launcher pill & welcome #bernie-stage
        const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

        function createContainedWalker(wrapId, flipId, maxSpanFn, pausePredicate) {
            let timer = null;
            let step = 0;

            function setPose(xPx, facingLeft, state, durMs) {
                const wrap = document.getElementById(wrapId);
                const flip = document.getElementById(flipId);
                const svg = wrap?.querySelector('[data-bernie-state]');
                if (!wrap || !flip || !svg) return;
                wrap.style.transition = durMs > 0 ? `transform ${durMs}ms ease-in-out` : 'none';
                wrap.style.transform = `translateX(${xPx}px)`;
                flip.style.transform = facingLeft ? 'scaleX(-1)' : 'scaleX(1)';
                svg.dataset.bernieState = state;
            }

            function tick() {
                window.clearTimeout(timer);
                if (prefersReduced || !document.getElementById(wrapId) || pausePredicate()) {
                    setPose(0, false, 'idle', 0);
                    return;
                }
                const span = Math.max(0, maxSpanFn());
                const phase = step++ % 4;
                if (phase === 0) {
                    setPose(span, false, 'walk', 2200);
                    timer = window.setTimeout(tick, 2300);
                } else if (phase === 1) {
                    setPose(span, false, 'wave', 300);
                    timer = window.setTimeout(tick, 2200);
                } else if (phase === 2) {
                    setPose(0, true, 'walk', 2200);
                    timer = window.setTimeout(tick, 2300);
                } else {
                    setPose(0, false, 'idle', 300);
                    timer = window.setTimeout(tick, 2600);
                }
            }

            return {
                start: (delay = 600) => { window.clearTimeout(timer); timer = window.setTimeout(tick, delay); },
                stop: (state = 'idle') => { window.clearTimeout(timer); setPose(0, false, state, 250); },
            };
        }

        const pillWalker = createContainedWalker('bernie-pill-walker', 'bernie-pill-flip', () => 12, () => isOpen);
        const stageWalker = createContainedWalker(
            'bernie-stage-walker',
            'bernie-stage-flip',
            () => {
                const stage = document.getElementById('bernie-stage');
                const walker = document.getElementById('bernie-stage-walker');
                return stage && walker ? Math.max(24, stage.clientWidth - walker.clientWidth - 110) : 90;
            },
            () => !isOpen || activeChatTrack !== null
        );

        launcher?.addEventListener('mouseenter', () => pillWalker.stop('wave'));
        launcher?.addEventListener('mouseleave', () => { if (!isOpen) pillWalker.start(400); });
        headerBernieBtn?.addEventListener('click', () => setHeaderBernie('celebrate', 1400));
        messagesEl.addEventListener('click', (e) => {
            if (e.target.closest('#bernie-stage-walker')) {
                setHeaderBernie('celebrate', 1200);
            }
        });

        pillWalker.start(700);

        function getTrackChips(track) {
            try {
                return JSON.parse(widget.dataset[track === 'guide' ? 'guideChips' : 'dataChips'] || '[]');
            } catch {
                return [];
            }
        }

        function setChatTrack(track, { announce = false } = {}) {
            activeChatTrack = track === 'guide' || track === 'data' ? track : null;

            if (filterBarEl) {
                filterBarEl.classList.toggle('hidden', activeChatTrack === null);
                filterBarEl.querySelectorAll('[data-chat-filter]').forEach((btn) => {
                    const isThis = btn.dataset.chatFilter === activeChatTrack;
                    const isGuideBtn = btn.dataset.chatFilter === 'guide';
                    btn.className = isThis
                        ? `px-2.5 py-1.5 rounded-lg text-[11px] font-semibold bg-white shadow-2xs transition cursor-pointer truncate ${isGuideBtn ? 'text-teal' : 'text-navy'}`
                        : 'px-2.5 py-1.5 rounded-lg text-[11px] font-semibold text-slate-500 hover:text-navy transition cursor-pointer truncate';
                });
            }

            if (chatInputEl && chatSendBtnEl) {
                const unlocked = activeChatTrack !== null;
                chatInputEl.disabled = !unlocked;
                chatSendBtnEl.disabled = !unlocked;
                if (!unlocked) {
                    chatInputEl.placeholder = IS_EN ? 'Select a mode above to start chatting...' : 'Pilih mode di atas untuk memulai percakapan...';
                } else if (activeChatTrack === 'guide') {
                    chatInputEl.placeholder = IS_EN ? 'Ask system guides, SOP, or glossary...' : 'Ketik pertanyaan panduan, SOP, atau glosarium...';
                } else {
                    chatInputEl.placeholder = IS_EN ? 'Ask department reports, KPIs, or POMEC...' : 'Ketik pertanyaan laporan, KPI, atau POMEC...';
                }
            }

            if (announce && activeChatTrack) {
                const isGuide = activeChatTrack === 'guide';
                const chips = getTrackChips(activeChatTrack).slice(0, 4);
                const replyMsg = isGuide
                    ? (IS_EN
                        ? 'Mode active: **System Guide & SOP**. Choose a topic below or type your question:'
                        : 'Mode aktif: **Panduan Sistem & SOP**. Pilih salah satu topik di bawah atau ketik pertanyaan Anda:')
                    : (IS_EN
                        ? 'Mode active: **Reports & Analytics**. Choose a quick query below or type your question:'
                        : 'Mode aktif: **Laporan & Analisis Data**. Pilih contoh di bawah atau ketik pertanyaan Anda:');

                // Reset previous chat bubbles and server-side context whenever switching mode so threads never pile up.
                messagesEl.innerHTML = '';
                fetch('/chatbot/reset', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' } }).catch(() => {});

                const aWrap = document.createElement('div');
                aWrap.className = 'flex justify-start chat-msg-in';
                aWrap.innerHTML = `
                    <div class="max-w-[95%] bg-white border border-slate-200/80 rounded-2xl rounded-bl-xs px-3.5 py-2.5 shadow-2xs space-y-2">
                        <div class="text-slate-700 leading-relaxed space-y-1.5">${renderChatText(replyMsg)}</div>
                        ${renderSuggestions(chips)}
                    </div>
                `;
                messagesEl.appendChild(aWrap);
                messagesEl.scrollTop = 0;
                stageWalker.stop('idle');
                setHeaderBernie('wave', 1400);
            }

            if (activeChatTrack) {
                chatInputEl?.focus();
            }
        }

        filterBarEl?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-chat-filter]');
            if (!btn || btn.dataset.chatFilter === activeChatTrack) return;
            setChatTrack(btn.dataset.chatFilter, { announce: true });
        });

        setChatTrack(null);

        function saveSize(w, h) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ w, h })); } catch {}
        }

        if (window.innerWidth >= 640) {
            try {
                const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                if (saved?.w && saved?.h) {
                    widget.style.width = Math.min(780, Math.max(340, saved.w)) + 'px';
                    widget.style.height = Math.min(720, Math.max(320, saved.h)) + 'px';
                }
            } catch {}
        }

        window.restoreChatWidget = function () {
            isOpen = true;
            pillWalker.stop('idle');
            launcher?.classList.add('hidden');
            widget.classList.remove('hidden');
            setHeaderBernie('wave', 2200);
            if (activeChatTrack === null) stageWalker.start(400);
            document.getElementById('chat-input')?.focus();
        };

        window.closeChatWidget = function () {
            isOpen = false;
            stageWalker.stop('idle');
            widget.classList.add('hidden');
            launcher?.classList.remove('hidden');
            pillWalker.start(500);
        };

        launcher?.addEventListener('click', () => window.restoreChatWidget());
        minBtn?.addEventListener('click', () => window.closeChatWidget());

        expandBtn?.addEventListener('click', () => {
            if (!isOpen) window.restoreChatWidget();
            isExpanded = !isExpanded;
            const targetW = isExpanded ? Math.min(640, window.innerWidth - 32) : 440;
            const targetH = isExpanded ? Math.min(640, window.innerHeight - 96) : 540;
            widget.style.width = `${targetW}px`;
            widget.style.height = `${targetH}px`;
            saveSize(targetW, targetH);
        });

        resetBtn?.addEventListener('click', () => {
            setChatTrack(null);
            messagesEl.innerHTML = initialWelcome;
            stageWalker.start(400);
            // Conversation memory lives on the server; clear it there too.
            fetch('/chatbot/reset', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' } }).catch(() => {});
        });

        let resizing = false;
        let startX = 0, startY = 0, startW = 0, startH = 0;

        handle?.addEventListener('mousedown', (e) => {
            e.preventDefault();
            resizing = true;
            startX = e.clientX;
            startY = e.clientY;
            const rect = widget.getBoundingClientRect();
            startW = rect.width;
            startH = rect.height;
        });

        window.addEventListener('mousemove', (e) => {
            if (!resizing) return;
            const newW = Math.min(780, Math.max(340, startW + (startX - e.clientX)));
            const newH = Math.min(720, Math.max(320, startH + (startY - e.clientY)));
            widget.style.width = `${newW}px`;
            widget.style.height = `${newH}px`;
        });

        window.addEventListener('mouseup', () => {
            if (!resizing) return;
            resizing = false;
            const rect = widget.getBoundingClientRect();
            saveSize(Math.round(rect.width), Math.round(rect.height));
        });

        function renderPopupCards(cards) {
            if (!Array.isArray(cards) || cards.length === 0) return '';
            const toneMap = {
                emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                amber: 'bg-amber-50 text-amber-700 border-amber-200',
                crimson: 'bg-rose-50 text-crimson-dark border-rose-200',
                teal: 'bg-cyan-50 text-teal border-cyan-200',
                slate: 'bg-slate-100 text-slate-600 border-slate-200',
            };
            return `<div class="space-y-1.5 pt-1">
                ${cards.map((c) => `
                    <div class="p-2.5 rounded-xl bg-alabaster border border-slate-200/80 space-y-1">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-navy leading-snug">${escapeHtml(c.title)}</span>
                            <span class="shrink-0 font-mono-num text-[11px] px-2 py-0.5 rounded-full border font-semibold ${toneMap[c.badge_tone] || toneMap.slate}">
                                ${escapeHtml(c.badge)}
                            </span>
                        </div>
                        <div class="inline-flex items-center text-[11px] text-slate-500">${escapeHtml(c.meta)}</div>
                    </div>
                `).join('')}
            </div>`;
        }

        function renderSuggestions(list) {
            if (!Array.isArray(list) || list.length === 0) return '';
            return `<div class="flex flex-wrap gap-1.5 pt-1">${list.map((s) => `
                <button type="button" data-chat-suggest="${escapeHtml(s)}"
                    class="text-[11px] px-2.5 py-1 rounded-full border border-slate-200 bg-alabaster text-navy hover:border-teal hover:bg-teal/5 hover:text-teal transition cursor-pointer">${escapeHtml(s)}</button>
            `).join('')}</div>`;
        }

        // Answer feedback (→ chatbot_turns.rating, shown to IT in Admin "Insight Chatbot").
        // Outline icons (Lucide thumbs-up/down paths), quiet until hovered; the chosen one fills with the brand colour.
        const THUMB_PATHS = {
            up: '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
            down: '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/>',
        };
        const thumbIcon = (dir) => `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${THUMB_PATHS[dir]}</svg>`;

        function renderFeedback() {
            const btn = (rating, dir, label) => `<button type="button" data-chat-rate="${rating}" title="${label}" aria-label="${label}"
                class="p-1 rounded-md text-slate-300 hover:text-navy hover:bg-slate-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal/40 cursor-pointer">${thumbIcon(dir)}</button>`;

            return `<span data-chat-feedback class="shrink-0 inline-flex items-center gap-0.5 font-sans">
                ${btn(1, 'up', IS_EN ? 'Helpful' : 'Jawaban membantu')}${btn(-1, 'down', IS_EN ? 'Not helpful' : 'Jawaban kurang tepat')}
            </span>`;
        }

        async function sendFeedback(turnId, rating, comment = null) {
            try {
                await fetch('/chatbot/feedback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    body: JSON.stringify({ turn_id: turnId, rating, comment }),
                });
            } catch { /* feedback is best-effort */ }
        }

        messagesEl.addEventListener('click', (e) => {
            const trackBtn = e.target.closest('[data-chat-track]');
            if (trackBtn) {
                setChatTrack(trackBtn.dataset.chatTrack === 'guide' ? 'guide' : 'data', { announce: true });
                return;
            }

            const btn = e.target.closest('[data-chat-suggest]');
            if (btn) return window.sendChatQuestion(btn.dataset.chatSuggest);

            const copyBtn = e.target.closest('[data-chat-copy]');
            if (copyBtn) {
                const replyEl = copyBtn.closest('[data-chat-bubble]')?.querySelector('[data-chat-reply-text]');
                const text = replyEl?.innerText?.trim() || '';
                if (text && navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(text).then(() => {
                        const prev = copyBtn.innerHTML;
                        copyBtn.textContent = IS_EN ? 'Copied' : 'Tersalin';
                        copyBtn.classList.add('text-teal');
                        window.setTimeout(() => {
                            copyBtn.innerHTML = prev;
                            copyBtn.classList.remove('text-teal');
                        }, 1500);
                    }).catch(() => {});
                }
                return;
            }

            const fb = e.target.closest('[data-chat-rate]');
            if (!fb) return;
            const wrap = fb.closest('[data-turn-id]');
            const group = fb.closest('[data-chat-feedback]');
            const rating = Number(fb.dataset.chatRate);
            const isUp = rating === 1;

            // Keep only the chosen icon, coloured and filled; no further clicks.
            group.querySelectorAll('[data-chat-rate]').forEach((b) => { if (b !== fb) b.remove(); });
            fb.disabled = true;
            fb.className = `p-1 rounded-md ${isUp ? 'text-teal' : 'text-crimson'} cursor-default`;
            fb.querySelector('svg').setAttribute('fill', 'currentColor');
            fb.querySelector('svg').setAttribute('fill-opacity', '0.15');
            group.insertAdjacentHTML('beforeend', `<span class="text-[10px] text-slate-400">${IS_EN ? 'Thanks' : 'Terima kasih'}</span>`);
            sendFeedback(Number(wrap.dataset.turnId), rating);

            // Down-vote: optional one-line reason, inline (no browser prompt).
            if (!isUp) {
                wrap.insertAdjacentHTML('beforeend', `
                    <form data-chat-fb-form class="flex items-center gap-1.5 pt-1">
                        <input name="comment" maxlength="500" autocomplete="off" placeholder="${IS_EN ? 'What was wrong? (optional)' : 'Apa yang kurang tepat? (opsional)'}"
                            class="flex-1 min-w-0 px-2.5 py-1.5 text-[11px] rounded-lg border border-slate-200 bg-alabaster focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                        <button type="submit" class="shrink-0 px-2.5 py-1.5 rounded-lg bg-navy text-white text-[11px] font-semibold hover:bg-navy/90 transition cursor-pointer">${IS_EN ? 'Send' : 'Kirim'}</button>
                    </form>`);
                wrap.querySelector('[data-chat-fb-form] input')?.focus();
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        });

        messagesEl.addEventListener('submit', (e) => {
            const form = e.target.closest('[data-chat-fb-form]');
            if (!form) return;
            e.preventDefault();
            const comment = form.elements.namedItem('comment').value.trim().slice(0, 500);
            if (comment) sendFeedback(Number(form.closest('[data-turn-id]').dataset.turnId), -1, comment);
            form.outerHTML = `<div class="pt-1 text-[11px] text-slate-400">${IS_EN ? 'Thanks — your feedback helps IT improve the assistant.' : 'Terima kasih — masukan Anda membantu IT memperbaiki asisten.'}</div>`;
        });

        window.sendChatQuestion = async function (questionText) {
            const q = String(questionText || '').trim();
            if (!q || isSendingChat) return;
            if (!activeChatTrack) {
                const looksGuide = /^(cara|apa itu|how to|what is|eskalasi|escalat)/i.test(q);
                setChatTrack(looksGuide ? 'guide' : 'data', { announce: false });
            }
            if (!isOpen) window.restoreChatWidget();

            isSendingChat = true;
            if (chatInputEl) chatInputEl.disabled = true;
            if (chatSendBtnEl) chatSendBtnEl.disabled = true;

            const uWrap = document.createElement('div');
            uWrap.className = 'flex justify-end chat-msg-in';
            uWrap.innerHTML = `<div class="max-w-[85%] bg-navy text-white rounded-2xl rounded-br-xs px-3.5 py-2 shadow-2xs">${escapeHtml(q)}</div>`;
            messagesEl.appendChild(uWrap);
            setHeaderBernie('think');

            const stages = activeChatTrack === 'guide'
                ? (IS_EN
                    ? ['Understanding your question…', 'Checking guides & SOP knowledge…', 'Preparing a clear explanation…', 'Almost ready…']
                    : ['Memahami pertanyaan Anda…', 'Menelusuri panduan & SOP terkait…', 'Menyusun penjelasan ringkas…', 'Hampir selesai…'])
                : (IS_EN
                    ? ['Understanding your request…', 'Querying verified report & utility data…', 'Validating numbers & preparing insight…', 'Almost ready, thank you for waiting…']
                    : ['Memahami maksud pertanyaan…', 'Memeriksa data laporan & utilitas sistem…', 'Memvalidasi angka & menyusun analisis…', 'Hampir selesai, mohon tunggu sebentar…']);

            const thinkingMascotHtml = headerBernieWrap
                ? headerBernieWrap.innerHTML.replace(/data-bernie-state="[^"]*"/, 'data-bernie-state="think"').replace('w-7 h-8', 'w-7 h-8')
                : '';

            const lWrap = document.createElement('div');
            lWrap.className = 'flex justify-start chat-msg-in';
            lWrap.innerHTML = `
                <div class="max-w-[88%] min-w-[230px] bg-white border border-slate-200/80 rounded-2xl rounded-bl-xs px-3.5 py-2.5 shadow-2xs space-y-2">
                    <div class="flex items-center gap-2 text-slate-500">
                        ${thinkingMascotHtml ? `<span class="shrink-0 w-8 h-8 rounded-lg bg-alabaster border border-slate-200/70 flex items-end justify-center overflow-hidden">${thinkingMascotHtml}</span>` : ''}
                        <div class="min-w-0 space-y-1 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1 shrink-0" aria-hidden="true">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal animate-bounce [animation-delay:-0.3s]"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal animate-bounce [animation-delay:-0.15s]"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal animate-bounce"></span>
                                </span>
                                <span data-chat-stage class="text-[11px] font-medium text-navy/80 transition-opacity">${escapeHtml(stages[0])}</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full w-4/5 animate-pulse" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
            `;
            messagesEl.appendChild(lWrap);
            messagesEl.scrollTop = messagesEl.scrollHeight;

            let stageIdx = 0;
            const stageEl = lWrap.querySelector('[data-chat-stage]');
            const stageTimer = window.setInterval(() => {
                if (stageIdx < stages.length - 1 && stageEl) {
                    stageIdx++;
                    stageEl.textContent = stages[stageIdx];
                }
            }, 1800);

            try {
                const res = await fetch('/chatbot/ask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    body: JSON.stringify({ question: q, locale: LOCALE, track: activeChatTrack }),
                });
                if (!res.ok) {
                    setHeaderBernie('idle');
                    // Tell the user what actually happened instead of a generic "unavailable".
                    const isEn = LOCALE === 'en';
                    const msg = (res.status === 419 || res.status === 401)
                        ? (isEn ? 'Your session has expired. Please reload the page and sign in again.' : 'Sesi Anda telah berakhir. Silakan muat ulang halaman dan login kembali.')
                        : res.status === 429
                            ? (isEn ? 'Too many questions in a short time. Please wait a minute and try again.' : 'Terlalu banyak pertanyaan dalam waktu singkat. Tunggu sekitar satu menit lalu coba lagi.')
                            : null;
                    if (msg) {
                        lWrap.innerHTML = `<div class="bg-amber-50 border border-amber-200 rounded-2xl px-3.5 py-2 text-amber-800">${msg}${res.status !== 429 ? ` <button type="button" onclick="location.reload()" class="underline font-medium">${isEn ? 'Reload' : 'Muat ulang'}</button>` : ''}</div>`;
                        messagesEl.scrollTop = messagesEl.scrollHeight;
                        return;
                    }
                    throw new Error(`HTTP ${res.status}`);
                }
                const data = await res.json();
                const turnId = Number.parseInt(data.turn_id, 10);
                const rawSrc = data.meta?.source || '-';
                const sourceLabel = CHAT_SOURCE_LABELS[rawSrc] || rawSrc;
                const filterText = data.meta?.filters ? ` &bull; ${escapeHtml(data.meta.filters)}` : '';
                const llmIssue = data.meta?.llm_issue || '';
                const llmErrType = data.meta?.llm_error_type || '';
                const isLimitOrAuth = ['rate_limit', 'quota_exceeded', 'auth_error', 'model_not_found'].includes(llmErrType);
                const issueBanner = llmIssue
                    ? `<div class="rounded-xl border px-2.5 py-1.5 text-[11px] leading-snug ${isLimitOrAuth ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800'}"><span class="font-semibold">${isLimitOrAuth ? (LOCALE === 'en' ? 'Model Limit / Config Notice:' : 'Info Limit / Kuota AI:') : (LOCALE === 'en' ? 'AI Notice:' : 'Catatan AI:')}</span> ${escapeHtml(llmIssue)}</div>`
                    : '';
                setHeaderBernie(llmIssue ? 'idle' : 'celebrate', 1600);
                lWrap.innerHTML = `
                    <div data-chat-bubble class="max-w-[95%] bg-white border border-slate-200/80 rounded-2xl rounded-bl-xs px-3.5 py-2.5 shadow-2xs space-y-2 chat-msg-in"${Number.isFinite(turnId) ? ` data-turn-id="${turnId}"` : ''}>
                        <div data-chat-reply-text class="text-slate-700 leading-relaxed space-y-2">${renderChatText(data.reply || '')}</div>
                        ${renderPopupCards(data.cards)}
                        ${renderSuggestions(data.suggestions)}
                        ${issueBanner}
                        <div class="flex items-center justify-between gap-2 text-[10px] text-slate-400 pt-1.5 border-t border-slate-100">
                            <span class="truncate">${escapeHtml(sourceLabel)}${filterText}</span>
                            <span class="inline-flex items-center gap-1 shrink-0">
                                <button type="button" data-chat-copy title="${IS_EN ? 'Copy reply' : 'Salin jawaban'}"
                                    class="p-1 rounded-md text-slate-300 hover:text-navy hover:bg-slate-100 transition cursor-pointer font-sans">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="inline"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                                ${Number.isFinite(turnId) ? renderFeedback() : ''}
                            </span>
                        </div>
                    </div>
                `;
            } catch {
                setHeaderBernie('idle');
                lWrap.innerHTML = `<div class="bg-rose-50 border border-rose-200 rounded-2xl px-3.5 py-2 text-crimson-dark">${T.assistantUnavailable}</div>`;
            } finally {
                window.clearInterval(stageTimer);
                isSendingChat = false;
                if (activeChatTrack !== null) {
                    if (chatInputEl) {
                        chatInputEl.disabled = false;
                        chatInputEl.focus();
                    }
                    if (chatSendBtnEl) chatSendBtnEl.disabled = false;
                }
            }
            messagesEl.scrollTop = messagesEl.scrollHeight;
        };

        document.getElementById('chat-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const val = input.value;
            input.value = '';
            window.sendChatQuestion(val);
        });
    })();

    function renderGmCharts() {
        const gmData = boot.gmCharts;
        if (!gmData) return;

        const pomecCanvas = document.getElementById('gm-chart-pomec-trend');
        if (pomecCanvas && gmData.pomec) {
            Chart.getChart(pomecCanvas)?.destroy();
            new Chart(pomecCanvas, {
                type: 'bar',
                data: {
                    labels: gmData.pomec.labels || [],
                    datasets: [
                        {
                            label: 'LWBP (kWh)',
                            data: gmData.pomec.lwbp || [],
                            backgroundColor: chartLwbpBarColor(),
                            borderRadius: { topLeft: 4, topRight: 4 },
                            stack: 'elec',
                            yAxisID: 'y',
                            barPercentage: 0.65,
                            categoryPercentage: 0.72,
                            maxBarThickness: 28,
                            order: 2,
                        },
                        {
                            label: 'WBP (kWh)',
                            data: gmData.pomec.wbp || [],
                            backgroundColor: '#F59E0B',
                            borderRadius: { topLeft: 4, topRight: 4 },
                            stack: 'elec',
                            yAxisID: 'y',
                            barPercentage: 0.65,
                            categoryPercentage: 0.72,
                            maxBarThickness: 28,
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: IS_EN ? 'PDAM Water (m³)' : 'Air PDAM (m³)',
                            data: gmData.pomec.water || [],
                            borderColor: '#1E6B7B',
                            backgroundColor: makeChartAreaGradient('rgba(30, 107, 123, 0.16)', 'rgba(30, 107, 123, 0.0)'),
                            fill: true,
                            borderWidth: 2.25,
                            pointRadius: 3,
                            pointBackgroundColor: '#1E6B7B',
                            pointBorderColor: chartPointBorderColor(),
                            pointBorderWidth: 1.5,
                            pointHoverRadius: 5.5,
                            pointHoverBorderWidth: 2,
                            tension: 0.38,
                            yAxisID: 'y1',
                            order: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: chartStagger(18, 200),
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' } },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { size: 11 } },
                        },
                        y: {
                            stacked: true,
                            position: 'left',
                            grid: { color: chartGridColor() },
                            border: { display: false },
                            ticks: {
                                font: { size: 11 },
                                callback: (v) => `${Number(v).toLocaleString('id-ID')} kWh`,
                            },
                        },
                        y1: {
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            border: { display: false },
                            ticks: {
                                font: { size: 11 },
                                callback: (v) => `${v} m³`,
                            },
                        },
                    },
                },
            });
        }

        const statusCanvas = document.getElementById('gm-chart-report-status');
        if (statusCanvas && gmData.reportStatus) {
            Chart.getChart(statusCanvas)?.destroy();
            const rs = gmData.reportStatus;
            const hasAny = (rs.reviewed + rs.submitted + rs.returned + rs.draft) > 0;
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: [
                        IS_EN ? 'Reviewed' : 'Disetujui HOD',
                        IS_EN ? 'Submitted' : 'Menunggu Review',
                        IS_EN ? 'Returned' : 'Perlu Revisi',
                        'Draft',
                    ],
                    datasets: [{
                        data: hasAny ? [rs.reviewed, rs.submitted, rs.returned, rs.draft] : [1],
                        backgroundColor: hasAny ? ['#059669', '#D97706', '#F04E30', isDarkTheme() ? '#475569' : '#CBD5E1'] : chartEmptyTrackColor(),
                        borderWidth: 0,
                        borderRadius: hasAny ? 4 : 0,
                        spacing: hasAny ? 2 : 0,
                        hoverOffset: hasAny ? 5 : 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '76%',
                    rotation: -90,
                    animation: doughnutAnimation(),
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: hasAny },
                    },
                },
            });
        }

        const hkCanvas = document.getElementById('gm-chart-hk-rooms');
        if (hkCanvas && gmData.hkRooms) {
            Chart.getChart(hkCanvas)?.destroy();
            const hk = gmData.hkRooms;
            new Chart(hkCanvas, {
                type: 'bar',
                data: {
                    labels: ['Vacant Clean (VC)', 'Occupied Clean (OC)', 'Vacant Dirty (VD)', 'Out of Order (OOO)'],
                    datasets: [{
                        label: IS_EN ? 'Rooms' : 'Jumlah Kamar',
                        data: [hk.vc || 0, hk.oc || 0, hk.vd || 0, hk.ooo || 0],
                        backgroundColor: ['#059669', '#1E6B7B', '#F59E0B', '#F04E30'],
                        borderRadius: 5,
                        barThickness: 16,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: chartStagger(30, 180),
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: chartGridColor() },
                            border: { display: false },
                            ticks: { precision: 0, font: { size: 11 } },
                        },
                        y: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { size: 11, weight: '600' }, color: chartStrongLabelColor() },
                        },
                    },
                },
            });
        }
    }

    try {
        renderGmCharts();
    } catch (err) {
        console.error('Failed to render GM charts:', err);
    }
    function renderReportHistories() {
        const tbody = document.getElementById('report-histories-tbody');
        const countEl = document.getElementById('report-history-count');
        if (!tbody) return;

        const q = reportFilterState.search.trim().toLowerCase();
        const filtered = REPORT_HISTORIES.filter((h) => {
            if (historyActionFilter && h.action !== historyActionFilter) return false;
            if (reportFilterState.dept && h.report?.department?.code !== reportFilterState.dept) return false;
            if (reportFilterState.period && h.report?.period_type !== reportFilterState.period) return false;
            if (reportFilterState.date && formatWitaIsoDate(h.report?.period_date) !== reportFilterState.date) return false;
            if (reportFilterState.status !== 'all' && h.report?.status !== reportFilterState.status) return false;
            if (q) {
                const hay = `${h.report?.report_code || ''} ${h.report?.title || ''} ${h.report?.department?.code || ''} ${h.user?.name || ''} ${h.note || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        if (countEl) countEl.textContent = `${filtered.length} ${IS_EN ? 'events' : 'aktivitas'}`;

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-slate-400">${IS_EN ? 'No workflow history matches your filter.' : 'Tidak ada riwayat aktivitas laporan yang sesuai filter.'}</td></tr>`;
            renderPaginationFooter('report-histories-pagination', tableStates.histories, 0, 1, 0, 0, renderReportHistories);
            return;
        }

        const pageHistories = paginateAndSort(
            filtered,
            tableStates.histories,
            `${historyActionFilter}|${JSON.stringify(reportFilterState)}`,
            {
                created_at: (h) => h.created_at || '',
                report: (h) => h.report?.report_code || '',
                action: (h) => h.action || '',
                user: (h) => h.user?.name || '',
            },
            'report-histories-pagination',
            renderReportHistories
        );

        const actionClasses = {
            reviewed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            in_review: 'bg-cyan-50 text-teal border-cyan-200',
            returned: 'bg-rose-50 text-crimson-dark border-rose-200',
            submitted: 'bg-amber-50 text-amber-800 border-amber-200',
        };

        tbody.innerHTML = pageHistories.map((h) => {
            const cls = actionClasses[h.action] || 'bg-slate-100 text-slate-700 border-slate-200';
            const transition = `${h.from_status ? `${escapeHtml(h.from_status.toUpperCase())} &rarr; ` : ''}${escapeHtml((h.to_status || '').toUpperCase())}`;
            const rptHtml = h.report
                ? `<button type="button" onclick="openReportDetail(${h.report.id})" class="font-mono-num font-bold text-navy hover:text-teal cursor-pointer">${escapeHtml(h.report.report_code)}</button>
                   <span class="ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono-num text-[10px] font-semibold">${escapeHtml(h.report.department?.code || '')}</span>
                   <div class="text-slate-600 truncate max-w-[260px] mt-0.5">${escapeHtml(h.report.title)}</div>`
                : '<span class="text-slate-400">-</span>';

            return `<tr class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-5 font-mono-num text-slate-600 whitespace-nowrap">${escapeHtml(formatWita(h.created_at, true))}</td>
                <td class="py-3.5 px-5 min-w-[220px]">${rptHtml}</td>
                <td class="py-3.5 px-5 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${cls}">${escapeHtml((h.action || '').toUpperCase())}</span>
                    <span class="font-mono-num text-[11px] text-slate-500 ml-1.5">${transition}</span>
                </td>
                <td class="py-3.5 px-5 text-slate-700 font-medium whitespace-nowrap">${escapeHtml(h.user?.name || 'System')}</td>
                <td class="py-3.5 px-5 text-slate-600 min-w-[200px]">${escapeHtml(h.note || '-')}</td>
            </tr>`;
        }).join('');
    }

    function renderAdminBackups() {
        const tbody = document.getElementById('admin-backups-tbody');
        if (!tbody) return;

        if (BACKUP_FILES.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-slate-400">${IS_EN ? 'No database backups created yet. Click "Create Backup Now" to generate a snapshot.' : 'Belum ada file backup database. Klik "Buat Backup Sekarang" untuk membuat snapshot.'}</td></tr>`;
            return;
        }

        tbody.innerHTML = BACKUP_FILES.map((bak) => `
            <tr class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-4 font-mono-num font-semibold text-navy">${escapeHtml(bak.filename)}</td>
                <td class="py-3.5 px-4 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold bg-teal/10 text-teal border border-teal/20">${escapeHtml(bak.extension)}</span>
                </td>
                <td class="py-3.5 px-4 text-right font-mono-num text-slate-600 whitespace-nowrap">${escapeHtml(bak.size_human)}</td>
                <td class="py-3.5 px-4 font-mono-num text-slate-500 whitespace-nowrap">${escapeHtml(bak.created_wita || formatWita(bak.created_at_iso, true))}</td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                        <a href="/admin/backups/${encodeURIComponent(bak.filename)}/download" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-navy hover:text-white text-navy font-semibold transition">${IS_EN ? 'Download' : 'Unduh'}</a>
                        <button type="button" onclick="deleteDatabaseBackup('${escapeHtml(bak.filename)}')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-crimson hover:text-white text-crimson-dark font-semibold transition cursor-pointer">${T.del}</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    window.createDatabaseBackup = async function () {
        const btn = document.getElementById('admin-backup-create-btn');
        if (btn) btn.disabled = true;
        try {
            const res = await fetch('/admin/backups', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
            if (data.backup) {
                BACKUP_FILES.unshift(data.backup);
                renderAdminBackups();
            }
            window.showToast(data.message, 'success');
        } catch {
            window.showToast(T.networkError, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    window.deleteDatabaseBackup = async function (filename) {
        try {
            const res = await fetch(`/admin/backups/${encodeURIComponent(filename)}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
            BACKUP_FILES = BACKUP_FILES.filter((b) => b.filename !== filename);
            renderAdminBackups();
            window.showToast(data.message, 'success');
        } catch {
            window.showToast(T.networkError, 'error');
        }
    };

    // Chatbot knowledge base editor (Admin → Knowledge Base Chatbot). Native <dialog>; reload after save to refresh Blade tables.
    (function initChatbotKnowledgeAdmin() {
        const root = document.getElementById('admin-chatbot');
        const dialog = document.getElementById('kb-dialog');
        const form = document.getElementById('kb-form');
        if (!root || !dialog || !form) return;

        // form.elements, not form.id / form.title: those are the <form>'s own id/title attributes.
        const el = (name) => form.elements.namedItem(name);
        const q = (sel) => dialog.querySelector(sel);
        const errorsBox = q('[data-kb-errors]');
        const preview = q('[data-kb-preview]');
        const tryInput = q('[data-kb-try]');
        const tryResult = q('[data-kb-try-result]');
        const tryHint = tryResult.textContent;
        let lang = 'id';
        let snapshot = '';

        const payload = () => ({
            type: el('type').value,
            title: el('title').value.trim(),
            triggers: el('triggers').value.trim(),
            body_id: el('body_id').value.trim(),
            body_en: el('body_en').value.trim() || null,
            roles: [...form.querySelectorAll('input[name="roles"]:checked')].map((cb) => cb.value),
            priority: Number(el('priority').value || 0),
            is_active: el('is_active').checked,
        });
        const isDirty = () => JSON.stringify(payload()) !== snapshot;

        const refresh = () => {
            const body = el(lang === 'en' ? 'body_en' : 'body_id').value;
            const shown = body.trim() || (lang === 'en' ? el('body_id').value.trim() : '');
            preview.innerHTML = shown
                ? renderChatText(shown)
                : `<p class="text-slate-400 italic">${IS_EN ? 'The answer will appear here.' : 'Jawaban akan tampil di sini.'}</p>`;
            q('[data-count-for="title"]').textContent = `${el('title').value.length}/150`;
            q('[data-count-for="body"]').textContent = `${body.length}/5000`;
        };
        const setLang = (l) => {
            lang = l;
            dialog.querySelectorAll('[data-kb-lang]').forEach((b) => {
                const on = b.dataset.kbLang === l;
                b.setAttribute('aria-selected', on);
                b.className = `px-3 py-1.5 rounded-md cursor-pointer transition ${on ? 'bg-white text-navy shadow-xs' : 'text-slate-500 hover:text-navy'}`;
            });
            dialog.querySelectorAll('[data-kb-body]').forEach((t) => t.classList.toggle('hidden', t.dataset.kbBody !== l));
            refresh();
        };
        const showErrors = (messages, fields = []) => {
            form.querySelectorAll('[aria-invalid]').forEach((f) => f.removeAttribute('aria-invalid'));
            fields.forEach((name) => el(name)?.setAttribute?.('aria-invalid', 'true'));
            errorsBox.classList.toggle('hidden', !messages.length);
            errorsBox.innerHTML = messages.length
                ? `<div class="font-semibold mb-1">${IS_EN ? 'Please fix the following:' : 'Mohon perbaiki:'}</div><ul class="list-disc pl-4 space-y-0.5">${messages.map((m) => `<li>${escapeHtml(m)}</li>`).join('')}</ul>`
                : '';
            if (messages.length) errorsBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        };

        const open = (k = {}) => {
            form.reset();
            el('id').value = k.id || '';
            el('type').value = k.type || 'faq';
            el('title').value = k.title || '';
            el('triggers').value = k.triggers || '';
            el('body_id').value = k.body_id || '';
            el('body_en').value = k.body_en || '';
            el('priority').value = k.priority ?? 0;
            el('is_active').checked = k.is_active ?? true;
            form.querySelectorAll('input[name="roles"]').forEach((cb) => { cb.checked = (k.roles || []).includes(cb.value); });
            q('#kb-dialog-title').textContent = k.id ? (IS_EN ? 'Edit article' : 'Ubah artikel') : (IS_EN ? 'New article' : 'Artikel baru');
            q('[data-kb-meta]').textContent = k.id
                ? (IS_EN ? `Answered ${k.hits ?? 0}× so far` : `Sudah menjawab ${k.hits ?? 0}×`)
                : (IS_EN ? 'Not saved yet' : 'Belum disimpan');
            tryInput.value = k.id ? '' : (k.sample || '');
            tryResult.className = 'text-xs text-slate-500';
            tryResult.textContent = tryHint;
            showErrors([]);
            setLang('id');
            snapshot = JSON.stringify(payload());
            dialog.showModal();
            el(k.title ? 'triggers' : 'title').focus();
        };
        const close = () => {
            if (isDirty() && !window.confirm(IS_EN ? 'Discard unsaved changes?' : 'Buang perubahan yang belum disimpan?')) return;
            dialog.close();
        };

        dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(); }); // Esc
        dialog.addEventListener('click', (e) => { if (e.target === dialog) close(); }); // backdrop
        dialog.querySelectorAll('[data-kb-cancel]').forEach((b) => b.addEventListener('click', close));
        dialog.querySelectorAll('[data-kb-lang]').forEach((b) => b.addEventListener('click', () => setLang(b.dataset.kbLang)));
        form.addEventListener('input', refresh);
        form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
            if (e.key === 'Enter' && e.target === tryInput) { e.preventDefault(); q('[data-kb-try-run]').click(); }
        });

        q('[data-kb-try-run]').addEventListener('click', async (e) => {
            const btn = e.currentTarget; // currentTarget is null after the await
            const question = tryInput.value.trim();
            const triggers = el('triggers').value.trim();
            if (!question || !triggers) {
                tryResult.className = 'text-xs text-amber-700';
                tryResult.textContent = IS_EN ? 'Fill in the triggers and a sample question first.' : 'Isi pemicu dan contoh pertanyaan dulu.';
                return;
            }
            btn.disabled = true;
            try {
                const res = await fetch('/admin/chatbot-knowledge/match', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    body: JSON.stringify({ triggers, question, priority: Number(el('priority').value || 0), id: el('id').value || null }),
                });
                const d = await res.json();
                if (!res.ok) throw new Error(extractErrorMessage(d, T.networkError));
                const [tone, msg] = !d.matched
                    ? ['text-crimson-dark', IS_EN ? '✕ No match. Add a word from this question to the triggers.' : '✕ Tidak cocok. Tambahkan kata dari pertanyaan ini ke pemicu.']
                    : d.wins
                        ? ['text-emerald-700', IS_EN ? '✓ Match — this article will answer.' : '✓ Cocok — artikel ini yang akan menjawab.']
                        : ['text-amber-700', IS_EN ? `! Matches, but “${d.rival}” wins (more specific trigger or higher priority).` : `! Cocok, tapi kalah dari “${d.rival}” (pemicunya lebih spesifik atau prioritasnya lebih tinggi).`];
                tryResult.className = `text-xs font-semibold ${tone}`;
                tryResult.textContent = msg;
            } catch (err) {
                tryResult.className = 'text-xs text-crimson-dark';
                tryResult.textContent = err.message || T.networkError;
            } finally {
                btn.disabled = false;
            }
        });

        root.addEventListener('click', async (e) => {
            const t = e.target.closest('[data-kb-new],[data-kb-edit],[data-kb-delete],[data-kb-from-question]');
            if (!t) return;
            if (t.matches('[data-kb-new]')) return open();
            if (t.matches('[data-kb-edit]')) return open(JSON.parse(t.dataset.kbEdit));
            if (t.matches('[data-kb-from-question]')) {
                // Pre-fill from a failed question: IT only needs to write the answer and tidy the trigger.
                const q = t.dataset.kbFromQuestion.toLowerCase().replace(/[^\p{L}\p{N}&\s]/gu, ' ').replace(/\s+/g, ' ').trim();
                return open({ type: 'faq', title: t.dataset.kbFromQuestion.slice(0, 150), triggers: q, sample: t.dataset.kbFromQuestion });
            }
            if (!window.confirm(IS_EN ? 'Delete this article?' : 'Hapus artikel ini?')) return;
            try {
                const res = await fetch(`/admin/chatbot-knowledge/${encodeURIComponent(t.dataset.kbDelete)}`, {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                });
                const data = await res.json();
                if (!res.ok) return window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
                window.showToast(data.message, 'success');
                location.reload();
            } catch {
                window.showToast(T.networkError, 'error');
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = el('id').value;
            const data = payload();

            // Client-side checks first (same rules as the server) so IT sees every problem at once.
            const missing = [
                ['title', IS_EN ? 'Title is required.' : 'Judul wajib diisi.'],
                ['triggers', IS_EN ? 'Add at least one trigger rule.' : 'Isi minimal satu aturan pemicu.'],
                ['body_id', IS_EN ? 'The Indonesian answer is required.' : 'Jawaban Bahasa Indonesia wajib diisi.'],
            ].filter(([name]) => !data[name]);
            if (missing.length) {
                if (missing.some(([n]) => n === 'body_id')) setLang('id');
                return showErrors(missing.map(([, m]) => m), missing.map(([n]) => n));
            }

            const saveBtn = q('[data-kb-save]');
            const label = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = IS_EN ? 'Saving…' : 'Menyimpan…';
            try {
                const res = await fetch(id ? `/admin/chatbot-knowledge/${encodeURIComponent(id)}` : '/admin/chatbot-knowledge', {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    body: JSON.stringify(data),
                });
                const out = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const errs = out.errors || {};
                    return showErrors(Object.values(errs).flat().length ? Object.values(errs).flat() : [extractErrorMessage(out, T.saveFailed)],
                        Object.keys(errs).map((k) => k.split('.')[0]));
                }
                snapshot = JSON.stringify(payload());
                dialog.close();
                window.showToast(out.message, 'success');
                location.reload();
            } catch {
                showErrors([T.networkError]);
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = label;
            }
        });
    })();

    // Chatbot AI tab controls: kill switch, AI connection test, "test a question" (dry run as any user).
    (function initChatbotControls() {
        const root = document.getElementById('admin-chatbot');
        if (!root) return;
        const post = (url, body) => fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
            body: JSON.stringify(body),
        }).then(async (res) => ({ ok: res.ok, data: await res.json().catch(() => ({})) }));

        const toggle = root.querySelector('[data-chatbot-toggle]');
        toggle?.addEventListener('change', async () => {
            const enabled = toggle.checked;
            if (!enabled && !window.confirm(IS_EN
                ? 'Switch the chatbot off for ALL users? They will see a maintenance notice until you switch it back on.'
                : 'Nonaktifkan chatbot untuk SEMUA user? Mereka akan melihat pesan perawatan sampai Anda mengaktifkannya lagi.')) {
                toggle.checked = true;
                return;
            }
            toggle.disabled = true;
            try {
                const { ok, data } = await post('/admin/chatbot/toggle', { enabled });
                if (!ok) throw new Error(extractErrorMessage(data, T.saveFailed));
                window.showToast(data.message, 'success');
                location.reload();
            } catch (err) {
                toggle.checked = !enabled;
                toggle.disabled = false;
                window.showToast(err.message || T.networkError, 'error');
            }
        });

        const checkBtn = root.querySelector('[data-ai-check]');
        checkBtn?.addEventListener('click', async () => {
            const label = checkBtn.textContent;
            checkBtn.disabled = true;
            checkBtn.textContent = IS_EN ? 'Testing…' : 'Mengetes…';
            try {
                const { ok, data } = await post('/admin/chatbot/check-ai', {});
                if (!ok) throw new Error(extractErrorMessage(data, T.networkError));
                const pass = data.status === 'pass';
                root.querySelector('[data-ai-dot]').className = `w-2.5 h-2.5 rounded-full ring-4 ${pass ? 'bg-emerald-500 ring-emerald-500/15' : 'bg-amber-500 ring-amber-500/15'}`;
                root.querySelector('[data-ai-current]').textContent = data.current;
                root.querySelector('[data-ai-advice]').textContent = data.advice;
                root.querySelector('[data-ai-time]').textContent = `${IS_EN ? 'Last checked' : 'Terakhir dicek'} ${data.checked_at}.`;
                window.showToast(data.current, pass ? 'success' : 'error');
            } catch (err) {
                window.showToast(err.message || T.networkError, 'error');
            } finally {
                checkBtn.disabled = false;
                checkBtn.textContent = label;
            }
        });

        const testForm = root.querySelector('[data-chat-test]');
        const out = root.querySelector('[data-chat-test-result]');
        // Friendly names for the route a question took (meta.source).
        // Unknown sources (e.g. POMEC/topology variants) fall back to the raw name.
        const ROUTES = IS_EN
            ? { unmatched: 'Not recognised', greeting: 'Greeting / identity', small_talk: 'Small talk', out_of_scope: 'Off-topic (declined)', 'Security Guardrail': 'Blocked by security', 'RBAC Guard': 'Outside access scope', Maintenance: 'Chatbot switched off', Glossary: 'Knowledge base — glossary', FAQ: 'Knowledge base — FAQ', 'Application Guide': 'Knowledge base — guide', departments: 'Department list', data_availability: 'Available dates', department_reports: 'Report list', report_stats: 'Report statistics', report_detail: 'One report in detail', report_explain: 'Report analysis (AI explains)', report_compare: 'Report KPI comparison', report_row_search: 'Report table row search', cross_analysis: 'Cross-module analysis (POMEC + reports)', pomec_compare: 'POMEC comparison' }
            : { unmatched: 'Belum dikenali', greeting: 'Sapaan / identitas', small_talk: 'Obrolan ringan', out_of_scope: 'Di luar topik (ditolak)', 'Security Guardrail': 'Diblokir keamanan', 'RBAC Guard': 'Di luar hak akses', Maintenance: 'Chatbot dinonaktifkan', Glossary: 'Knowledge base — glosarium', FAQ: 'Knowledge base — FAQ', 'Application Guide': 'Knowledge base — panduan', departments: 'Daftar departemen', data_availability: 'Tanggal data tersedia', department_reports: 'Daftar laporan', report_stats: 'Statistik laporan', report_detail: 'Detail satu laporan', report_explain: 'Analisis laporan (dijelaskan AI)', report_compare: 'Perbandingan KPI laporan', report_row_search: 'Pencarian baris tabel laporan', cross_analysis: 'Korelasi lintas modul (POMEC + laporan)', pomec_compare: 'Perbandingan POMEC' };
        const fact = (k, v) => `<div class="rounded-xl border border-slate-200/80 bg-alabaster px-3 py-2 min-w-0"><div class="text-[10px] uppercase tracking-wider text-slate-400">${k}</div><div class="text-xs font-semibold text-navy truncate" title="${escapeHtml(v)}">${escapeHtml(v)}</div></div>`;

        testForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = testForm.querySelector('button[type="submit"]');
            const question = testForm.elements.namedItem('question').value.trim();
            if (!question) return;
            btn.disabled = true;
            out.classList.remove('hidden');
            out.innerHTML = `<div class="text-xs text-slate-400 animate-pulse">${IS_EN ? 'Running…' : 'Menjalankan…'}</div>`;
            try {
                const { ok, data } = await post('/admin/chatbot/test', { question, user_id: testForm.elements.namedItem('user_id').value || null });
                if (!ok) throw new Error(extractErrorMessage(data, T.networkError));
                const src = data.meta?.source || '-';
                const d = data.debug || {};
                const unmatched = src === 'unmatched';
                const kb = ['Glossary', 'FAQ', 'Application Guide'].includes(src);
                out.innerHTML = `
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-3">
                        ${fact(IS_EN ? 'Route' : 'Jalur', ROUTES[src] || src)}
                        ${fact(kb ? (IS_EN ? 'Article' : 'Artikel') : 'Filter', data.meta?.filters || '—')}
                        ${fact('Intent AI', d.intent || '—')}
                        ${fact(IS_EN ? 'AI calls' : 'Panggilan AI', d.fallback ? `${d.llm_calls} (${d.llm_error_type || (IS_EN ? 'failed → fallback' : 'gagal → cadangan')})` : String(d.llm_calls ?? 0))}
                        ${fact(IS_EN ? 'Time' : 'Waktu', `${((d.latency_ms || 0) / 1000).toFixed(1)} s`)}
                    </div>
                    ${data.meta?.llm_issue ? `<div class="mb-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">${escapeHtml(data.meta.llm_issue)}</div>` : ''}
                    <div class="rounded-2xl border ${unmatched ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200/80 bg-slate-50/60'} px-4 py-3 text-sm text-slate-700 leading-relaxed space-y-2">${renderChatText(data.reply || '')}</div>
                    ${data.cards?.length ? `<p class="mt-2 text-[11px] text-slate-400">+ ${data.cards.length} ${IS_EN ? 'data card(s) shown under the answer' : 'kartu data ditampilkan di bawah jawaban'}</p>` : ''}
                    ${unmatched ? `<div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-amber-800">${IS_EN ? 'The assistant does not know this yet.' : 'Asisten belum mengenali pertanyaan ini.'}
                        <button type="button" data-kb-from-question="${escapeHtml(question)}" class="px-2.5 py-1 rounded-lg bg-teal/10 text-teal font-semibold hover:bg-teal hover:text-white transition cursor-pointer">${IS_EN ? 'Make article' : 'Jadikan artikel'}</button></div>` : ''}`;
            } catch (err) {
                out.innerHTML = `<div class="text-xs rounded-xl bg-rose-50 border border-rose-200 text-crimson-dark px-3 py-2">${escapeHtml(err.message || T.networkError)}</div>`;
            } finally {
                btn.disabled = false;
            }
        });
    })();

    bindSortableHeaders('reports-table', tableStates.reports, renderReportsTable);
    bindSortableHeaders('reports-history-table', tableStates.histories, renderReportHistories);
    bindSortableHeaders('admin-users-table', tableStates.users, renderAdminUsers);
    bindSortableHeaders('admin-audit-table', tableStates.audit, renderAdminAuditLogs);

    renderReportsTable();
    renderReportHistories();
    renderPomec();
    renderPomecImports();
    renderAdminUsers();
    renderAdminAuditLogs();
    renderAuditWorkflowLogs();
    renderAdminBackups();

    window.addEventListener('sbwm-theme-change', () => {
        applyChartThemeDefaults();
        try {
            renderGmCharts();
        } catch (err) {
            console.error('Failed to refresh GM charts on theme switch:', err);
        }
        try {
            renderReportsTable();
        } catch (err) {
            console.error('Failed to refresh Reports charts on theme switch:', err);
        }
        try {
            renderPomec();
        } catch (err) {
            console.error('Failed to refresh POMEC charts on theme switch:', err);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWorkspace);
} else {
    initWorkspace();
}
