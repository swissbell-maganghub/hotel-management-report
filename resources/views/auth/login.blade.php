@extends('layouts.base')

@section('title', $locale === 'en' ? 'Sign In' : 'Masuk Sistem')

@section('body')
<div class="flex-1 flex items-center justify-center p-4">
    <div class="w-full max-w-[420px]">
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-[0_8px_30px_rgba(15,41,66,0.06)]">
            <!-- Compact Brand Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="{{ asset('images/swissbel-logo.png') }}" alt="Swiss-Belhotel Makassar" class="h-11 w-11 object-contain shrink-0">
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-wider text-teal font-semibold truncate">
                            Swiss-Belhotel Waterfront
                        </div>
                        <h1 class="text-base font-heading font-extrabold text-navy leading-tight truncate">
                            Management Report
                        </h1>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 shrink-0">
                    <button type="button"
                            data-theme-toggle
                            data-label-dark="{{ ($locale ?? 'id') === 'en' ? 'Switch to Dark Mode' : 'Aktifkan Mode Gelap' }}"
                            data-label-light="{{ ($locale ?? 'id') === 'en' ? 'Switch to Light Mode' : 'Aktifkan Mode Terang' }}"
                            aria-label="{{ ($locale ?? 'id') === 'en' ? 'Toggle Light / Dark Mode' : 'Pilih Mode Terang atau Gelap' }}"
                            aria-pressed="false"
                            title="{{ ($locale ?? 'id') === 'en' ? 'Switch to Dark Mode' : 'Aktifkan Mode Gelap' }}"
                            class="sbwm-theme-toggle inline-flex items-center gap-0.5 rounded-full bg-slate-100 p-0.5 border border-slate-200 transition cursor-pointer shrink-0">
                        <span data-theme-pill="light" class="w-6 h-6 rounded-full flex items-center justify-center bg-white text-amber-500 shadow-2xs transition">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="4"/>
                                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                            </svg>
                        </span>
                        <span data-theme-pill="dark" class="w-6 h-6 rounded-full flex items-center justify-center text-slate-500 transition">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </span>
                    </button>

                    <div class="inline-flex rounded-full bg-slate-100 p-0.5 border border-slate-200 text-[11px] font-semibold shrink-0">
                        <a href="{{ route('locale.switch', 'id') }}" class="px-2.5 py-1 rounded-full transition {{ $locale === 'id' ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy' }}">ID</a>
                        <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1 rounded-full transition {{ $locale === 'en' ? 'bg-navy text-white' : 'text-slate-600 hover:text-navy' }}">EN</a>
                    </div>
                </div>
            </div>

            <!-- Compact Sign-In Form -->
            <div class="p-5 sm:p-6 space-y-4">
                <div>
                    <h2 class="text-sm font-heading font-bold text-navy">
                        {{ $locale === 'en' ? 'Sign In' : 'Masuk Sistem' }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $locale === 'en' ? 'Use your authorized hotel account.' : 'Gunakan akun hotel Anda yang terotorisasi.' }}
                    </p>
                </div>

                @if ($errors->any())
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-medium text-crimson-dark flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" id="login-form" class="space-y-3.5">
                    @csrf
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">
                            {{ $locale === 'en' ? 'Email' : 'Email Kerja' }}
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                            placeholder="nama@swissbel.local"
                            class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition">
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">
                            {{ $locale === 'en' ? 'Password' : 'Kata Sandi' }}
                        </label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-3.5 pr-10 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition">
                            <button type="button" id="toggle-password-btn" aria-label="Toggle password visibility"
                                class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-navy transition cursor-pointer">
                                <svg id="eye-open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eye-closed" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal focus:ring-teal">
                            <span>{{ $locale === 'en' ? 'Remember me' : 'Ingat sesi' }}</span>
                        </label>
                    </div>

                    <button type="submit" id="login-submit-btn"
                        class="w-full py-2.5 px-4 rounded-xl bg-navy hover:bg-navy-light text-white text-sm font-semibold transition cursor-pointer disabled:opacity-60">
                        {{ $locale === 'en' ? 'Sign In' : 'Masuk' }}
                    </button>
                </form>

                @if (! empty($testingLoginEnabled) && $testingUsers->isNotEmpty())
                    @php $firstTu = $testingUsers->first(); @endphp
                    <form method="POST" action="{{ route('login.as') }}" class="pt-4 border-t border-slate-100 space-y-2">
                        @csrf
                        <input type="hidden" id="login-as-email-input" name="email" value="{{ $firstTu->email }}">
                        <div class="flex items-center justify-between gap-2">
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                {{ $locale === 'en' ? 'Login As (Testing)' : 'Login Sebagai (Testing)' }}
                            </label>
                            <span class="font-mono-num text-[11px] px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                pass: password
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Custom Styled Dropdown -->
                            <div class="relative flex-1 min-w-0" id="login-as-dropdown-wrap">
                                <button type="button" id="login-as-trigger"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50/80 hover:border-slate-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-teal/25 focus:border-teal flex items-center justify-between gap-2 transition cursor-pointer">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <span id="login-as-label-name" class="font-semibold text-navy truncate">{{ $firstTu->name }}</span>
                                        <span id="login-as-label-badge" class="font-mono-num text-[10px] font-semibold px-1.5 py-0.5 rounded bg-teal/10 text-teal shrink-0">
                                            {{ strtoupper($firstTu->role) }} &bull; {{ $firstTu->department?->code ?? 'ALL' }}
                                        </span>
                                    </span>
                                    <svg id="login-as-chevron" class="w-4 h-4 text-slate-400 transition-transform duration-150 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <div id="login-as-menu" class="dropdown-panel hidden absolute left-0 right-0 bottom-full mb-1.5 bg-white border border-slate-200/90 rounded-2xl shadow-[0_16px_40px_rgba(15,41,66,0.16)] p-1.5 max-h-56 overflow-y-auto z-50 space-y-0.5">
                                    @foreach ($testingUsers as $tu)
                                        <button type="button"
                                            data-email="{{ $tu->email }}"
                                            data-name="{{ $tu->name }}"
                                            data-badge="{{ strtoupper($tu->role) }} • {{ $tu->department?->code ?? 'ALL' }}"
                                            class="login-as-option w-full text-left px-2.5 py-2 rounded-xl hover:bg-slate-100/80 flex items-center justify-between gap-2 text-xs transition cursor-pointer {{ $loop->first ? 'bg-slate-100/70' : '' }}">
                                            <span class="font-semibold text-navy truncate">{{ $tu->name }}</span>
                                            <span class="font-mono-num text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-700 shrink-0">
                                                {{ strtoupper($tu->role) }} &bull; {{ $tu->department?->code ?? 'ALL' }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <button type="submit"
                                class="px-3.5 py-2 rounded-xl bg-teal hover:bg-teal-light text-white text-xs font-semibold transition cursor-pointer shrink-0">
                                {{ $locale === 'en' ? 'Go' : 'Masuk' }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- Compact Footer -->
            <div class="px-5 py-3 bg-slate-50/80 border-t border-slate-100 rounded-b-2xl flex items-center justify-between gap-4 text-[11px] text-slate-500">
                <span>{{ $locale === 'en' ? 'Intranet & VPN' : 'Intranet & VPN' }}</span>
                <span>{{ $locale === 'en' ? 'IT Support' : 'Bantuan: IT Dept' }}</span>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const pwd = document.getElementById('password');
        const toggle = document.getElementById('toggle-password-btn');
        const eyeOpen = document.getElementById('eye-open');
        const eyeClosed = document.getElementById('eye-closed');
        const form = document.getElementById('login-form');
        const submitBtn = document.getElementById('login-submit-btn');

        toggle?.addEventListener('click', function () {
            const isHidden = pwd.type === 'password';
            pwd.type = isHidden ? 'text' : 'password';
            eyeOpen.classList.toggle('hidden', isHidden);
            eyeClosed.classList.toggle('hidden', !isHidden);
        });

        form?.addEventListener('submit', function () {
            submitBtn.disabled = true;
        });

        const wrap = document.getElementById('login-as-dropdown-wrap');
        const trigger = document.getElementById('login-as-trigger');
        const menu = document.getElementById('login-as-menu');
        const chevron = document.getElementById('login-as-chevron');
        const emailInput = document.getElementById('login-as-email-input');
        const labelName = document.getElementById('login-as-label-name');
        const labelBadge = document.getElementById('login-as-label-badge');

        function closeLoginAs() {
            menu?.classList.add('hidden');
            chevron?.classList.remove('rotate-180');
        }

        trigger?.addEventListener('click', function (e) {
            e.stopPropagation();
            const willOpen = menu?.classList.contains('hidden');
            menu?.classList.toggle('hidden');
            chevron?.classList.toggle('rotate-180', !!willOpen);
        });

        document.querySelectorAll('.login-as-option').forEach(function (opt) {
            opt.addEventListener('click', function () {
                emailInput.value = opt.dataset.email;
                labelName.textContent = opt.dataset.name;
                labelBadge.textContent = opt.dataset.badge;
                document.querySelectorAll('.login-as-option').forEach((o) => o.classList.remove('bg-slate-100/70'));
                opt.classList.add('bg-slate-100/70');
                closeLoginAs();
            });
        });

        document.addEventListener('click', function (e) {
            if (wrap && !wrap.contains(e.target)) closeLoginAs();
        });
    })();
</script>
@endsection
