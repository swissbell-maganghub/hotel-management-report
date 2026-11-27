import Chart from 'chart.js/auto';

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
        dupFileTitle: 'Duplikasi File Terdeteksi (SHA-256)',
        dupFileDesc: 'File ini identik dengan batch',
        uniqueHashTitle: 'SHA-256 Unik',
        valid: 'valid',
        errorDup: 'error/duplikat',
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
        dupFileTitle: 'Duplicate File Detected (SHA-256)',
        dupFileDesc: 'This file matches existing batch',
        uniqueHashTitle: 'Unique SHA-256',
        valid: 'valid',
        errorDup: 'error/duplicate',
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

    let reportFilterState = { search: '', dept: boot.activeReportDept || '', period: '', date: '', status: 'all' };
    let historyActionFilter = '';
    let adminFilterState = { search: '', role: '', dept: '', status: 'all' };
    let auditModuleFilter = '';
    let auditSearchQuery = '';
    let auditActorFilter = '';
    let auditSeverityFilter = '';
    let auditDateFrom = '';
    let auditWorkflowSearch = '';
    let auditWorkflowActionFilter = '';
    let pomecFilterType = 'all';
    let pomecMonthFilter = '';
    let pomecSearchQuery = '';
    let editorRows = [];
    let editorTitleTouched = false;
    let currentPomecPreviewToken = null;

    const tableStates = {
        reports: { page: 1, perPage: 10, sortKey: 'period_date', sortDir: 'desc', lastFilterSig: '' },
        histories: { page: 1, perPage: 10, sortKey: 'created_at', sortDir: 'desc', lastFilterSig: '' },
        pomec: { page: 1, perPage: 10, sortKey: 'reading_date', sortDir: 'desc', lastFilterSig: '' },
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

    window.switchWorkspaceTab = function (tabKey, { toggleSubmenu = false } = {}) {
        const currentVisiblePanel = document.querySelector('.workspace-panel:not(.hidden)');
        const alreadyOnTab = currentVisiblePanel?.id === `tab-panel-${tabKey}`;

        document.querySelectorAll('.workspace-panel').forEach((p) => p.classList.add('hidden'));
        document.getElementById(`tab-panel-${tabKey}`)?.classList.remove('hidden');

        // [WASTAGE-REPORT] Sembunyikan KPI Row utama saat berada di tab wastage
        const mainKpiRow = document.getElementById('workspace-kpi-row');
        if (mainKpiRow) {
            mainKpiRow.classList.toggle('hidden', tabKey === 'wastage');
        }

        document.querySelectorAll('.workspace-tab-btn').forEach((btn) => {
            const active = btn.dataset.tab === tabKey;
            btn.className = 'workspace-tab-btn w-full flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer ' +
                (active ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100/80 hover:text-navy');
        });

        const submenuEl = document.getElementById('sidebar-reports-submenu');
        const chevronEl = document.getElementById('sidebar-reports-chevron');
        const reportsNavBtn = document.querySelector('.workspace-tab-btn[data-tab="reports"]');
        if (submenuEl) {
            const isCurrentlyOpen = !submenuEl.classList.contains('hidden');
            const openSubmenu = tabKey === 'reports'
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

        if (tabKey === 'dashboard' || tabKey === 'executive' || tabKey === 'reports' || tabKey === 'pomec' || tabKey === 'wastage') {
            const shouldAnimateReveal = !revealedChartTabs.has(tabKey);
            revealedChartTabs.add(tabKey);
            window.requestAnimationFrame(() => {
                ['gm-chart-pomec-trend', 'gm-chart-report-status', 'gm-chart-hk-rooms', 'reports-chart-status', 'reports-chart-trend', 'reports-chart-dept', 'pomec-chart-trend', 'pomec-chart-split', 'wastage-chart-daily', 'wastage-chart-monthly', 'wastage-chart-yearly'].forEach((id) => {
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
                            ${canEditReport(rpt) ? `
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
        const alertsByDept = {};
        REPORTS_DATA.forEach((rpt) => {
            const code = rpt.department?.code;
            if (!code) return;
            countsByDept[code] = (countsByDept[code] || 0) + 1;
            if (['submitted', 'in_review', 'returned'].includes(rpt.status)) {
                alertsByDept[code] = (alertsByDept[code] || 0) + 1;
            }
        });

        document.querySelectorAll('.report-dept-tab-btn').forEach((btn) => {
            const code = btn.dataset.deptTab || '';
            const active = code === activeDept;
            const padCls = code === '' ? 'px-3 gap-2' : 'px-2.5 gap-1.5';
            btn.className = `report-dept-tab-btn inline-flex items-center ${padCls} py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer ` +
                (active ? 'bg-navy text-white border-navy shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-navy');

            const codeBadge = code ? btn.firstElementChild : null;
            if (codeBadge && !codeBadge.hasAttribute('data-dept-tab-count')) {
                codeBadge.className = 'font-mono-num text-[10px] font-bold px-1.5 py-0.5 rounded ' +
                    (active ? 'bg-white/15 text-white' : 'bg-white border border-slate-200 text-navy');
            }

            const countSpan = btn.querySelector('[data-dept-tab-count]');
            if (countSpan) {
                countSpan.textContent = code === '' ? String(REPORTS_DATA.length) : String(countsByDept[code] || 0);
                countSpan.className = 'font-mono-num text-[10px] px-1.5 py-0.5 rounded-full ' +
                    (active ? 'bg-white/15 text-white' : 'bg-slate-200/70 text-slate-600');
            }

            const dotSpan = btn.querySelector('[data-dept-alert-dot]');
            if (dotSpan) {
                const alertCnt = alertsByDept[code] || 0;
                dotSpan.classList.toggle('hidden', alertCnt === 0);
                dotSpan.title = `${alertCnt} ${IS_EN ? 'needs attention' : 'perlu tindakan'}`;
            }
        });

        document.querySelectorAll('.sidebar-dept-btn').forEach((btn) => {
            const code = btn.dataset.sidebarDept || '';
            const active = onReportsTab && code === activeDept;
            btn.className = 'sidebar-dept-btn w-full flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg text-[11px] transition cursor-pointer ' +
                (active ? 'bg-navy/10 text-navy font-semibold' : 'font-medium text-slate-500 hover:bg-slate-100/80 hover:text-navy');
            const cEl = btn.querySelector('[data-sidebar-dept-count]');
            if (cEl) {
                cEl.textContent = code === '' ? String(REPORTS_DATA.length) : String(countsByDept[code] || 0);
            }
        });

        const scoped = activeDept ? REPORTS_DATA.filter((r) => r.department?.code === activeDept) : REPORTS_DATA;
        const reviewedCnt = scoped.filter((r) => r.status === 'reviewed').length;
        const actionableCnt = scoped.filter((r) => ['submitted', 'in_review', 'returned'].includes(r.status)).length;
        const deptObj = activeDept ? depts.find((d) => d.code === activeDept) : (depts.length === 1 ? depts[0] : null);
        const multiDept = depts.length > 1;

        const badgeEl = document.getElementById('reports-active-dept-badge');
        const titleEl = document.getElementById('reports-active-dept-title');
        const subtitleEl = document.getElementById('reports-active-dept-subtitle');
        const statsEl = document.getElementById('reports-active-dept-stats');

        if (badgeEl) {
            badgeEl.textContent = deptObj ? deptObj.code : (IS_EN ? 'ALL DEPTS' : 'SEMUA DEPT');
        }
        if (titleEl) {
            titleEl.textContent = deptObj
                ? (IS_EN ? (deptObj.name_en || deptObj.name) : deptObj.name)
                : (IS_EN ? 'All Departmental Reports' : 'Semua Laporan Departemen');
        }
        if (subtitleEl) {
            subtitleEl.textContent = activeDept && deptObj
                ? (deptObj.description || (IS_EN ? 'Focused report workspace & approval queue for this department.' : 'Ruang laporan, analitik, dan histori khusus departemen ini.'))
                : (multiDept
                    ? (IS_EN ? 'Select a department submenu below to focus on specific operational reports and analytics.' : 'Pilih submenu departemen di bawah agar laporan, grafik, dan histori tersusun per divisi.')
                    : (IS_EN ? 'Department report authoring, workflow review, and archive.' : 'Penyusunan, pemeriksaan, dan arsip laporan departemen Anda.'));
        }
        if (statsEl) {
            const actionPart = actionableCnt > 0
                ? `<span class="text-slate-300">&bull;</span><span class="text-amber-700 font-semibold"><strong>${actionableCnt}</strong> ${IS_EN ? 'pending' : 'perlu aksi'}</span>`
                : '';
            statsEl.innerHTML = `<span class="text-slate-600"><strong class="text-navy">${scoped.length}</strong> ${IS_EN ? 'total' : 'laporan'}</span><span class="text-slate-300">&bull;</span><span class="text-emerald-700"><strong>${reviewedCnt}</strong> reviewed</span>${actionPart}`;
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

    window.selectReportDepartment = function (deptCode = '') {
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
        reportFilterState = { search: '', dept: '', period: '', date: '', status: 'all' };
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
        renderReportsTable();
        renderReportHistories();
    };

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
                                    if (c.type === 'currency' && !isNaN(val)) val = 'Rp ' + Number(val).toLocaleString('id-ID');
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
            renderReportsTable();
            refreshKpis();
            window.showToast(data.message, 'success');
            window.closeModal('modal-delete-report');
        } catch {
            window.showToast(T.networkError, 'error');
        }
    };

    // -------------------------------------------------------------------------
    // 6. POMEC Filtering, Trend Bars, Batch History, Preview & Commit
    // -------------------------------------------------------------------------
    function renderPomecRecords() {
        const tbody = document.getElementById('pomec-records-tbody');
        const chartEl = document.getElementById('pomec-trend-bars');
        if (!tbody) return;

        const q = pomecSearchQuery.trim().toLowerCase();
        const filtered = POMEC_RECORDS.filter((rec) => {
            if (pomecFilterType !== 'all' && rec.utility_type !== pomecFilterType) return false;
            const isoDate = formatWitaIsoDate(rec.reading_date);
            if (pomecMonthFilter && !isoDate.startsWith(pomecMonthFilter) && rec.import?.period_month !== pomecMonthFilter) return false;
            if (q) {
                const dateLabel = formatWitaDateLabel(rec.reading_date, IS_EN);
                const hay = `${isoDate} ${dateLabel} ${rec.utility_type || ''} ${rec.meter_name || ''} ${rec.notes || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        const recCountEl = document.getElementById('pomec-record-count');
        if (recCountEl) recCountEl.textContent = `${filtered.length}/${POMEC_RECORDS.length}`;
        const hasPomecFilter = !!(q || pomecMonthFilter || pomecFilterType !== 'all');
        document.getElementById('pomec-reset-btn')?.classList.toggle('hidden', !hasPomecFilter);

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-slate-400">${IS_EN ? 'No utility records match your filter.' : 'Tidak ada data utilitas yang cocok.'}</td></tr>`;
            renderPaginationFooter('pomec-pagination', tableStates.pomec, 0, 1, 0, 0, renderPomecRecords);
        } else {
            const pageItems = paginateAndSort(
                filtered,
                tableStates.pomec,
                `${pomecFilterType}|${pomecMonthFilter}|${q}`,
                {
                    reading_date: (rec) => formatWitaIsoDate(rec.reading_date) || '',
                    utility_type: (rec) => rec.utility_type || '',
                    meter_name: (rec) => rec.meter_name || '',
                    total_usage: (rec) => Number(rec.total_usage || 0),
                },
                'pomec-pagination',
                renderPomecRecords
            );

            tbody.innerHTML = pageItems.map((rec) => {
                const dateStr = formatWitaDateLabel(rec.reading_date, IS_EN);
                const isElec = rec.utility_type === 'electricity';
                const detailVal = isElec
                    ? `${Number(rec.lwbp_value || 0).toLocaleString('id-ID')} / ${Number(rec.wbp_value || 0).toLocaleString('id-ID')}`
                    : `${Number(rec.water_m3 || 0).toLocaleString('id-ID')} m³`;
                const totalVal = isElec
                    ? `${Number(rec.total_usage || 0).toLocaleString('id-ID')} kWh`
                    : `${Number(rec.total_usage || 0).toLocaleString('id-ID')} m³`;

                return `<tr class="hover:bg-slate-50/70 transition">
                    <td class="py-3.5 px-5 font-mono-num text-slate-600 whitespace-nowrap">${escapeHtml(dateStr)}</td>
                    <td class="py-3.5 px-5 whitespace-nowrap">
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${isElec ? 'bg-amber-50 text-amber-700 border border-amber-200/70' : 'bg-cyan-50 text-teal border border-cyan-200/70'}">
                            ${escapeHtml(rec.utility_type.toUpperCase())}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 font-semibold text-navy whitespace-nowrap">${escapeHtml(rec.meter_name)}</td>
                    <td class="py-3.5 px-5 text-right font-mono-num text-slate-600 whitespace-nowrap">${detailVal}</td>
                    <td class="py-3.5 px-5 text-right font-mono-num font-bold text-navy whitespace-nowrap">${totalVal}</td>
                    <td class="py-3.5 px-5 text-slate-500 min-w-[160px]">${escapeHtml(rec.notes || '-')}</td>
                </tr>`;
            }).join('');
        }

        renderPomecCharts(filtered);
    }

    function renderPomecCharts(filtered) {
        const trendCanvas = document.getElementById('pomec-chart-trend');
        const splitCanvas = document.getElementById('pomec-chart-split');
        if (!trendCanvas && !splitCanvas) return;

        // Group filtered records by WITA ISO date ascending
        const byDate = new Map();
        filtered.forEach((r) => {
            const iso = formatWitaIsoDate(r.reading_date);
            if (!iso) return;
            if (!byDate.has(iso)) byDate.set(iso, { lwbp: 0, wbp: 0, water: 0 });
            const bucket = byDate.get(iso);
            if (r.utility_type === 'electricity') {
                bucket.lwbp += Number(r.lwbp_value || 0);
                bucket.wbp += Number(r.wbp_value || 0);
            } else {
                bucket.water += Number(r.water_m3 || r.total_usage || 0);
            }
        });

        const sortedDays = Array.from(byDate.keys()).sort().slice(-14);
        const rangeEl = document.getElementById('pomec-chart-range');
        if (rangeEl) {
            rangeEl.textContent = sortedDays.length > 0
                ? `${formatWitaDateLabel(sortedDays[0], IS_EN)} – ${formatWitaDateLabel(sortedDays[sortedDays.length - 1], IS_EN)}`
                : '-';
        }

        if (trendCanvas) {
            upsertChart(trendCanvas, {
                type: 'bar',
                data: {
                    labels: sortedDays.map((d) => new Intl.DateTimeFormat(IS_EN ? 'en-GB' : 'id-ID', { timeZone: 'Asia/Makassar', day: '2-digit', month: 'short' }).format(new Date(`${d}T12:00:00+08:00`))),
                    datasets: [
                        {
                            label: 'LWBP (kWh)',
                            data: sortedDays.map((d) => Math.round(byDate.get(d).lwbp)),
                            backgroundColor: chartLwbpBarColor(),
                            borderRadius: { topLeft: 4, topRight: 4 },
                            stack: 'elec',
                            yAxisID: 'y',
                            barPercentage: 0.65,
                            categoryPercentage: 0.72,
                            maxBarThickness: 26,
                            order: 2,
                        },
                        {
                            label: 'WBP (kWh)',
                            data: sortedDays.map((d) => Math.round(byDate.get(d).wbp)),
                            backgroundColor: '#F59E0B',
                            borderRadius: { topLeft: 4, topRight: 4 },
                            stack: 'elec',
                            yAxisID: 'y',
                            barPercentage: 0.65,
                            categoryPercentage: 0.72,
                            maxBarThickness: 26,
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: IS_EN ? 'Water (m³)' : 'Air (m³)',
                            data: sortedDays.map((d) => Number(byDate.get(d).water.toFixed(1))),
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
                        legend: { position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' } } },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } },
                        y: {
                            stacked: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: { color: chartGridColor() },
                            border: { display: false },
                            ticks: { font: { size: 10 }, callback: (v) => `${Number(v).toLocaleString('id-ID')} kWh` },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            border: { display: false },
                            ticks: { font: { size: 10 }, callback: (v) => `${v} m³` },
                        },
                    },
                },
            });
        }

        if (splitCanvas) {
            const elecRows = filtered.filter((r) => r.utility_type === 'electricity');
            const totalLwbp = elecRows.reduce((s, r) => s + Number(r.lwbp_value || 0), 0);
            const totalWbp = elecRows.reduce((s, r) => s + Number(r.wbp_value || 0), 0);
            const sumElec = totalLwbp + totalWbp;
            const lwbpPct = sumElec > 0 ? Math.round((totalLwbp / sumElec) * 100) : 0;
            const wbpPct = sumElec > 0 ? 100 - lwbpPct : 0;

            const totEl = document.getElementById('pomec-chart-elec-total');
            if (totEl) totEl.textContent = `${Math.round(sumElec).toLocaleString('id-ID')} kWh`;
            const ctrEl = document.getElementById('pomec-chart-split-center');
            if (ctrEl) ctrEl.textContent = `${lwbpPct}%`;

            const hasElec = sumElec > 0;
            upsertChart(splitCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['LWBP (kWh)', 'WBP (kWh)'],
                    datasets: [{
                        data: hasElec ? [Math.round(totalLwbp), Math.round(totalWbp)] : [1],
                        backgroundColor: hasElec ? [chartLwbpBarColor(), '#F59E0B'] : chartEmptyTrackColor(),
                        borderWidth: 0,
                        borderRadius: hasElec ? 4 : 0,
                        spacing: hasElec ? 2 : 0,
                        hoverOffset: hasElec ? 5 : 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '76%',
                    rotation: -90,
                    animation: doughnutAnimation(),
                    plugins: { legend: { display: false }, tooltip: { enabled: hasElec } },
                },
            });

            const legEl = document.getElementById('pomec-chart-split-legend');
            if (legEl) {
                legEl.innerHTML = `
                    <div class="rounded-lg bg-slate-50 border border-slate-200/70 px-2.5 py-1.5">
                        <div class="flex items-center gap-1.5 text-slate-500"><span class="w-2 h-2 rounded-full bg-navy"></span>LWBP (${lwbpPct}%)</div>
                        <div class="font-bold text-navy mt-0.5">${Math.round(totalLwbp).toLocaleString('id-ID')} kWh</div>
                    </div>
                    <div class="rounded-lg bg-amber-50/60 border border-amber-200/70 px-2.5 py-1.5">
                        <div class="flex items-center gap-1.5 text-amber-800"><span class="w-2 h-2 rounded-full bg-amber-500"></span>WBP (${wbpPct}%)</div>
                        <div class="font-bold text-amber-900 mt-0.5">${Math.round(totalWbp).toLocaleString('id-ID')} kWh</div>
                    </div>
                `;
            }
        }
    }

    // [WASTAGE-REPORT] START: Grafik Food Waste Harian (weekend/event), Bulanan (komposisi + MoM), dan Tahunan (YTD + evaluasi)
    const wastageChartData = boot.wastageCharts || {
        daily: [],
        monthly: [],
        yearly: [],
        years: [new Date().getFullYear()],
        defaultMonth: '',
        defaultYear: new Date().getFullYear(),
        categoriesMeta: [],
    };

    const wastageDefaultYear = Number(wastageChartData.defaultYear) || new Date().getFullYear();

    const wastageChartState = {
        date: 'last30', // 'last30', 'last7', 'all', atau 'YYYY-MM-DD'
        month: 'all', // 'all' (seluruh 12 bulan di year) atau 'YYYY-MM'
        year: wastageDefaultYear,
        yearlyFilter: `ytd:${wastageDefaultYear}`, // 'ytd:YYYY', 'all', atau 'cat:YYYY'
    };

    function getWastageMonthLabels(short = true) {
        if (IS_EN) {
            return short
                ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        }
        return short
            ? ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
            : ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    function getWastageDayShort(dayOfWeek) {
        const names = IS_EN
            ? ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
            : ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        return names[Number(dayOfWeek) || 0] || '';
    }

    function formatWastageDate(isoDate, withYear = true) {
        const opts = { timeZone: 'Asia/Makassar', day: '2-digit', month: 'short' };
        if (withYear) opts.year = 'numeric';
        return new Intl.DateTimeFormat(IS_EN ? 'en-GB' : 'id-ID', opts).format(new Date(`${isoDate}T12:00:00+08:00`));
    }

    function formatWastageKg(val) {
        return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatWastageInt(val) {
        return Number(val || 0).toLocaleString('id-ID');
    }

    function formatWastageRatio(val) {
        return Number(val || 0).toFixed(4);
    }

    function getFilteredWastageDates() {
        const allDaily = wastageChartData.daily || [];
        if (wastageChartState.month !== 'all') {
            return allDaily.filter((d) => d.month === wastageChartState.month);
        }
        const inYear = allDaily.filter((d) => d.month.startsWith(`${wastageChartState.year}-`));
        return inYear.length > 0 ? inYear : allDaily;
    }

    function populateWastageMonthFilter() {
        const monthSelect = document.getElementById('wastage-chart-month-filter');
        if (!monthSelect) return;

        const years = Array.isArray(wastageChartData.years) && wastageChartData.years.length > 0
            ? wastageChartData.years
            : [wastageChartState.year];
        const monthlyMap = new Map((wastageChartData.monthly || []).map((m) => [m.month, m]));
        const fullNames = getWastageMonthLabels(false);

        const buildYearOptions = (container, yr) => {
            const allOpt = document.createElement('option');
            allOpt.value = `all:${yr}`;
            allOpt.textContent = IS_EN ? `All Months (${yr})` : `Semua Bulan (${yr})`;
            container.appendChild(allOpt);

            for (let m = 1; m <= 12; m++) {
                const key = `${yr}-${String(m).padStart(2, '0')}`;
                const rec = monthlyMap.get(key);
                const opt = document.createElement('option');
                opt.value = key;
                const daysSuffix = rec && rec.days > 0
                    ? ` • (${rec.days} ${IS_EN ? 'days' : 'hari'})`
                    : '';
                opt.textContent = `${fullNames[m - 1]} ${yr}${daysSuffix}`;
                container.appendChild(opt);
            }
        };

        monthSelect.innerHTML = '';
        if (years.length === 1) {
            buildYearOptions(monthSelect, years[0]);
        } else {
            years.forEach((yr) => {
                const group = document.createElement('optgroup');
                group.label = IS_EN ? `Year ${yr}` : `Tahun ${yr}`;
                buildYearOptions(group, yr);
                monthSelect.appendChild(group);
            });
        }

        monthSelect.value = wastageChartState.month === 'all'
            ? `all:${wastageChartState.year}`
            : wastageChartState.month;
    }

    function populateWastageDateFilter() {
        const dateSelect = document.getElementById('wastage-chart-date-filter');
        if (!dateSelect) return;

        const rows = getFilteredWastageDates();
        dateSelect.innerHTML = '';

        const presets = [
            {
                value: 'last30',
                label: IS_EN
                    ? `Last 30 Days (${Math.min(30, rows.length)} days)`
                    : `30 Hari Terakhir (${Math.min(30, rows.length)} hari)`,
            },
            {
                value: 'last7',
                label: IS_EN
                    ? `Last 7 Days (${Math.min(7, rows.length)} days)`
                    : `7 Hari Terakhir (${Math.min(7, rows.length)} hari)`,
            },
            {
                value: 'all',
                label: IS_EN
                    ? `All Dates (${rows.length} days)`
                    : `Semua Tanggal (${rows.length} hari)`,
            },
        ];

        presets.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.value;
            opt.textContent = p.label;
            dateSelect.appendChild(opt);
        });

        if (rows.length > 0) {
            const dateGroup = document.createElement('optgroup');
            dateGroup.label = IS_EN ? 'Specific Date Details' : 'Rincian Tanggal Tertentu';
            rows.forEach((row) => {
                const opt = document.createElement('option');
                opt.value = row.date;
                const dayShort = getWastageDayShort(row.day_of_week);
                const dayBadge = dayShort ? ` (${dayShort})` : '';
                const eventFlag = row.is_high_cover ? ' ★' : '';
                opt.textContent = `${formatWastageDate(row.date, true)}${dayBadge}${eventFlag} • ${formatWastageKg(row.total_kg)} KG`;
                dateGroup.appendChild(opt);
            });
            dateSelect.appendChild(dateGroup);
        }

        const isPreset = wastageChartState.date === 'last30' || wastageChartState.date === 'last7' || wastageChartState.date === 'all';
        const hasSelectedDate = rows.some((r) => r.date === wastageChartState.date);
        if (!isPreset && !hasSelectedDate) {
            wastageChartState.date = 'last30';
        }
        dateSelect.value = wastageChartState.date;
    }

    function populateWastageYearlyFilter() {
        const yearlySelect = document.getElementById('wastage-chart-yearly-filter');
        if (!yearlySelect) return;

        const years = Array.isArray(wastageChartData.years) && wastageChartData.years.length > 0
            ? wastageChartData.years
            : [wastageChartState.year];

        yearlySelect.innerHTML = '';

        years.forEach((yr) => {
            const opt = document.createElement('option');
            opt.value = `ytd:${yr}`;
            opt.textContent = IS_EN ? `YTD Summary ${yr}` : `Ringkasan YTD ${yr}`;
            yearlySelect.appendChild(opt);
        });

        const allOpt = document.createElement('option');
        allOpt.value = 'all';
        allOpt.textContent = IS_EN
            ? `Multi-Year Comparison (${years.length} yrs)`
            : `Perbandingan Multi-Tahun (${years.length} thn)`;
        yearlySelect.appendChild(allOpt);

        years.forEach((yr) => {
            const opt = document.createElement('option');
            opt.value = `cat:${yr}`;
            opt.textContent = IS_EN ? `Category Composition ${yr}` : `Komposisi Kategori ${yr}`;
            yearlySelect.appendChild(opt);
        });

        const validValues = new Set(Array.from(yearlySelect.options).map((o) => o.value));
        if (!validValues.has(wastageChartState.yearlyFilter)) {
            wastageChartState.yearlyFilter = `ytd:${years[0]}`;
        }
        yearlySelect.value = wastageChartState.yearlyFilter;
    }

    function buildWastageRatioDataset(data, yAxisID = 'y1', label = 'KG / Cover') {
        const lineColor = isDarkTheme() ? '#48B8C7' : '#1E6B7B';
        return {
            type: 'line',
            label,
            data,
            spanGaps: true,
            borderColor: lineColor,
            backgroundColor: makeChartAreaGradient(
                isDarkTheme() ? 'rgba(72, 184, 199, 0.22)' : 'rgba(30, 107, 123, 0.16)',
                'rgba(30, 107, 123, 0.0)'
            ),
            fill: true,
            borderWidth: 2.25,
            pointRadius: 3.5,
            pointBackgroundColor: lineColor,
            pointBorderColor: chartPointBorderColor(),
            pointBorderWidth: 1.5,
            pointHoverRadius: 5.5,
            pointHoverBorderWidth: 2,
            tension: 0.35,
            yAxisID,
            order: 0,
        };
    }

    function buildWastageComboOptions(rowsForTooltip, mode = 'daily', opts = {}) {
        const stacked = !!opts.stacked;
        const yearlyYtd = !!opts.yearlyYtd;
        const yearlyClickable = !!opts.yearlyClickable;
        const y1Unit = opts.y1Unit || 'ratio';
        const hasY2 = !!opts.hasY2;
        const fullNames = getWastageMonthLabels(false);

        const scales = {
            x: { stacked, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } },
            y: {
                stacked,
                position: 'left',
                beginAtZero: true,
                grid: { color: chartGridColor() },
                border: { display: false },
                ticks: { font: { size: 10 }, callback: (v) => `${Number(v).toLocaleString('id-ID')} KG` },
            },
            y1: {
                position: 'right',
                beginAtZero: true,
                grid: { drawOnChartArea: false },
                border: { display: false },
                ticks: {
                    font: { size: 10 },
                    callback: (v) => (y1Unit === 'covers' ? Number(v).toLocaleString('id-ID') : Number(v).toFixed(3)),
                },
            },
        };

        if (hasY2) {
            scales.y2 = {
                position: 'right',
                beginAtZero: true,
                grid: { drawOnChartArea: false },
                border: { display: false },
                ticks: { font: { size: 10 }, callback: (v) => Number(v).toFixed(3) },
            };
        }

        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: chartStagger(18, 200),
            interaction: { mode: 'index', intersect: false },
            onHover(event, elements) {
                const target = event?.native?.target;
                if (!target) return;
                const canClick = mode === 'daily' || mode === 'monthly' || (mode === 'yearly' && yearlyClickable);
                target.style.cursor = canClick && elements.length ? 'pointer' : 'default';
            },
            onClick(_event, elements) {
                if (!elements.length) return;
                const idx = elements[0].index;
                if (mode === 'monthly') {
                    const targetMonth = `${wastageChartState.year}-${String(idx + 1).padStart(2, '0')}`;
                    wastageChartState.month = targetMonth;
                    wastageChartState.date = 'last30';
                    const monthSelect = document.getElementById('wastage-chart-month-filter');
                    if (monthSelect) monthSelect.value = targetMonth;
                    populateWastageDateFilter();
                    renderWastageCharts();
                } else if (mode === 'daily') {
                    const clickedRow = rowsForTooltip[idx];
                    if (!clickedRow) return;
                    wastageChartState.date = clickedRow.date;
                    const dateSelect = document.getElementById('wastage-chart-date-filter');
                    if (dateSelect) dateSelect.value = clickedRow.date;
                    renderWastageCharts();
                } else if (mode === 'yearly' && yearlyClickable) {
                    const clickedYear = rowsForTooltip[idx];
                    if (!clickedYear || !clickedYear.year) return;
                    wastageChartState.yearlyFilter = `ytd:${clickedYear.year}`;
                    const yearlySelect = document.getElementById('wastage-chart-yearly-filter');
                    if (yearlySelect) yearlySelect.value = wastageChartState.yearlyFilter;
                    renderWastageCharts();
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' } },
                },
                tooltip: {
                    callbacks: {
                        title(items) {
                            if (!items.length) return '';
                            const row = rowsForTooltip[items[0].dataIndex];
                            if (mode === 'daily' && row) {
                                const dayShort = getWastageDayShort(row.day_of_week);
                                return `${formatWastageDate(row.date, true)}${dayShort ? ` (${dayShort})` : ''}`;
                            }
                            if (mode === 'monthly' && row?.month) {
                                const parts = row.month.split('-');
                                const mIdx = Math.max(0, Number(parts[1]) - 1);
                                return `${fullNames[mIdx] || parts[1]} ${parts[0]}`;
                            }
                            return items[0].label;
                        },
                        label(ctx) {
                            const val = ctx.parsed.y;
                            if (val === null || val === undefined) return `${ctx.dataset.label}: -`;
                            if (ctx.dataset.yAxisID === 'y2' || (ctx.dataset.yAxisID === 'y1' && y1Unit !== 'covers')) {
                                return `${ctx.dataset.label}: ${formatWastageRatio(val)} KG/Cov`;
                            }
                            if (ctx.dataset.yAxisID === 'y1' && y1Unit === 'covers') {
                                return `${ctx.dataset.label}: ${formatWastageInt(val)} covers`;
                            }
                            return `${ctx.dataset.label}: ${formatWastageKg(val)} KG`;
                        },
                        afterBody(items) {
                            if (!items.length) return [];
                            const row = rowsForTooltip[items[0].dataIndex];
                            if (!row) return [];

                            const lines = [];
                            if (mode === 'daily') {
                                lines.push(`Cover: ${formatWastageInt(row.covers)}`);
                                if (row.covers > 0) {
                                    lines.push(`${(Number(row.kg_per_cover || 0) * 1000).toFixed(1)} g / ${IS_EN ? 'cover' : 'tamu'}`);
                                }
                                if (row.is_weekend) {
                                    lines.push(IS_EN ? 'Marker: Weekend (Sat/Sun)' : 'Penanda: Akhir Pekan (Sabtu/Minggu)');
                                }
                                if (row.is_high_cover) {
                                    lines.push(IS_EN ? 'Marker: High Cover / Event Day' : 'Penanda: Cover Tinggi / Event');
                                }
                                if (row.notes) {
                                    lines.push(`${IS_EN ? 'Note' : 'Catatan'}: ${row.notes}`);
                                }
                            } else if (mode === 'monthly') {
                                lines.push(`TOTAL KG: ${formatWastageKg(row.total_kg)} KG`);
                                lines.push(`Cover: ${formatWastageInt(row.covers)}`);
                                lines.push(IS_EN ? `Days recorded: ${row.days || 0} days` : `Jumlah hari: ${row.days || 0} hari`);
                                if (row.mom_ratio_pct !== null && row.mom_ratio_pct !== undefined) {
                                    const signR = row.mom_ratio_pct > 0 ? '+' : '';
                                    const signK = row.mom_kg_pct > 0 ? '+' : '';
                                    lines.push(
                                        IS_EN
                                            ? `MoM vs prev active month: KG ${signK}${row.mom_kg_pct.toFixed(1)}% • KG/Cov ${signR}${row.mom_ratio_pct.toFixed(1)}%`
                                            : `MoM vs bulan aktif sblm: KG ${signK}${row.mom_kg_pct.toFixed(1)}% • KG/Cov ${signR}${row.mom_ratio_pct.toFixed(1)}%`
                                    );
                                }
                            } else if (mode === 'yearly') {
                                if (yearlyYtd) {
                                    lines.push(IS_EN ? `Month Waste: ${formatWastageKg(row.total_kg)} KG` : `Waste Bulan Ini: ${formatWastageKg(row.total_kg)} KG`);
                                    lines.push(IS_EN ? `Month Cover: ${formatWastageInt(row.covers)}` : `Cover Bulan Ini: ${formatWastageInt(row.covers)}`);
                                    lines.push(IS_EN ? `Days recorded: ${row.days || 0} days` : `Jumlah hari: ${row.days || 0} hari`);
                                } else {
                                    lines.push(IS_EN ? `Active months: ${row.months || 0}/12` : `Bulan aktif: ${row.months || 0}/12`);
                                    lines.push(IS_EN ? `Days recorded: ${row.days || 0} days` : `Jumlah hari: ${row.days || 0} hari`);
                                    lines.push(
                                        IS_EN
                                            ? `Next Year Target (-10%): ${formatWastageRatio(row.target_next_year_kg_per_cover)} KG/Cov`
                                            : `Target Efisiensi (-10%): ${formatWastageRatio(row.target_next_year_kg_per_cover)} KG/Cov`
                                    );
                                }
                            }
                            return lines;
                        },
                    },
                },
            },
            scales,
        };
    }

    function renderWastageCategoryBreakdownChart(canvas, categoriesMap, barColor) {
        const meta = wastageChartData.categoriesMeta || [];
        const labels = meta.map((m) => m.label);
        const values = meta.map((m) => Number(categoriesMap?.[m.key] || 0));

        upsertChart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: IS_EN ? 'Waste by Category (KG)' : 'Sampah per Kategori (KG)',
                        data: values,
                        backgroundColor: barColor,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        barPercentage: 0.62,
                        categoryPercentage: 0.78,
                        maxBarThickness: 32,
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
                    tooltip: {
                        callbacks: {
                            title(items) {
                                if (!items.length) return '';
                                const m = meta[items[0].dataIndex];
                                return m ? `${m.category} • ${m.label}` : items[0].label;
                            },
                            label(ctx) {
                                return `${formatWastageKg(ctx.parsed.y)} KG`;
                            },
                        },
                    },
                },
                scales: {
                    x: { stacked: false, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 30, minRotation: 0 } },
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        grid: { color: chartGridColor() },
                        border: { display: false },
                        ticks: { font: { size: 10 }, callback: (v) => `${Number(v).toLocaleString('id-ID')} KG` },
                    },
                },
            },
        });
    }

    function renderWastageDailyLegend(showLegend) {
        const legendEl = document.getElementById('wastage-chart-daily-legend');
        if (!legendEl) return;
        if (!showLegend) {
            legendEl.classList.add('hidden');
            legendEl.innerHTML = '';
            return;
        }
        const ratioColor = isDarkTheme() ? '#48B8C7' : '#1E6B7B';
        const items = [
            { color: '#F59E0B', label: IS_EN ? 'Weekday Normal' : 'Hari Kerja Normal' },
            { color: '#F04E30', label: IS_EN ? 'Weekend (Sat/Sun)' : 'Akhir Pekan (Sabtu/Minggu)' },
            { color: '#8B5CF6', label: IS_EN ? 'Event / High Cover (★)' : 'Event / Cover Tinggi (★)' },
            { color: ratioColor, label: 'KG / Cover', isLine: true },
        ];
        legendEl.classList.remove('hidden');
        legendEl.innerHTML = items.map((item) => {
            const swatch = item.isLine
                ? `<span class="inline-block w-3 h-0.5 rounded-full" style="background-color:${item.color}"></span>`
                : `<span class="inline-block w-2.5 h-2.5 rounded-sm" style="background-color:${item.color}"></span>`;
            return `<span class="inline-flex items-center gap-1.5">${swatch}<span>${escapeHtml(item.label)}</span></span>`;
        }).join('');
    }

    function renderWastageMonthlyMom(selectedMonth, fullNames) {
        const momEl = document.getElementById('wastage-chart-monthly-mom');
        if (!momEl) return;

        const activeMonths = (wastageChartData.monthly || [])
            .filter((m) => Number(m.total_kg || 0) > 0 || Number(m.days || 0) > 0)
            .sort((a, b) => a.month.localeCompare(b.month));

        if (activeMonths.length < 2) {
            momEl.classList.add('hidden');
            momEl.innerHTML = '';
            return;
        }

        let currRec = null;
        let prevRec = null;
        if (selectedMonth !== 'all') {
            const idx = activeMonths.findIndex((m) => m.month === selectedMonth);
            if (idx > 0) {
                currRec = activeMonths[idx];
                prevRec = activeMonths[idx - 1];
            }
        }
        if (!currRec || !prevRec) {
            const inYear = activeMonths.filter((m) => m.month.startsWith(`${wastageChartState.year}-`));
            const pool = inYear.length >= 2 ? inYear : activeMonths;
            currRec = pool[pool.length - 1];
            prevRec = pool[pool.length - 2];
        }

        const formatMonthShort = (ym) => {
            const [y, m] = ym.split('-');
            return `${fullNames[Math.max(0, Number(m) - 1)] || m} ${y}`;
        };

        const kgDiffPct = Number(prevRec.total_kg || 0) > 0
            ? ((Number(currRec.total_kg || 0) - Number(prevRec.total_kg || 0)) / Number(prevRec.total_kg)) * 100
            : 0;
        const ratioDiffPct = Number(prevRec.kg_per_cover || 0) > 0
            ? ((Number(currRec.kg_per_cover || 0) - Number(prevRec.kg_per_cover || 0)) / Number(prevRec.kg_per_cover)) * 100
            : 0;

        const improved = ratioDiffPct < -0.5;
        const worsened = ratioDiffPct > 0.5;
        const badgeCls = improved
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : (worsened ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-600 border-slate-200');
        const badgeLabel = improved
            ? (IS_EN ? 'Waste Efficiency Improved' : 'Efisiensi Membaik')
            : (worsened ? (IS_EN ? 'Ratio Increased' : 'Rasio Meningkat') : (IS_EN ? 'Stable' : 'Stabil'));

        const signKg = kgDiffPct > 0 ? '+' : '';
        const signRatio = ratioDiffPct > 0 ? '+' : '';

        momEl.classList.remove('hidden');
        momEl.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-slate-600">
                    <span class="font-semibold text-navy">${escapeHtml(formatMonthShort(prevRec.month))}</span>
                    <span class="mx-1 text-slate-400">vs</span>
                    <span class="font-semibold text-navy">${escapeHtml(formatMonthShort(currRec.month))}</span>
                    <span class="mx-1.5 text-slate-300">•</span>
                    <span class="font-mono-num">KG: ${signKg}${kgDiffPct.toFixed(1)}%</span>
                    <span class="mx-1 text-slate-300">|</span>
                    <span class="font-mono-num">KG/Cov: ${signRatio}${ratioDiffPct.toFixed(1)}%</span>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-semibold ${badgeCls}">
                    ${escapeHtml(badgeLabel)}
                </span>
            </div>
        `;
    }

    function renderWastageYearlyEval(yrRec) {
        const evalEl = document.getElementById('wastage-chart-yearly-eval');
        if (!evalEl) return;
        if (!yrRec) {
            evalEl.classList.add('hidden');
            evalEl.innerHTML = '';
            return;
        }

        const nextYear = Number(yrRec.year) + 1;
        const avgGrams = (Number(yrRec.avg_kg_per_cover || 0) * 1000).toFixed(1);
        const targetGrams = (Number(yrRec.target_next_year_kg_per_cover || 0) * 1000).toFixed(1);

        evalEl.classList.remove('hidden');
        evalEl.innerHTML = `
            <div class="rounded-lg border border-slate-200/80 bg-white px-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">
                    ${IS_EN ? `Cumulative YTD (${escapeHtml(String(yrRec.year))})` : `Kumulatif YTD (${escapeHtml(String(yrRec.year))})`}
                </div>
                <div class="mt-0.5 text-sm font-bold font-mono-num text-navy">
                    ${formatWastageKg(yrRec.ytd_kg)} KG <span class="text-slate-400 font-normal">/</span> ${formatWastageInt(yrRec.ytd_cover)} cover
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    ${yrRec.months || 0}/12 ${IS_EN ? 'active months' : 'bulan aktif'} • ${yrRec.days || 0} ${IS_EN ? 'days' : 'hari'}
                </div>
            </div>
            <div class="rounded-lg border border-slate-200/80 bg-white px-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">
                    ${IS_EN ? 'Annual Avg KG / Cover' : 'Rata-rata Tahunan KG / Cover'}
                </div>
                <div class="mt-0.5 text-sm font-bold font-mono-num text-teal">
                    ${formatWastageRatio(yrRec.avg_kg_per_cover)} KG/Cov
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    ≈ ${avgGrams} g / ${IS_EN ? 'cover' : 'tamu'}
                </div>
            </div>
            <div class="rounded-lg border border-slate-200/80 bg-white px-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">
                    ${IS_EN ? `${nextYear} Efficiency Target (-10%)` : `Target Efisiensi ${nextYear} (-10%)`}
                </div>
                <div class="mt-0.5 text-sm font-bold font-mono-num text-navy">
                    ≤ ${formatWastageRatio(yrRec.target_next_year_kg_per_cover)} KG/Cov
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    ≈ ${targetGrams} g / ${IS_EN ? 'cover' : 'tamu'} (${IS_EN ? 'mgmt benchmark' : 'acuan evaluasi'})
                </div>
            </div>
        `;
    }

    function renderWastageCharts() {
        const dailyCanvas = document.getElementById('wastage-chart-daily');
        const monthlyCanvas = document.getElementById('wastage-chart-monthly');
        const yearlyCanvas = document.getElementById('wastage-chart-yearly');
        if (!dailyCanvas && !monthlyCanvas && !yearlyCanvas) return;

        const fullNames = getWastageMonthLabels(false);
        const shortNames = getWastageMonthLabels(true);

        // 1. Grafik Mode Harian
        if (dailyCanvas) {
            const allFilteredRows = getFilteredWastageDates();
            const dailyMeta = document.getElementById('wastage-chart-daily-meta');
            const dailyEmpty = document.getElementById('wastage-chart-daily-empty');
            const isPreset = wastageChartState.date === 'last30' || wastageChartState.date === 'last7' || wastageChartState.date === 'all';

            if (!isPreset) {
                const selectedRow = allFilteredRows.find((r) => r.date === wastageChartState.date);
                renderWastageDailyLegend(false);
                if (!selectedRow) {
                    Chart.getChart(dailyCanvas)?.destroy();
                    if (dailyMeta) dailyMeta.textContent = '0 KG';
                    if (dailyEmpty) {
                        dailyEmpty.textContent = IS_EN ? 'No food waste data for the selected date.' : 'Tidak ada data food waste untuk tanggal ini.';
                        dailyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (dailyEmpty) dailyEmpty.classList.add('hidden');
                    if (dailyMeta) {
                        const dayShort = getWastageDayShort(selectedRow.day_of_week);
                        const dayBadge = dayShort ? ` (${dayShort})` : '';
                        dailyMeta.textContent = `${formatWastageDate(selectedRow.date, true)}${dayBadge} • ${formatWastageKg(selectedRow.total_kg)} KG • ${formatWastageInt(selectedRow.covers)} cover • ${formatWastageRatio(selectedRow.kg_per_cover)} KG/Cov`;
                    }
                    const singleColor = selectedRow.is_weekend ? '#F04E30' : (selectedRow.is_high_cover ? '#8B5CF6' : '#F59E0B');
                    renderWastageCategoryBreakdownChart(dailyCanvas, selectedRow.categories, singleColor);
                }
            } else {
                let dailyRows = allFilteredRows;
                if (wastageChartState.date === 'last30') {
                    dailyRows = allFilteredRows.slice(-30);
                } else if (wastageChartState.date === 'last7') {
                    dailyRows = allFilteredRows.slice(-7);
                }

                if (dailyRows.length === 0) {
                    Chart.getChart(dailyCanvas)?.destroy();
                    renderWastageDailyLegend(false);
                    if (dailyMeta) dailyMeta.textContent = IS_EN ? '0 days' : '0 hari';
                    if (dailyEmpty) {
                        dailyEmpty.textContent = IS_EN
                            ? 'No daily food waste records available.'
                            : 'Belum ada data harian food waste untuk ditampilkan.';
                        dailyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (dailyEmpty) dailyEmpty.classList.add('hidden');
                    renderWastageDailyLegend(true);
                    const sumKg = dailyRows.reduce((s, r) => s + Number(r.total_kg || 0), 0);
                    const sumCovers = dailyRows.reduce((s, r) => s + Number(r.covers || 0), 0);
                    const weekendCount = dailyRows.filter((r) => r.is_weekend).length;
                    const eventCount = dailyRows.filter((r) => r.is_high_cover).length;
                    if (dailyMeta) {
                        dailyMeta.textContent = `${dailyRows.length} ${IS_EN ? 'days' : 'hari'} • ${formatWastageKg(sumKg)} KG • ${formatWastageInt(sumCovers)} cover • ${weekendCount} weekend • ${eventCount} ${IS_EN ? 'high-cover/event' : 'event/cover tinggi'}`;
                    }

                    const dailyBarColors = dailyRows.map((r) => (r.is_weekend ? '#F04E30' : (r.is_high_cover ? '#8B5CF6' : '#F59E0B')));
                    const dailyLabels = dailyRows.map((d) => {
                        const base = formatWastageDate(d.date, false);
                        const dayBadge = d.is_weekend ? ` (${getWastageDayShort(d.day_of_week)})` : '';
                        const star = d.is_high_cover ? ' ★' : '';
                        return `${base}${dayBadge}${star}`;
                    });

                    upsertChart(dailyCanvas, {
                        type: 'bar',
                        data: {
                            labels: dailyLabels,
                            datasets: [
                                {
                                    label: 'TOTAL KG/Day',
                                    data: dailyRows.map((r) => Number(r.total_kg || 0)),
                                    backgroundColor: dailyBarColors,
                                    borderRadius: { topLeft: 4, topRight: 4 },
                                    yAxisID: 'y',
                                    barPercentage: 0.62,
                                    categoryPercentage: 0.74,
                                    maxBarThickness: 28,
                                    order: 2,
                                },
                                buildWastageRatioDataset(dailyRows.map((r) => (r.covers > 0 ? Number(r.kg_per_cover || 0) : null)), 'y1', 'KG / Cover'),
                            ],
                        },
                        options: buildWastageComboOptions(dailyRows, 'daily', { stacked: false, y1Unit: 'ratio' }),
                    });
                }
            }
        }

        // 2. Grafik Mode Bulanan (Stacked Bar 4 Kelompok Kategori + Rata-rata KG/COVER + MoM)
        if (monthlyCanvas) {
            const year = Number(wastageChartState.year);
            const monthlyMap = new Map((wastageChartData.monthly || []).map((m) => [m.month, m]));
            const monthlyMeta = document.getElementById('wastage-chart-monthly-meta');
            const monthlyEmpty = document.getElementById('wastage-chart-monthly-empty');
            const monthlyBlue = isDarkTheme() ? '#38BDF8' : '#2563EB';

            renderWastageMonthlyMom(wastageChartState.month, fullNames);

            if (wastageChartState.month !== 'all') {
                const rec = monthlyMap.get(wastageChartState.month);
                const mParts = wastageChartState.month.split('-');
                const mLabel = mParts.length === 2
                    ? `${fullNames[Math.max(0, Number(mParts[1]) - 1)]} ${mParts[0]}`
                    : wastageChartState.month;

                if (!rec || (rec.days === 0 && Number(rec.total_kg || 0) === 0)) {
                    Chart.getChart(monthlyCanvas)?.destroy();
                    if (monthlyMeta) monthlyMeta.textContent = `${mLabel} • 0 ${IS_EN ? 'days' : 'hari'}`;
                    if (monthlyEmpty) {
                        monthlyEmpty.textContent = IS_EN
                            ? `No food waste data recorded for ${mLabel}.`
                            : `Belum ada data food waste untuk ${mLabel}.`;
                        monthlyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (monthlyEmpty) monthlyEmpty.classList.add('hidden');
                    if (monthlyMeta) {
                        monthlyMeta.textContent = `${mLabel} • ${formatWastageKg(rec.total_kg)} KG • ${formatWastageInt(rec.covers)} cover • ${formatWastageRatio(rec.kg_per_cover)} KG/Cov`;
                    }
                    renderWastageCategoryBreakdownChart(monthlyCanvas, rec.categories, monthlyBlue);
                }
            } else {
                if (monthlyEmpty) monthlyEmpty.classList.add('hidden');
                const slots = [];
                let lastActiveSlot = null;
                for (let m = 1; m <= 12; m++) {
                    const key = `${year}-${String(m).padStart(2, '0')}`;
                    const existing = monthlyMap.get(key);
                    const slot = existing
                        ? { ...existing, groups: existing.groups || { buffet: 0, preparation: 0, plate_waste: 0, breakfast_cafe: 0 } }
                        : { month: key, total_kg: 0, covers: 0, days: 0, kg_per_cover: 0, groups: { buffet: 0, preparation: 0, plate_waste: 0, breakfast_cafe: 0 } };

                    const isActive = Number(slot.total_kg || 0) > 0 || Number(slot.days || 0) > 0;
                    if (isActive && lastActiveSlot) {
                        slot.mom_kg_pct = Number(lastActiveSlot.total_kg || 0) > 0
                            ? ((Number(slot.total_kg || 0) - Number(lastActiveSlot.total_kg || 0)) / Number(lastActiveSlot.total_kg)) * 100
                            : null;
                        slot.mom_ratio_pct = Number(lastActiveSlot.kg_per_cover || 0) > 0
                            ? ((Number(slot.kg_per_cover || 0) - Number(lastActiveSlot.kg_per_cover || 0)) / Number(lastActiveSlot.kg_per_cover)) * 100
                            : null;
                    } else {
                        slot.mom_kg_pct = null;
                        slot.mom_ratio_pct = null;
                    }
                    if (isActive) lastActiveSlot = slot;
                    slots.push(slot);
                }

                const totalYrKg = slots.reduce((s, r) => s + Number(r.total_kg || 0), 0);
                const totalYrCovers = slots.reduce((s, r) => s + Number(r.covers || 0), 0);
                const filledMonths = slots.filter((r) => r.days > 0 || r.total_kg > 0).length;
                if (monthlyMeta) {
                    monthlyMeta.textContent = `${IS_EN ? 'Year' : 'Tahun'} ${year} • ${formatWastageKg(totalYrKg)} KG • ${formatWastageInt(totalYrCovers)} cover • ${filledMonths}/12 ${IS_EN ? 'months' : 'bulan'}`;
                }

                const dark = isDarkTheme();
                upsertChart(monthlyCanvas, {
                    type: 'bar',
                    data: {
                        labels: shortNames,
                        datasets: [
                            {
                                label: 'Buffet',
                                data: slots.map((r) => Number(r.groups?.buffet || 0)),
                                backgroundColor: dark ? '#3B82F6' : '#2563EB',
                                stack: 'monthly-waste',
                                yAxisID: 'y',
                                barPercentage: 0.65,
                                categoryPercentage: 0.74,
                                maxBarThickness: 26,
                                order: 4,
                            },
                            {
                                label: 'Preparation',
                                data: slots.map((r) => Number(r.groups?.preparation || 0)),
                                backgroundColor: dark ? '#2DD4BF' : '#0D9488',
                                stack: 'monthly-waste',
                                yAxisID: 'y',
                                barPercentage: 0.65,
                                categoryPercentage: 0.74,
                                maxBarThickness: 26,
                                order: 3,
                            },
                            {
                                label: 'Plate Waste',
                                data: slots.map((r) => Number(r.groups?.plate_waste || 0)),
                                backgroundColor: dark ? '#FBBF24' : '#F59E0B',
                                stack: 'monthly-waste',
                                yAxisID: 'y',
                                barPercentage: 0.65,
                                categoryPercentage: 0.74,
                                maxBarThickness: 26,
                                order: 2,
                            },
                            {
                                label: 'Breakfast Swiss',
                                data: slots.map((r) => Number(r.groups?.breakfast_cafe || 0)),
                                backgroundColor: dark ? '#A78BFA' : '#8B5CF6',
                                borderRadius: { topLeft: 4, topRight: 4 },
                                stack: 'monthly-waste',
                                yAxisID: 'y',
                                barPercentage: 0.65,
                                categoryPercentage: 0.74,
                                maxBarThickness: 26,
                                order: 1,
                            },
                            buildWastageRatioDataset(
                                slots.map((r) => (r.covers > 0 ? Number(r.kg_per_cover || 0) : null)),
                                'y1',
                                IS_EN ? 'Monthly Avg KG/Cover' : 'Rata-rata KG/Cover'
                            ),
                        ],
                    },
                    options: buildWastageComboOptions(slots, 'monthly', { stacked: true, y1Unit: 'ratio' }),
                });
            }
        }

        // 3. Grafik Mode Tahunan (Tren KG YTD, COVER YTD, & Rata-rata Tahunan KG/COVER)
        if (yearlyCanvas) {
            const yearlyMeta = document.getElementById('wastage-chart-yearly-meta');
            const yearlyEmpty = document.getElementById('wastage-chart-yearly-empty');
            const yearlyList = Array.isArray(wastageChartData.yearly) ? wastageChartData.yearly : [];
            const yearlyMap = new Map(yearlyList.map((y) => [String(y.year), y]));
            const monthlyMap = new Map((wastageChartData.monthly || []).map((m) => [m.month, m]));
            const modeVal = wastageChartState.yearlyFilter || `ytd:${wastageDefaultYear}`;
            const dark = isDarkTheme();

            if (modeVal === 'all') {
                const activeYears = yearlyList.filter((y) => Number(y.ytd_kg || 0) > 0 || Number(y.days || 0) > 0);
                if (activeYears.length === 0) {
                    Chart.getChart(yearlyCanvas)?.destroy();
                    renderWastageYearlyEval(null);
                    if (yearlyMeta) yearlyMeta.textContent = IS_EN ? 'No annual data' : 'Belum ada data tahunan';
                    if (yearlyEmpty) {
                        yearlyEmpty.textContent = IS_EN ? 'No annual food waste records available.' : 'Belum ada data tahunan food waste untuk ditampilkan.';
                        yearlyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (yearlyEmpty) yearlyEmpty.classList.add('hidden');
                    const totalAllKg = activeYears.reduce((s, y) => s + Number(y.ytd_kg || 0), 0);
                    const totalAllCov = activeYears.reduce((s, y) => s + Number(y.ytd_cover || 0), 0);
                    if (yearlyMeta) {
                        yearlyMeta.textContent = `${activeYears.length} ${IS_EN ? 'years' : 'tahun'} • ${formatWastageKg(totalAllKg)} KG • ${formatWastageInt(totalAllCov)} cover`;
                    }
                    const latestYrRec = yearlyMap.get(String(wastageChartState.year)) || activeYears[activeYears.length - 1];
                    renderWastageYearlyEval(latestYrRec);

                    const barEmerald = dark ? '#34D399' : '#10B981';
                    const lineCoverBlue = dark ? '#60A5FA' : '#2563EB';
                    const lineAvgAmber = dark ? '#FBBF24' : '#D97706';

                    upsertChart(yearlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: activeYears.map((y) => `${IS_EN ? 'Year' : 'Tahun'} ${y.year}`),
                            datasets: [
                                {
                                    label: 'KG YTD',
                                    data: activeYears.map((y) => Number(y.ytd_kg || 0)),
                                    backgroundColor: barEmerald,
                                    borderRadius: { topLeft: 4, topRight: 4 },
                                    yAxisID: 'y',
                                    barPercentage: 0.45,
                                    maxBarThickness: 44,
                                    order: 3,
                                },
                                {
                                    type: 'line',
                                    label: 'COVER YTD',
                                    data: activeYears.map((y) => Number(y.ytd_cover || 0)),
                                    borderColor: lineCoverBlue,
                                    backgroundColor: 'transparent',
                                    borderWidth: 2.25,
                                    pointRadius: 4,
                                    pointBackgroundColor: lineCoverBlue,
                                    pointBorderColor: chartPointBorderColor(),
                                    pointBorderWidth: 1.5,
                                    tension: 0.3,
                                    yAxisID: 'y1',
                                    order: 1,
                                },
                                {
                                    type: 'line',
                                    label: IS_EN ? 'Annual Avg KG/COVER' : 'Rata-rata Tahunan KG/COVER',
                                    data: activeYears.map((y) => (y.ytd_cover > 0 ? Number(y.avg_kg_per_cover || 0) : null)),
                                    spanGaps: true,
                                    borderColor: lineAvgAmber,
                                    backgroundColor: 'transparent',
                                    borderDash: [5, 4],
                                    borderWidth: 2,
                                    pointRadius: 3.5,
                                    pointBackgroundColor: lineAvgAmber,
                                    pointBorderColor: chartPointBorderColor(),
                                    pointBorderWidth: 1.5,
                                    tension: 0.3,
                                    yAxisID: 'y2',
                                    order: 0,
                                },
                            ],
                        },
                        options: buildWastageComboOptions(activeYears, 'yearly', {
                            stacked: false,
                            yearlyClickable: true,
                            y1Unit: 'covers',
                            hasY2: true,
                        }),
                    });
                }
            } else if (modeVal.startsWith('cat:')) {
                const yrKey = modeVal.slice(4);
                const yrRec = yearlyMap.get(yrKey);
                if (!yrRec || (Number(yrRec.ytd_kg || 0) === 0 && Number(yrRec.days || 0) === 0)) {
                    Chart.getChart(yearlyCanvas)?.destroy();
                    renderWastageYearlyEval(null);
                    if (yearlyMeta) yearlyMeta.textContent = `${IS_EN ? 'Year' : 'Tahun'} ${yrKey} • 0 KG`;
                    if (yearlyEmpty) {
                        yearlyEmpty.textContent = IS_EN ? `No food waste data recorded for ${yrKey}.` : `Belum ada data food waste untuk tahun ${yrKey}.`;
                        yearlyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (yearlyEmpty) yearlyEmpty.classList.add('hidden');
                    renderWastageYearlyEval(yrRec);
                    if (yearlyMeta) {
                        yearlyMeta.textContent = `${IS_EN ? 'Category Breakdown' : 'Komposisi Kategori'} ${yrKey} • ${formatWastageKg(yrRec.ytd_kg)} KG • ${formatWastageInt(yrRec.ytd_cover)} cover`;
                    }
                    renderWastageCategoryBreakdownChart(yearlyCanvas, yrRec.categories, dark ? '#34D399' : '#10B981');
                }
            } else {
                const yrKey = modeVal.startsWith('ytd:') ? modeVal.slice(4) : String(wastageChartState.year);
                const yrRec = yearlyMap.get(yrKey);
                const slots = [];
                let lastActiveIdx = -1;
                for (let m = 1; m <= 12; m++) {
                    const key = `${yrKey}-${String(m).padStart(2, '0')}`;
                    const rec = monthlyMap.get(key);
                    const hasData = !!(rec && (Number(rec.total_kg || 0) > 0 || Number(rec.days || 0) > 0));
                    if (hasData) lastActiveIdx = m - 1;
                    slots.push(rec || { month: key, total_kg: 0, covers: 0, days: 0, ytd_kg: 0, ytd_cover: 0, ytd_kg_per_cover: 0 });
                }

                if (lastActiveIdx === -1) {
                    Chart.getChart(yearlyCanvas)?.destroy();
                    renderWastageYearlyEval(null);
                    if (yearlyMeta) yearlyMeta.textContent = `${IS_EN ? 'YTD Summary' : 'Ringkasan YTD'} ${yrKey} • 0 KG`;
                    if (yearlyEmpty) {
                        yearlyEmpty.textContent = IS_EN ? `No YTD food waste records for ${yrKey}.` : `Belum ada data YTD food waste untuk tahun ${yrKey}.`;
                        yearlyEmpty.classList.remove('hidden');
                    }
                } else {
                    if (yearlyEmpty) yearlyEmpty.classList.add('hidden');
                    const evalRec = yrRec || {
                        year: yrKey,
                        ytd_kg: slots[lastActiveIdx].ytd_kg,
                        ytd_cover: slots[lastActiveIdx].ytd_cover,
                        months: lastActiveIdx + 1,
                        days: slots.reduce((s, r) => s + Number(r.days || 0), 0),
                        avg_kg_per_cover: slots[lastActiveIdx].ytd_kg_per_cover,
                        target_next_year_kg_per_cover: Number(slots[lastActiveIdx].ytd_kg_per_cover || 0) * 0.9,
                    };
                    renderWastageYearlyEval(evalRec);

                    if (yearlyMeta) {
                        yearlyMeta.textContent = `${IS_EN ? 'YTD Progress' : 'Progres YTD'} ${yrKey} • ${formatWastageKg(evalRec.ytd_kg)} KG YTD • ${formatWastageInt(evalRec.ytd_cover)} COVER YTD • Avg ${formatWastageRatio(evalRec.avg_kg_per_cover)} KG/Cov`;
                    }

                    const barEmerald = dark ? '#34D399' : '#10B981';
                    const lineCoverBlue = dark ? '#60A5FA' : '#2563EB';
                    const lineAvgAmber = dark ? '#FBBF24' : '#D97706';

                    upsertChart(yearlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: shortNames,
                            datasets: [
                                {
                                    label: 'KG YTD',
                                    data: slots.map((r, i) => (i <= lastActiveIdx ? Number(r.ytd_kg || 0) : null)),
                                    backgroundColor: barEmerald,
                                    borderRadius: { topLeft: 4, topRight: 4 },
                                    yAxisID: 'y',
                                    barPercentage: 0.62,
                                    categoryPercentage: 0.74,
                                    maxBarThickness: 28,
                                    order: 3,
                                },
                                {
                                    type: 'line',
                                    label: 'COVER YTD',
                                    data: slots.map((r, i) => (i <= lastActiveIdx ? Number(r.ytd_cover || 0) : null)),
                                    spanGaps: false,
                                    borderColor: lineCoverBlue,
                                    backgroundColor: 'transparent',
                                    borderWidth: 2.25,
                                    pointRadius: 3.5,
                                    pointBackgroundColor: lineCoverBlue,
                                    pointBorderColor: chartPointBorderColor(),
                                    pointBorderWidth: 1.5,
                                    tension: 0.3,
                                    yAxisID: 'y1',
                                    order: 1,
                                },
                                {
                                    type: 'line',
                                    label: IS_EN ? 'YTD Avg KG/COVER' : 'Rata-rata KG/COVER YTD',
                                    data: slots.map((r, i) => (i <= lastActiveIdx && r.ytd_cover > 0 ? Number(r.ytd_kg_per_cover || 0) : null)),
                                    spanGaps: true,
                                    borderColor: lineAvgAmber,
                                    backgroundColor: 'transparent',
                                    borderDash: [5, 4],
                                    borderWidth: 2,
                                    pointRadius: 3,
                                    pointBackgroundColor: lineAvgAmber,
                                    pointBorderColor: chartPointBorderColor(),
                                    pointBorderWidth: 1.5,
                                    tension: 0.3,
                                    yAxisID: 'y2',
                                    order: 0,
                                },
                            ],
                        },
                        options: buildWastageComboOptions(slots, 'yearly', {
                            stacked: false,
                            yearlyYtd: true,
                            y1Unit: 'covers',
                            hasY2: true,
                        }),
                    });
                }
            }
        }
    }

    function initWastageCharts() {
        const dailyCanvas = document.getElementById('wastage-chart-daily');
        const monthlyCanvas = document.getElementById('wastage-chart-monthly');
        const yearlyCanvas = document.getElementById('wastage-chart-yearly');
        if (!dailyCanvas && !monthlyCanvas && !yearlyCanvas) return;

        populateWastageMonthFilter();
        populateWastageDateFilter();
        populateWastageYearlyFilter();

        const dateSelect = document.getElementById('wastage-chart-date-filter');
        const monthSelect = document.getElementById('wastage-chart-month-filter');
        const yearlySelect = document.getElementById('wastage-chart-yearly-filter');
        const toggleBtn = document.getElementById('wastage-charts-toggle');
        const chartsGrid = document.getElementById('wastage-charts-grid');

        monthSelect?.addEventListener('change', () => {
            const val = monthSelect.value;
            if (val.startsWith('all:')) {
                wastageChartState.year = Number(val.slice(4));
                wastageChartState.month = 'all';
            } else {
                wastageChartState.month = val;
                wastageChartState.year = Number(val.slice(0, 4));
            }
            wastageChartState.date = 'last30';
            populateWastageDateFilter();
            renderWastageCharts();
        });

        dateSelect?.addEventListener('change', () => {
            wastageChartState.date = dateSelect.value;
            renderWastageCharts();
        });

        yearlySelect?.addEventListener('change', () => {
            wastageChartState.yearlyFilter = yearlySelect.value;
            renderWastageCharts();
        });

        toggleBtn?.addEventListener('click', () => {
            if (!chartsGrid) return;
            const hidden = chartsGrid.classList.toggle('hidden');
            toggleBtn.textContent = hidden
                ? (IS_EN ? 'Show Charts' : 'Tampilkan Grafik')
                : (IS_EN ? 'Hide Charts' : 'Sembunyikan Grafik');
            if (!hidden) {
                window.requestAnimationFrame(() => {
                    ['wastage-chart-daily', 'wastage-chart-monthly', 'wastage-chart-yearly'].forEach((id) => {
                        const c = document.getElementById(id);
                        const ch = c ? Chart.getChart(c) : null;
                        if (ch) {
                            ch.resize();
                            ch.update('none');
                        }
                    });
                });
            }
        });

        renderWastageCharts();
    }
    // [WASTAGE-REPORT] END

    function renderPomecImports() {
        const tbody = document.getElementById('pomec-imports-tbody');
        const countEl = document.getElementById('pomec-batch-count');
        if (countEl) countEl.textContent = POMEC_IMPORTS.length;
        if (!tbody) return;

        const q = pomecSearchQuery.trim().toLowerCase();
        const filtered = POMEC_IMPORTS.filter((imp) => {
            if (pomecFilterType !== 'all' && imp.utility_type !== pomecFilterType) return false;
            if (pomecMonthFilter && imp.period_month !== pomecMonthFilter) return false;
            if (q && !`${imp.batch_code || ''} ${imp.original_filename || ''} ${imp.uploader?.name || ''} ${imp.period_month || ''}`.toLowerCase().includes(q)) return false;
            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-slate-400">${IS_EN ? 'No POMEC import batches match your filter.' : 'Tidak ada riwayat batch impor POMEC yang sesuai.'}</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map((imp) => {
            const isElec = imp.utility_type === 'electricity';
            const unit = isElec ? 'kWh' : 'm³';
            return `<tr class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-5 font-mono-num font-semibold text-navy whitespace-nowrap">${escapeHtml(imp.batch_code)}</td>
                <td class="py-3.5 px-5 whitespace-nowrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${isElec ? 'bg-amber-50 text-amber-700' : 'bg-cyan-50 text-teal'}">${escapeHtml(imp.utility_type.toUpperCase())}</span>
                    <span class="font-mono-num text-slate-500 ml-1.5">${escapeHtml(imp.period_month)}</span>
                </td>
                <td class="py-3.5 px-5 text-slate-700 min-w-[200px]">${escapeHtml(imp.original_filename)}</td>
                <td class="py-3.5 px-5 font-mono-num text-[11px] text-slate-500 whitespace-nowrap">${escapeHtml((imp.file_hash_sha256 || '').slice(0, 24))}...</td>
                <td class="py-3.5 px-5 text-right font-mono-num font-semibold text-navy whitespace-nowrap">${imp.total_rows} ${T.rows} &bull; ${Number(imp.total_usage || 0).toLocaleString('id-ID')} ${unit}</td>
                <td class="py-3.5 px-5 text-slate-600 whitespace-nowrap">
                    <div>${escapeHtml(imp.uploader?.name || '-')}</div>
                    <div class="text-[10px] font-mono-num text-slate-400 mt-0.5">${escapeHtml(formatWita(imp.committed_at, true))}</div>
                </td>
            </tr>`;
        }).join('');
    }

    document.getElementById('pomec-file-input')?.addEventListener('change', (e) => {
        const label = document.getElementById('pomec-file-label');
        const file = e.target.files?.[0];
        if (label) {
            label.textContent = file
                ? `${file.name} (${Math.max(1, Math.round(file.size / 1024))} KB)`
                : (IS_EN ? 'Choose .xlsx or .csv file' : 'Pilih file .xlsx atau .csv');
        }
    });

    document.getElementById('pomec-search')?.addEventListener('input', (e) => {
        pomecSearchQuery = e.target.value;
        renderPomecRecords();
        renderPomecImports();
    });

    document.getElementById('pomec-month-filter')?.addEventListener('change', (e) => {
        pomecMonthFilter = e.target.value;
        renderPomecRecords();
        renderPomecImports();
    });

    document.querySelectorAll('.pomec-filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            pomecFilterType = btn.dataset.type;
            document.querySelectorAll('.pomec-filter-btn').forEach((b) => {
                const active = b.dataset.type === pomecFilterType;
                b.className = 'pomec-filter-btn px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer ' +
                    (active ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy');
            });
            renderPomecRecords();
            renderPomecImports();
        });
    });

    window.resetPomecFilters = function () {
        pomecFilterType = 'all';
        pomecMonthFilter = '';
        pomecSearchQuery = '';
        const s = document.getElementById('pomec-search');
        const m = document.getElementById('pomec-month-filter');
        if (s) s.value = '';
        if (m) m.value = '';
        document.querySelectorAll('.pomec-filter-btn').forEach((b) => {
            const active = b.dataset.type === 'all';
            b.className = 'pomec-filter-btn px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer ' +
                (active ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy');
        });
        renderPomecRecords();
        renderPomecImports();
    };

    document.getElementById('pomec-preview-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const btn = document.getElementById('pomec-preview-btn');
        btn.disabled = true;

        try {
            const res = await fetch('/pomec/preview', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) {
                window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
                return;
            }

            currentPomecPreviewToken = data.preview_token;
            const p = data.preview;
            document.getElementById('pomec-preview-box').classList.remove('hidden');

            const banner = document.getElementById('pomec-hash-banner');
            if (p.is_duplicate_file) {
                banner.className = 'p-3 rounded-xl border bg-rose-50 border-rose-200 text-crimson-dark text-xs space-y-1';
                banner.innerHTML = `<div class="font-bold">${T.dupFileTitle}</div>
                    <div>${T.dupFileDesc} <strong>${escapeHtml(p.duplicate_batch)}</strong>.</div>`;
            } else {
                banner.className = 'p-3 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-xs space-y-1';
                banner.innerHTML = `<div class="font-bold">${T.uniqueHashTitle} &bull; ${p.valid_count} ${T.valid}, ${p.error_count} ${T.errorDup}</div>
                    <div class="font-mono-num text-[11px]">Hash: ${escapeHtml(p.file_hash.slice(0, 32))}...</div>`;
            }

            document.getElementById('pomec-preview-rows').innerHTML = (p.rows || []).map((r) => `
                <tr class="hover:bg-slate-50/70">
                    <td class="py-3 px-4 font-mono-num whitespace-nowrap">${escapeHtml(formatWitaDateLabel(r.reading_date, IS_EN))}</td>
                    <td class="py-3 px-4">
                        <div class="font-medium text-navy">${escapeHtml(r.meter_name)}</div>
                        ${r.error_message ? `<div class="text-[11px] text-crimson-dark mt-0.5">${escapeHtml(r.error_message)}</div>` : ''}
                    </td>
                    <td class="py-3 px-4 text-right font-mono-num whitespace-nowrap">${Number(r.total_usage || 0).toLocaleString('id-ID')}</td>
                    <td class="py-3 px-4 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ${r.status === 'valid' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-crimson-dark'}">
                            ${escapeHtml(r.status.toUpperCase())}
                        </span>
                    </td>
                </tr>
            `).join('');

            document.getElementById('pomec-commit-btn').disabled = p.is_duplicate_file || p.valid_count === 0;
        } catch {
            window.showToast(T.networkError, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    function refreshPomecSummaryCards() {
        const elecRecs = POMEC_RECORDS.filter((r) => r.utility_type === 'electricity');
        const waterRecs = POMEC_RECORDS.filter((r) => r.utility_type === 'water');
        const elecTotal = elecRecs.reduce((s, r) => s + Number(r.total_usage || 0), 0);
        const lwbpTotal = elecRecs.reduce((s, r) => s + Number(r.lwbp_value || 0), 0);
        const wbpTotal = elecRecs.reduce((s, r) => s + Number(r.wbp_value || 0), 0);
        const waterTotal = waterRecs.reduce((s, r) => s + Number(r.water_m3 || 0), 0);
        const lwbpPct = elecTotal > 0 ? Math.round((lwbpTotal / elecTotal) * 100) : 0;
        const wbpPct = elecTotal > 0 ? 100 - lwbpPct : 0;

        const elElec = document.getElementById('pomec-stat-elec-total');
        const elLwbpBar = document.getElementById('pomec-stat-elec-lwbp-bar');
        const elWbpBar = document.getElementById('pomec-stat-elec-wbp-bar');
        const elSplit = document.getElementById('pomec-stat-elec-split');
        const elWater = document.getElementById('pomec-stat-water-total');
        const elWaterCnt = document.getElementById('pomec-stat-water-count');

        if (elElec) elElec.textContent = Math.round(elecTotal).toLocaleString('id-ID');
        if (elLwbpBar) elLwbpBar.style.width = `${lwbpPct}%`;
        if (elWbpBar) elWbpBar.style.width = `${wbpPct}%`;
        if (elSplit) {
            elSplit.innerHTML = `<span>LWBP: ${Math.round(lwbpTotal).toLocaleString('id-ID')} (${lwbpPct}%)</span>` +
                `<span>WBP: ${Math.round(wbpTotal).toLocaleString('id-ID')} (${wbpPct}%)</span>`;
        }
        if (elWater) elWater.textContent = waterTotal.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        if (elWaterCnt) elWaterCnt.textContent = `${waterRecs.length} ${IS_EN ? 'records' : 'catatan'}`;
    }

    document.getElementById('pomec-commit-btn')?.addEventListener('click', async () => {
        if (!currentPomecPreviewToken) return;
        const btn = document.getElementById('pomec-commit-btn');
        btn.disabled = true;
        try {
            const res = await fetch('/pomec/commit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: JSON.stringify({ preview_token: currentPomecPreviewToken }),
            });
            const data = await res.json();
            if (!res.ok) {
                window.showToast(extractErrorMessage(data, T.saveFailed), 'error');
                btn.disabled = false;
                return;
            }
            window.showToast(data.message, 'success');
            if (data.import?.records) {
                POMEC_RECORDS = [...data.import.records, ...POMEC_RECORDS];
                renderPomecRecords();
                refreshPomecSummaryCards();
            }
            if (data.import) {
                POMEC_IMPORTS.unshift(data.import);
                renderPomecImports();
            }
            document.getElementById('pomec-preview-box').classList.add('hidden');
            document.getElementById('pomec-preview-form').reset();
            const label = document.getElementById('pomec-file-label');
            if (label) label.textContent = IS_EN ? 'Choose .xlsx or .csv file' : 'Pilih file .xlsx atau .csv';
            currentPomecPreviewToken = null;
        } catch {
            window.showToast(T.networkError, 'error');
            btn.disabled = false;
        }
    });

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
    bindSortableHeaders('pomec-records-table', tableStates.pomec, renderPomecRecords);
    bindSortableHeaders('admin-users-table', tableStates.users, renderAdminUsers);
    bindSortableHeaders('admin-audit-table', tableStates.audit, renderAdminAuditLogs);

    renderReportsTable();
    renderReportHistories();
    renderPomecRecords();
    renderPomecImports();
    initWastageCharts(); // [WASTAGE-REPORT]
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
            renderPomecRecords();
        } catch (err) {
            console.error('Failed to refresh POMEC charts on theme switch:', err);
        }
        // [WASTAGE-REPORT]
        try {
            renderWastageCharts();
        } catch (err) {
            console.error('Failed to refresh Wastage charts on theme switch:', err);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWorkspace);
} else {
    initWorkspace();
}
