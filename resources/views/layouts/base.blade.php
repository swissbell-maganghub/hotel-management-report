<!DOCTYPE html>
<html lang="{{ $locale ?? 'id' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/swissbel-icon.png') }}">
    <title>@yield('title', 'Hotel Management Report System') — Swiss-Belhotel Waterfront Makassar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('sbwm_theme');
                const isDark = saved === 'dark';
                if (isDark) {
                    document.documentElement.classList.add('dark');
                }
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (_) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-alabaster text-slate-900 antialiased min-h-screen flex flex-col">
    @yield('body')

    <!-- Toast Feedback -->
    <div id="toast" class="fixed top-20 right-4 z-50 hidden max-w-sm bg-white border border-slate-200 border-l-4 border-l-gold rounded-xl shadow-lg px-4 py-3 text-sm text-slate-800 transition-all">
        <div class="flex items-start gap-2.5">
            <span id="toast-dot" class="mt-1.5 w-2 h-2 rounded-full bg-teal shrink-0"></span>
            <p id="toast-message" class="font-medium leading-snug"></p>
        </div>
    </div>

    <script>
        window.showToast = function (message, tone = 'info') {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toast-message');
            const dotEl = document.getElementById('toast-dot');
            if (!toast || !msgEl) return;

            msgEl.textContent = message;
            dotEl.className = 'mt-1.5 w-2 h-2 rounded-full shrink-0 ' + (
                tone === 'error' ? 'bg-crimson' : (tone === 'success' ? 'bg-emerald-600' : 'bg-teal')
            );

            toast.classList.remove('hidden');
            clearTimeout(window.__toastTimer);
            window.__toastTimer = setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        };

        window.syncSbwmThemeButtons = function () {
            const isDark = document.documentElement.classList.contains('dark');
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
                btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                const moon = btn.querySelector('[data-theme-icon="moon"]');
                const sun = btn.querySelector('[data-theme-icon="sun"]');
                if (moon) moon.classList.toggle('hidden', isDark);
                if (sun) sun.classList.toggle('hidden', !isDark);

                const lightPill = btn.querySelector('[data-theme-pill="light"]');
                const darkPill = btn.querySelector('[data-theme-pill="dark"]');
                if (lightPill && darkPill) {
                    lightPill.className = 'w-6 h-6 rounded-full flex items-center justify-center transition ' +
                        (isDark ? 'text-slate-400' : 'bg-white text-amber-500 shadow-2xs');
                    darkPill.className = 'w-6 h-6 rounded-full flex items-center justify-center transition ' +
                        (isDark ? 'bg-navy text-white shadow-2xs' : 'text-slate-500');
                }

                const label = btn.getAttribute(isDark ? 'data-label-light' : 'data-label-dark');
                if (label) btn.setAttribute('title', label);
            });
        };

        window.toggleSbwmTheme = function (forcedTheme) {
            const root = document.documentElement;
            const nextDark = forcedTheme ? forcedTheme === 'dark' : !root.classList.contains('dark');
            root.classList.toggle('dark', nextDark);
            try {
                localStorage.setItem('sbwm_theme', nextDark ? 'dark' : 'light');
            } catch (_) {}
            window.syncSbwmThemeButtons();
            window.dispatchEvent(new CustomEvent('sbwm-theme-change', {
                detail: { theme: nextDark ? 'dark' : 'light', isDark: nextDark }
            }));
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.syncSbwmThemeButtons();
            document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    const pill = e.target?.closest?.('[data-theme-pill]');
                    const forced = pill?.getAttribute('data-theme-pill');
                    if (forced === 'light' || forced === 'dark') {
                        window.toggleSbwmTheme(forced);
                    } else {
                        window.toggleSbwmTheme();
                    }
                });
            });
        });
    </script>
</body>
</html>
