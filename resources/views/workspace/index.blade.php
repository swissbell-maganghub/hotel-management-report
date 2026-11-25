@extends('layouts.base')

@php
    $isEn = $locale === 'en';
    $actionBadgeCount = $pendingReviewCount + $returnedCount;
    $totalReportsCount = max(1, $reports->count());
    $reviewedPct = (int) round(($reviewedCount / $totalReportsCount) * 100);
    $pendingPct = (int) round(($pendingReviewCount / $totalReportsCount) * 100);
    $returnedPct = (int) round(($returnedCount / $totalReportsCount) * 100);

    $allTabLabels = [
        'dashboard' => $user->isItAdmin()
            ? 'System Overview'
            : ($user->isGm() ? 'Executive Dashboard' : ($isEn ? 'Overview' : 'Dashboard')),
        'executive' => 'Executive Dashboard',
        'reports' => $isEn ? 'Department Reports' : 'Laporan Departemen',
        'pomec' => 'POMEC Utility',
        'topology' => 'System Topology',
        'admin' => 'User Management',
        'audit' => 'Audit Trail',
        'chatbot' => $isEn ? 'AI Chatbot' : 'Chatbot AI',
    ];
    $tabLabels = array_intersect_key($allTabLabels, array_flip($allowedTabs));

    $bootstrapPayload = [
        'locale' => $locale,
        'activeTab' => $activeTab,
        'activeReportDept' => $activeReportDept ?? '',
        'accessibleDepts' => ($accessibleDepts ?? $departments)->values(),
        'tabLabels' => $tabLabels,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'department_id' => $user->department_id,
            'is_read_only' => (bool) $user->is_read_only,
            'inputtableDepartmentIds' => $user->inputtableDepartmentIds(),
            'reviewableDepartmentIds' => $user->reviewableDepartmentIds(),
        ],
        'templates' => $templates,
        'reports' => $reports,
        'reportHistories' => $reportHistories ?? [],
        'pomecRecords' => $pomecRecords,
        'pomecImports' => $pomecImports,
        'gmCharts' => $gmCharts ?? null,
        'managedUsers' => $managedUsers,
        'auditLogs' => $auditLogs,
        'backupFiles' => $backupFiles ?? [],
    ];
@endphp

@section('title', $tabLabels[$activeTab] ?? 'Dashboard')

