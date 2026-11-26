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
    <div id="toast" role="status" aria-live="polite" class="fixed top-20 right-4 left-4 sm:left-auto sm:max-w-sm z-50 hidden">
        <div class="flex items-center gap-3 bg-white border border-slate-200/80 rounded-2xl shadow-lg py-3 pl-3 pr-5">
            <span id="toast-icon" class="w-8 h-8 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path id="toast-icon-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d=""/></svg>
            </span>
            <p id="toast-message" class="text-xs font-semibold text-navy leading-snug"></p>
        </div>
    </div>

    <script>
        window.showToast = function (message, tone = 'info') {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toast-message');
            const iconEl = document.getElementById('toast-icon');
            if (!toast || !msgEl) return;

            const [color, path] = {
                success: ['bg-emerald-50 text-emerald-700', 'M5 13l4 4L19 7'],
                error: ['bg-rose-50 text-crimson-dark', 'M6 18L18 6M6 6l12 12'],
            }[tone] || ['bg-cyan-50 text-teal', 'M12 8h.01M12 11v5'];

            msgEl.textContent = message;
            iconEl.className = 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 ' + color;
            document.getElementById('toast-icon-path').setAttribute('d', path);

            toast.classList.remove('hidden');
            clearTimeout(window.__toastTimer);
            // Pesan error biasanya lebih panjang, beri waktu baca lebih lama.
            window.__toastTimer = setTimeout(() => {
                toast.classList.add('hidden');
            }, tone === 'error' ? 6000 : 3500);
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
