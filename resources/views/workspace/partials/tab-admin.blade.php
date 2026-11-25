@php $isEn = $locale === 'en'; @endphp

<div class="space-y-5">
    <!-- Top User Summary Strip (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
            <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Total Accounts' : 'Total Akun' }}</div>
            <div class="text-2xl font-heading font-extrabold font-mono-num text-navy mt-1" id="admin-stat-total">{{ $managedUsers->count() }}</div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
            <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Active Users' : 'Akun Aktif' }}</div>
            <div class="text-2xl font-heading font-extrabold font-mono-num text-emerald-700 mt-1" id="admin-stat-active">{{ $managedUsers->where('is_active', true)->count() }}</div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
            <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Inactive / Locked' : 'Nonaktif' }}</div>
            <div class="text-2xl font-heading font-extrabold font-mono-num text-crimson mt-1" id="admin-stat-inactive">{{ $managedUsers->where('is_active', false)->count() }}</div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
            <div class="text-xs font-medium text-slate-500">{{ $isEn ? 'Active Sessions' : 'Sesi Login Aktif' }}</div>
            <div class="text-2xl font-heading font-extrabold font-mono-num text-teal mt-1" id="admin-stat-sessions">{{ $managedUsers->sum('active_sessions_count') }}</div>
        </div>
    </div>

    <!-- Full-Width User & RBAC Access Control -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <!-- Header & Actions -->
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'User & RBAC Access Control' : 'Manajemen Pengguna & Hak Akses RBAC' }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $isEn ? 'Manage accounts, role & department scopes, active sessions, and password resets.' : 'Kelola akun pengguna, role & cakupan departemen, sesi login aktif, dan reset kata sandi.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="document.getElementById('rbac-matrix-panel').classList.toggle('hidden')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-navy transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>{{ $isEn ? 'RBAC Matrix' : 'Matriks RBAC' }}</span>
                </button>
                <button type="button" onclick="openUserModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>{{ $isEn ? 'Add User' : 'Tambah Pengguna' }}</span>
                </button>
            </div>
        </div>

        <!-- Collapsible RBAC Reference Matrix -->
        <div id="rbac-matrix-panel" class="hidden bg-slate-50/80 border-b border-slate-200/80 px-5 py-4">
            <div class="text-xs font-bold text-navy mb-2.5">{{ $isEn ? 'Role & Department Scope Reference' : 'Referensi Hak Akses Role & Department Scope' }}</div>
            <div class="overflow-x-auto bg-white border border-slate-200 rounded-xl">
                <table class="w-full min-w-[780px] text-left border-collapse text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Scope</th>
                            <th class="py-3 px-4">Input</th>
                            <th class="py-3 px-4">Review HOD</th>
                            <th class="py-3 px-4">Export</th>
                            <th class="py-3 px-4">System Topology</th>
                            <th class="py-3 px-4">User &amp; RBAC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td class="py-3 px-4 font-semibold text-navy whitespace-nowrap">General Manager (GM)</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $isEn ? 'All Depts (Results)' : 'Hasil Semua Dept' }}</td>
                            <td class="py-3 px-4">—</td>
                            <td class="py-3 px-4">—</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-slate-600 font-semibold">Read-Only</td>
                            <td class="py-3 px-4">—</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-semibold text-teal whitespace-nowrap">Head of Dept (HOD)</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $isEn ? 'Own Dept (+ Delegated)' : 'Dept Sendiri (+ Delegasi)' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4">—</td>
                            <td class="py-3 px-4">—</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">Admin / Staff</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $isEn ? 'Own Dept (+ Delegated)' : 'Dept Sendiri (+ Delegasi)' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4">—</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $isEn ? 'Own Dept' : 'Dept Sendiri' }}</td>
                            <td class="py-3 px-4">—</td>
                            <td class="py-3 px-4">—</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 font-semibold text-amber-700 whitespace-nowrap">IT Administrator</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $isEn ? 'System & All Depts' : 'Sistem & Semua Dept' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Yes' : 'Ya' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold whitespace-nowrap">{{ $isEn ? 'View & Edit' : 'Lihat & Edit' }}</td>
                            <td class="py-3 px-4 text-emerald-700 font-semibold">{{ $isEn ? 'Full' : 'Penuh' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Search & Filter Toolbar -->
        <div class="px-5 py-3.5 bg-slate-50/50 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <div class="relative flex-1 min-w-[200px]">
                    <input id="admin-user-search" type="text"
                        placeholder="{{ $isEn ? 'Search name or email...' : 'Cari nama atau email pengguna...' }}"
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal transition">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </div>

                <select id="admin-user-role-filter" class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Roles' : 'Semua Role' }}</option>
                    <option value="gm">General Manager (GM)</option>
                    <option value="hod">Head of Department (HOD)</option>
                    <option value="staff">Admin / Staff</option>
                    <option value="it_admin">IT Administrator</option>
                </select>

                <select id="admin-user-dept-filter" class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal">
                    <option value="">{{ $isEn ? 'All Departments' : 'Semua Departemen' }}</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->localizedName($locale) }} ({{ $dept->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-1.5" id="admin-user-status-pills">
                @foreach (['all' => ($isEn ? 'All' : 'Semua'), 'active' => ($isEn ? 'Active' : 'Aktif'), 'inactive' => ($isEn ? 'Inactive' : 'Nonaktif')] as $sKey => $sLabel)
                    <button type="button" data-status="{{ $sKey }}"
                        class="admin-status-btn px-3 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer {{ $sKey === 'all' ? 'bg-navy text-white border-navy' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        {{ $sLabel }}
                    </button>
                @endforeach
                <span id="admin-users-count" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-navy font-mono-num text-[11px] font-semibold">
                    {{ $managedUsers->count() }}/{{ $managedUsers->count() }}
                </span>
                <button type="button" id="admin-users-reset-btn" onclick="resetAdminUserFilters()" class="hidden px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-rose-200 bg-rose-50 text-crimson-dark hover:bg-crimson hover:text-white transition cursor-pointer">
                    Reset
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table id="admin-users-table" class="w-full min-w-[980px] text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="name">{{ $isEn ? 'User' : 'Pengguna' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="role">Role</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="department">{{ $isEn ? 'Department Scope' : 'Cakupan Departemen' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="last_login_at">{{ $isEn ? 'Last Login (WITA) & Sessions' : 'Login Terakhir (WITA) & Sesi' }}</th>
                        <th class="py-3.5 px-5 cursor-pointer select-none hover:text-navy transition" data-sort-key="is_active">Status</th>
                        <th class="py-3.5 px-5 text-right">{{ $isEn ? 'Actions' : 'Aksi' }}</th>
                    </tr>
                </thead>
                <tbody id="admin-users-tbody" class="divide-y divide-slate-100">
                    @php
                        $roleBadgeCss = [
                            'gm' => 'bg-navy text-white',
                            'hod' => 'bg-teal/10 text-teal border border-teal/20',
                            'staff' => 'bg-slate-100 text-slate-700 border border-slate-200',
                            'it_admin' => 'bg-amber-50 text-amber-800 border border-amber-200',
                        ];
                    @endphp
                    @foreach ($managedUsers as $u)
                        @php
                            $isSelf = $u->id === $user->id;
                            $sessions = (int) ($u->active_sessions_count ?? 0);
                            $deptText = in_array($u->role, ['gm', 'it_admin'], true)
                                ? ($isEn ? 'Executive (All Departments)' : 'Executive (Semua Departemen)')
                                : ($u->department ? $u->department->name.' ('.$u->department->code.')' : '-');
                            $lastLogin = $u->last_login_at
                                ? $u->last_login_at->copy()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA'
                                : ($isEn ? 'Never' : 'Belum pernah');
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 align-middle min-w-[230px]">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-xl bg-navy/10 text-navy font-heading font-bold text-xs flex items-center justify-center shrink-0">{{ strtoupper(substr($u->name, 0, 2)) }}</span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-navy flex items-center gap-1.5">
                                            <span class="truncate">{{ $u->name }}</span>
                                            @if ($isSelf)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-teal/10 text-teal font-semibold">{{ $isEn ? 'You' : 'Anda' }}</span>
                                            @endif
                                        </div>
                                        <div class="font-mono-num text-[11px] text-slate-500 truncate mt-0.5">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-mono-num font-semibold {{ $roleBadgeCss[$u->role] ?? $roleBadgeCss['staff'] }}">{{ strtoupper($u->role) }}</span>
                            </td>
                            <td class="py-3.5 px-5 align-middle min-w-[200px] space-y-1">
                                <div class="text-slate-700 font-medium">{{ $deptText }}</div>
                                @if ($u->is_read_only || ($u->extraPermissions && $u->extraPermissions->isNotEmpty()))
                                    <div class="flex flex-wrap gap-1">
                                        @if ($u->is_read_only)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-600 font-mono-num text-[10px] font-semibold">PRIMARY: READ-ONLY</span>
                                        @endif
                                        @foreach ($u->extraPermissions as $perm)
                                            @php
                                                $permCls = match($perm->access_level) {
                                                    'review' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'input' => 'bg-cyan-50 text-teal border-cyan-200',
                                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                };
                                            @endphp
                                            <span class="px-1.5 py-0.5 rounded border font-mono-num text-[10px] font-semibold {{ $permCls }}">
                                                +{{ $perm->department?->code }}: {{ strtoupper($perm->access_level) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                                <div class="font-mono-num text-[11px] text-slate-700">{{ $lastLogin }}</div>
                                <div class="text-[11px] text-slate-400 font-mono-num mt-0.5">
                                    {!! $sessions > 0 ? '<span class="text-emerald-700 font-semibold">'.$sessions.' '.($isEn ? 'active session(s)' : 'sesi aktif').'</span>' : ($isEn ? '0 active sessions' : '0 sesi aktif') !!}
                                    @if ($u->last_login_ip) &bull; {{ $u->last_login_ip }} @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-5 align-middle whitespace-nowrap">
                                <button type="button" onclick="toggleAdminUserStatus({{ $u->id }})" @disabled($isSelf)
                                    class="px-2.5 py-1 rounded-full text-[11px] font-semibold border transition {{ $isSelf ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }} {{ $u->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-crimson-dark border-rose-200 hover:bg-rose-100' }}">
                                    {{ $u->is_active ? ($isEn ? 'Active' : 'Aktif') : ($isEn ? 'Inactive' : 'Nonaktif') }}
                                </button>
                            </td>
                            <td class="py-3.5 px-5 align-middle text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button" onclick="openUserModalById({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-navy hover:text-white text-navy font-semibold transition cursor-pointer">{{ $isEn ? 'Edit' : 'Ubah' }}</button>
                                    <button type="button" onclick="openAdminResetPasswordModalById({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-600 hover:text-white text-amber-800 font-semibold transition cursor-pointer">Reset PW</button>
                                    @if (! $isSelf && $sessions > 0)
                                        <button type="button" onclick="revokeAdminUserSessions({{ $u->id }})" class="px-2.5 py-1.5 rounded-lg bg-teal/10 hover:bg-teal hover:text-white text-teal font-semibold transition cursor-pointer">{{ $isEn ? 'Kick' : 'Putus Sesi' }}</button>
                                    @endif
                                    @if (! $isSelf)
                                        <button type="button" onclick="openDeleteUserModal({{ $u->id }})" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-crimson hover:text-white text-crimson-dark font-semibold transition cursor-pointer">{{ $isEn ? 'Del' : 'Hapus' }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="admin-users-empty" class="hidden py-10 text-center text-xs text-slate-500 space-y-2">
            <div>{{ $isEn ? 'No users match your filter criteria.' : 'Tidak ada pengguna yang cocok dengan filter pencarian.' }}</div>
            <button type="button" onclick="resetAdminUserFilters()" class="text-teal font-semibold hover:underline cursor-pointer">
                {{ $isEn ? 'Reset Filters' : 'Reset Filter' }}
            </button>
        </div>
        <div id="admin-users-pagination"></div>
    </div>

    <!-- Department Table Templates Management (Focused Access Schema Control) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-heading font-bold text-navy">
                    {{ $isEn ? 'Department Report Templates' : 'Template Tabel Departemen' }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $isEn ? 'Enable or disable active departmental report schemas per division.' : 'Aktifkan atau nonaktifkan skema tabel laporan departemen per divisi.' }}
                </p>
            </div>
            <button type="button" onclick="switchWorkspaceTab('audit')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-semibold text-navy transition cursor-pointer">
                <span>{{ $isEn ? 'Open Audit Trail' : 'Buka Audit Trail' }}</span>
                <span>&rarr;</span>
            </button>
        </div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[380px] overflow-y-auto">
            @foreach ($allTemplates as $tpl)
                <div class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60 flex items-start justify-between gap-3 text-xs">
                    <div class="space-y-0.5 min-w-0">
                        <div class="font-mono-num font-bold text-teal">{{ $tpl->code }} &bull; {{ $tpl->department?->code }}</div>
                        <div class="font-semibold text-navy truncate">{{ $tpl->localizedName($locale) }}</div>
                        <div class="text-[11px] text-slate-500">{{ count($tpl->columns_schema ?? []) }} {{ $isEn ? 'columns' : 'kolom' }}</div>
                    </div>
                    <button type="button" onclick="toggleTemplateStatus({{ $tpl->id }})"
                        class="shrink-0 px-2.5 py-1 rounded-full font-semibold border cursor-pointer transition {{ $tpl->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}">
                        {{ $tpl->is_active ? ($isEn ? 'Active' : 'Aktif') : ($isEn ? 'Off' : 'Nonaktif') }}
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Database Overview & Snapshot Backup Center (IT Admin Only) -->
    @if (!empty($databaseStats))
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.04)] overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-heading font-bold text-navy">
                        {{ $isEn ? 'Database Engine & Backup Center' : 'Manajemen Database & Backup Sistem' }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $databaseStats['connection'] }} &bull; {{ $databaseStats['database'] }} &bull;
                        <span class="font-mono-num font-semibold text-navy">{{ $databaseStats['size_human'] }}</span> &bull;
                        {{ $databaseStats['total_tables'] }} {{ $isEn ? 'tables' : 'tabel' }} (<span class="font-mono-num">{{ number_format($databaseStats['total_rows'], 0, ',', '.') }}</span> {{ $isEn ? 'rows' : 'baris' }})
                    </p>
                </div>
                <button type="button" id="admin-backup-create-btn" onclick="createDatabaseBackup()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-navy hover:bg-navy-light text-white text-xs font-semibold transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>{{ $isEn ? 'Create Backup Now' : 'Buat Backup Sekarang' }}</span>
                </button>
            </div>

            <div class="p-5 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Live Core Table Row Counts -->
                <div class="lg:col-span-4 border border-slate-200/80 rounded-2xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-heading font-bold text-navy">
                        {{ $isEn ? 'Database Table Statistics' : 'Statistik Tabel Database' }}
                    </div>
                    <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto text-xs">
                        @foreach ($databaseStats['table_counts'] as $tbl => $cnt)
                            <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                                <span class="font-mono-num text-slate-700">{{ $tbl }}</span>
                                <span class="font-mono-num font-bold text-navy">{{ number_format($cnt, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Stored Backup Snapshots Table -->
                <div class="lg:col-span-8 border border-slate-200/80 rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left border-collapse text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="py-3 px-4">{{ $isEn ? 'Backup Filename' : 'Nama File Backup' }}</th>
                                    <th class="py-3 px-4">{{ $isEn ? 'Format' : 'Format' }}</th>
                                    <th class="py-3 px-4 text-right">{{ $isEn ? 'Size' : 'Ukuran' }}</th>
                                    <th class="py-3 px-4">{{ $isEn ? 'Created (WITA)' : 'Dibuat (WITA)' }}</th>
                                    <th class="py-3 px-4 text-right">{{ $isEn ? 'Actions' : 'Aksi' }}</th>
                                </tr>
                            </thead>
                            <tbody id="admin-backups-tbody" class="divide-y divide-slate-100">
                                @forelse ($backupFiles ?? [] as $bak)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3.5 px-4 font-mono-num font-semibold text-navy">{{ $bak['filename'] }}</td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono-num font-semibold bg-teal/10 text-teal border border-teal/20">{{ $bak['extension'] }}</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono-num text-slate-600 whitespace-nowrap">{{ $bak['size_human'] }}</td>
                                        <td class="py-3.5 px-4 font-mono-num text-slate-500 whitespace-nowrap">{{ $bak['created_wita'] }}</td>
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <a href="{{ route('admin.backups.download', $bak['filename']) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-navy hover:text-white text-navy font-semibold transition">
                                                    {{ $isEn ? 'Download' : 'Unduh' }}
                                                </a>
                                                <button type="button" onclick="deleteDatabaseBackup('{{ e($bak['filename']) }}')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-crimson hover:text-white text-crimson-dark font-semibold transition cursor-pointer">
                                                    {{ $isEn ? 'Del' : 'Hapus' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            {{ $isEn ? 'No database backups created yet. Click "Create Backup Now" to generate a snapshot.' : 'Belum ada file backup database. Klik "Buat Backup Sekarang" untuk membuat snapshot.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