@section('body')
<div class="flex-1 flex min-h-0 relative">
    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-xs hidden lg:hidden"></div>

    <!-- Collapsible Executive Sidebar -->
    <aside id="app-sidebar"
        class="fixed lg:sticky top-0 left-0 z-40 lg:z-20 h-screen w-64 bg-white border-r border-slate-200/80 flex flex-col justify-between transition-all duration-200 -translate-x-full lg:translate-x-0 shrink-0">
        <!-- Brand & Nav -->
        <div class="flex flex-col min-h-0 flex-1">
            <!-- Brand Header -->
            <div class="sidebar-brand-row h-16 px-4 border-b border-slate-100 flex items-center justify-between gap-2 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="{{ asset('images/swissbel-icon.png') }}" alt="Swiss-Belhotel" class="w-9 h-9 rounded-xl shadow-2xs shrink-0 object-cover">
                    <div class="sidebar-text min-w-0">
                        <div class="text-xs font-heading font-extrabold text-navy leading-tight truncate">
                            Swiss-Belhotel
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">
                            Waterfront Makassar
                        </div>
                    </div>
                </div>
                <button type="button" id="sidebar-mobile-close" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-100 cursor-pointer" aria-label="Close sidebar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5">
                <div class="sidebar-text px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                    {{ $isEn ? 'Main Navigation' : 'Menu Utama' }}
                </div>

                @foreach ($allowedTabs as $tabKey)
                    @php
                        $isActive = $activeTab === $tabKey;
                    @endphp
                    <button type="button" data-tab="{{ $tabKey }}" onclick="switchWorkspaceTab('{{ $tabKey }}', { toggleSubmenu: true })"
                        title="{{ $tabLabels[$tabKey] }}"
                        @if ($tabKey === 'reports' && ($accessibleDepts ?? collect())->count() > 1)
                            aria-expanded="{{ $activeTab === 'reports' ? 'true' : 'false' }}"
                        @endif
                        class="workspace-tab-btn w-full flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100/80 hover:text-navy' }}">
                        <span class="flex items-center gap-2.5 min-w-0">
                            @if ($tabKey === 'dashboard')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            @elseif ($tabKey === 'executive')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            @elseif ($tabKey === 'reports')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @elseif ($tabKey === 'pomec')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            @elseif ($tabKey === 'topology')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                            @elseif ($tabKey === 'audit')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            @elseif ($tabKey === 'chatbot')
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            @else
                                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            @endif
                            <span class="sidebar-text truncate">{{ $tabLabels[$tabKey] }}</span>
                        </span>
                        @if ($tabKey === 'reports')
                            <span class="sidebar-text inline-flex items-center gap-1.5 shrink-0">
                                <span id="reports-tab-badge" class="font-mono-num px-1.5 py-0.5 rounded-full text-[10px] bg-crimson text-white leading-none {{ $actionBadgeCount > 0 ? '' : 'hidden' }}">
                                    {{ $actionBadgeCount }}
                                </span>
                                @if (($accessibleDepts ?? collect())->count() > 1)
                                    <svg id="sidebar-reports-chevron" class="w-3.5 h-3.5 opacity-70 transition-transform duration-150 {{ $activeTab === 'reports' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </span>
                        @endif
                    </button>

                    @if ($tabKey === 'reports' && ($accessibleDepts ?? collect())->count() > 1)
                        @php $currentSidebarDept = $activeReportDept ?? ''; @endphp
                        <div id="sidebar-reports-submenu" class="sidebar-text ml-3.5 pl-2.5 border-l border-slate-200/80 space-y-0.5 py-0.5 {{ $activeTab === 'reports' ? '' : 'hidden' }}">
                            <button type="button" data-sidebar-dept="" onclick="selectReportDepartment('')"
                                class="sidebar-dept-btn w-full flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition cursor-pointer {{ $activeTab === 'reports' && $currentSidebarDept === '' ? 'bg-navy/10 text-navy' : 'text-slate-500 hover:bg-slate-100/80 hover:text-navy' }}">
                                <span class="truncate">{{ $isEn ? 'All Departments' : 'Semua Dept' }}</span>
                                <span data-sidebar-dept-count="" class="font-mono-num text-[10px] opacity-80 shrink-0">{{ $reports->count() }}</span>
                            </button>
                            @foreach ($accessibleDepts as $subDept)
                                @php
                                    $subCount = $reports->where('department_id', $subDept->id)->count();
                                    $isSubActive = $activeTab === 'reports' && $currentSidebarDept === $subDept->code;
                                @endphp
                                <button type="button" data-sidebar-dept="{{ $subDept->code }}" onclick="selectReportDepartment('{{ $subDept->code }}')"
                                    class="sidebar-dept-btn w-full flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg text-[11px] font-medium transition cursor-pointer {{ $isSubActive ? 'bg-navy/10 text-navy font-semibold' : 'text-slate-500 hover:bg-slate-100/80 hover:text-navy' }}">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        <span class="font-mono-num text-[9px] font-bold px-1 py-0.5 rounded bg-slate-100 text-slate-600 shrink-0">{{ $subDept->code }}</span>
                                        <span class="truncate">{{ $subDept->localizedName($locale) }}</span>
                                    </span>
                                    <span data-sidebar-dept-count="{{ $subDept->code }}" class="font-mono-num text-[10px] opacity-80 shrink-0">{{ $subCount }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Sidebar Footer Scope Card -->
        <div class="p-3 border-t border-slate-100 sidebar-text">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                <div class="text-[11px] font-semibold text-navy truncate">{{ $user->roleLabel($locale) }}</div>
                <div class="text-[11px] text-slate-500 truncate">{{ $user->scopeLabel($locale) }}</div>
                @if ($user->isItAdmin())
                    <div class="flex items-center gap-1.5 text-[10px] font-mono-num text-emerald-700 pt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <span>Intranet / VPN HTTPS</span>
                    </div>
                @endif
            </div>
        </div>
    </aside>

    <!-- Right Workspace Column (Topbar + Content + Footer) -->
    <div class="flex-1 min-w-0 flex flex-col min-h-screen">
        <!-- Sticky Executive Topbar -->
        <header class="sticky top-0 z-20 h-16 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 sm:px-6 flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <!-- Mobile Drawer Trigger -->
                <button type="button" id="sidebar-mobile-open" class="lg:hidden p-2 rounded-xl border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 cursor-pointer" aria-label="Open menu">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <!-- Desktop Collapse Toggle -->
                <button type="button" id="sidebar-collapse-btn" title="{{ $isEn ? 'Toggle sidebar' : 'Ciutkan / Lebarkan sidebar' }}"
                    class="hidden lg:inline-flex p-2 rounded-xl border border-slate-200 text-slate-500 hover:text-navy hover:bg-slate-50 transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"/></svg>
                </button>

                <div class="min-w-0">
                    <div class="text-[11px] font-medium text-slate-400 truncate">
                        Hotel Management Report System
                    </div>
                    <h1 id="topbar-page-title" class="text-sm sm:text-base font-heading font-extrabold text-navy leading-tight truncate">
                        {{ $tabLabels[$activeTab] ?? 'Dashboard' }}
                    </h1>
                </div>
            </div>

            <!-- Right Controls -->
            <div class="flex items-center gap-2.5 shrink-0">
                <!-- Topbar Quick Search -->
                <div class="hidden xl:block relative w-56">
                    <input id="topbar-quick-search" type="text"
                        placeholder="{{ $isEn ? 'Quick search reports...' : 'Cari cepat laporan...' }}"
                        class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50/70 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal transition">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </div>

                <!-- Live WITA Operational Clock Pill -->
                <div class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/90 text-[11px] font-mono-num text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span id="topbar-wita-clock" data-wita-clock>{{ now()->timezone('Asia/Makassar')->format('D, d M Y • H:i:s') }} WITA</span>
                </div>

                <!-- Actionable Notifications Bell -->
                <div class="relative" id="notif-container">
                    <button type="button" id="notif-btn" title="{{ $isEn ? 'Report notifications' : 'Notifikasi laporan' }}"
                        class="relative w-9 h-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-navy flex items-center justify-center transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span id="notif-dot" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-crimson {{ $actionBadgeCount > 0 ? '' : 'hidden' }}"></span>
                    </button>

                    <div id="notif-dropdown" class="dropdown-panel hidden absolute right-0 mt-2 w-80 bg-white border border-slate-200/90 rounded-2xl shadow-[0_16px_40px_rgba(15,41,66,0.14)] overflow-hidden z-50 text-xs">
                        <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
                            <span class="font-heading font-bold text-navy">{{ $isEn ? 'Actionable Reports' : 'Laporan Perlu Tindakan' }}</span>
                            <span id="notif-count-pill" class="font-mono-num text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">{{ $actionBadgeCount }}</span>
                        </div>
                        <div id="notif-list" class="max-h-64 overflow-y-auto divide-y divide-slate-100"></div>
                    </div>
                </div>

                @if ($user->canCreateReports())
                    <button type="button" onclick="openReportEditor()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">{{ $isEn ? 'New Report' : 'Buat Laporan' }}</span>
                    </button>
                @endif

                <!-- Theme Switcher (Light / Dark) -->
                <button type="button"
                        data-theme-toggle
                        data-label-dark="{{ ($locale ?? 'id') === 'en' ? 'Switch to Dark Mode' : 'Aktifkan Mode Gelap' }}"
                        data-label-light="{{ ($locale ?? 'id') === 'en' ? 'Switch to Light Mode' : 'Aktifkan Mode Terang' }}"
                        aria-label="{{ ($locale ?? 'id') === 'en' ? 'Toggle Dark Mode' : 'Ubah Tema Tampilan' }}"
                        aria-pressed="false"
                        title="{{ ($locale ?? 'id') === 'en' ? 'Switch to Dark Mode' : 'Aktifkan Mode Gelap' }}"
                        class="sbwm-theme-toggle inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-200/70 transition cursor-pointer shrink-0">
                    <svg data-theme-icon="moon" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg data-theme-icon="sun" class="w-4 h-4 hidden text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                </button>

                <!-- Bilingual Switcher (ID | EN) -->
                <div class="inline-flex rounded-full bg-slate-100 p-0.5 border border-slate-200 text-xs font-semibold">
                    <a href="{{ route('locale.switch', 'id') }}" class="px-2.5 py-1 rounded-full transition {{ $locale === 'id' ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy' }}">ID</a>
                    <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1 rounded-full transition {{ $locale === 'en' ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy' }}">EN</a>
                </div>

                <!-- User Account Dropdown -->
                <div class="relative" id="user-menu-container">
                    <button type="button" id="user-menu-btn"
                        class="inline-flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/80 transition cursor-pointer">
                        <span class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-heading font-bold text-xs flex items-center justify-center shrink-0">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </span>
                        <span class="hidden md:block text-left leading-tight">
                            <span class="block text-xs font-semibold text-navy">{{ $user->name }}</span>
                            <span class="block text-[11px] text-teal">{{ $user->roleLabel($locale) }}</span>
                        </span>
                        <svg id="user-menu-chevron" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="user-menu-dropdown" class="dropdown-panel hidden absolute right-0 mt-2 w-68 bg-white border border-slate-200/90 rounded-2xl shadow-[0_16px_40px_rgba(15,41,66,0.14)] overflow-hidden z-50 text-xs">
                        <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-100 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-heading font-bold text-navy truncate">{{ $user->name }}</span>
                                <span class="font-mono-num text-[10px] font-semibold px-2 py-0.5 rounded-full bg-teal/10 text-teal border border-teal/20 shrink-0">
                                    {{ strtoupper($user->role) }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate">{{ $user->scopeLabel($locale) }}</div>
                            @if ($user->isItAdmin())
                                <div class="inline-flex items-center gap-1.5 text-[11px] font-mono-num text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>LAN / VPN HTTPS</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-1.5 space-y-0.5">
                            <button type="button" onclick="closeUserMenu(); openModal('modal-change-password')"
                                class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100/80 text-slate-700 font-medium flex items-center gap-2.5 transition cursor-pointer">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                <span>{{ $isEn ? 'Change Password' : 'Ubah Kata Sandi' }}</span>
                            </button>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 rounded-xl hover:bg-rose-50 text-crimson-dark font-semibold flex items-center gap-2.5 transition cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    <span>{{ $isEn ? 'Sign Out' : 'Keluar Sistem' }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Executive Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6">
            <!-- KPI Row (2 cols mobile, 4 cols desktop) -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-2">
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Total Reports' : 'Total Laporan' }}</div>
                    <div class="flex items-baseline justify-between">
                        <div class="text-2xl font-heading font-extrabold font-mono-num text-navy" id="kpi-total">{{ $reports->count() }}</div>
                        <span class="text-[11px] font-mono-num text-emerald-700 font-semibold" id="kpi-reviewed-pct">{{ $reviewedPct }}% Reviewed</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div id="kpi-reviewed-bar" class="h-full bg-emerald-600 rounded-full" style="width: {{ $reviewedPct }}%"></div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-2">
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Awaiting HOD Review' : 'Menunggu Review HOD' }}</div>
                    <div class="flex items-baseline justify-between">
                        <div class="text-2xl font-heading font-extrabold font-mono-num text-amber-600" id="kpi-pending">{{ $pendingReviewCount }}</div>
                        <span class="text-[11px] font-mono-num text-amber-700 font-semibold">Submitted</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full bg-amber-500 rounded-full" style="width: {{ $pendingPct }}%"></div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-2">
                    <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Needs Revision' : 'Perlu Revisi' }}</div>
                    <div class="flex items-baseline justify-between">
                        <div class="text-2xl font-heading font-extrabold font-mono-num text-crimson" id="kpi-returned">{{ $returnedCount }}</div>
                        <span class="text-[11px] font-mono-num text-crimson-dark font-semibold">Returned</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full bg-crimson rounded-full" style="width: {{ $returnedPct }}%"></div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] space-y-2">
                    @if ($canPomec)
                        <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'POMEC Electricity (MTD)' : 'POMEC Listrik (MTD)' }}</div>
                        <div class="flex items-baseline justify-between gap-1">
                            <div class="text-xl sm:text-2xl font-heading font-extrabold font-mono-num text-teal">{{ number_format($electricityTotal, 0, ',', '.') }}</div>
                            <span class="text-[11px] font-mono-num text-slate-500">kWh</span>
                        </div>
                        <div class="text-[11px] font-mono-num text-slate-500">
                            {{ $isEn ? 'Water' : 'Air' }}: {{ number_format($waterTotal, 1, ',', '.') }} m³
                        </div>
                    @else
                        <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Reviewed Reports' : 'Laporan Disetujui HOD' }}</div>
                        <div class="flex items-baseline justify-between">
                            <div class="text-2xl font-heading font-extrabold font-mono-num text-emerald-700">{{ $reviewedCount }}</div>
                            <span class="text-[11px] font-mono-num text-emerald-700 font-semibold">Reviewed</span>
                        </div>
                        <div class="text-[11px] text-slate-500">{{ $user->scopeLabel($locale) }}</div>
                    @endif
                </div>
            </section>

            <!-- Tab Panels -->
            <section id="tab-panel-dashboard" class="workspace-panel {{ $activeTab === 'dashboard' ? '' : 'hidden' }}">
                @include('workspace.partials.tab-dashboard')
            </section>

            @if ($user->isItAdmin())
                <section id="tab-panel-executive" class="workspace-panel {{ $activeTab === 'executive' ? '' : 'hidden' }}">
                    @include('workspace.partials.tab-executive')
                </section>
            @endif

            <section id="tab-panel-reports" class="workspace-panel {{ $activeTab === 'reports' ? '' : 'hidden' }}">
                @include('workspace.partials.tab-reports')
            </section>

            @if ($canPomec)
                <section id="tab-panel-pomec" class="workspace-panel {{ $activeTab === 'pomec' ? '' : 'hidden' }}">
                    @include('workspace.partials.tab-pomec')
                </section>
            @endif

            @if ($user->canViewTopology())
                <section id="tab-panel-topology" class="workspace-panel {{ $activeTab === 'topology' ? '' : 'hidden' }}">
                    @include('workspace.partials.tab-topology')
                </section>
            @endif

            @if ($user->canManageUsers())
                <section id="tab-panel-admin" class="workspace-panel {{ $activeTab === 'admin' ? '' : 'hidden' }}">
                    @include('workspace.partials.tab-admin')
                </section>
                <section id="tab-panel-audit" class="workspace-panel {{ $activeTab === 'audit' ? '' : 'hidden' }}">
                    @include('workspace.partials.tab-audit')
                </section>
            @endif

            @if (! empty($chatbotAdmin))
                <section id="tab-panel-chatbot" class="workspace-panel {{ $activeTab === 'chatbot' ? '' : 'hidden' }}">
                    @include('workspace.partials.admin-chatbot', ['kb' => $chatbotAdmin['knowledge'], 'ins' => $chatbotAdmin['insights']])
                </section>
            @endif
        </main>

        <!-- Executive Footer -->
        <footer class="mt-auto bg-white border-t border-slate-200/80 px-4 sm:px-6 py-4 pb-20 sm:pb-4 lg:pr-64">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-semibold text-navy">Swiss-Belhotel Waterfront Makassar</span>
                    <span>&bull;</span>
                    <span>Hotel Management Report System</span>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px]">
                    @if ($user->isItAdmin())
                        <span class="inline-flex items-center gap-1.5 font-mono-num text-emerald-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>LAN / VPN HTTPS</span>
                        </span>
                        <span class="text-slate-300">|</span>
                    @endif
                    <span class="font-mono-num">WITA (UTC+8)</span>
                    <span class="text-slate-300">|</span>
                    <span>IT Department &copy; {{ date('Y') }}</span>
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- Modals & Floating AI Assistant -->
@include('workspace.partials.modals')
@include('workspace.partials.chat-widget')

<script id="workspace-bootstrap" type="application/json">@json($bootstrapPayload)</script>
@endsection
