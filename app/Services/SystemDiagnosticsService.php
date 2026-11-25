<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SystemDiagnosticsService
{
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql',
        'mbstring',
        'openssl',
        'tokenizer',
        'xml',
        'ctype',
        'json',
        'fileinfo',
        'zip',
    ];

    public function __construct(
        private readonly DatabaseBackupService $backupService,
    ) {}

    /**
     * Run non-destructive runtime diagnostics & production-readiness checks (secrets omitted).
     */
    public function collect(bool $isEn = false): array
    {
        $db = $this->probeDatabase();
        $storage = $this->probeStorage();
        $backups = $this->probeBackups();
        $missingExts = array_values(array_filter(
            self::REQUIRED_EXTENSIONS,
            fn (string $ext): bool => ! extension_loaded($ext)
        ));

        $appEnv = (string) app()->environment();
        $appDebug = (bool) config('app.debug', false);
        $testingLogin = (bool) config('app.testing_login', false);
        $hasAppKey = filled(config('app.key'));
        $appUrl = (string) config('app.url', '');
        $isHttps = str_starts_with($appUrl, 'https://');
        $secureCookie = (bool) config('session.secure', false);
        $hasManifest = is_file(public_path('build/manifest.json'));
        $hasHot = is_file(public_path('hot'));

        $failedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            try {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            } catch (\Throwable) {
                $failedJobs = 0;
            }
        }

        $checks = [
            [
                'key' => 'app_env',
                'label' => $isEn ? 'Application Environment' : 'Environment Aplikasi (APP_ENV)',
                'status' => $appEnv === 'production' ? 'pass' : 'warn',
                'current' => strtoupper($appEnv),
                'target' => 'PRODUCTION',
                'advice' => $appEnv === 'production'
                    ? ($isEn ? 'Running in production mode.' : 'Berjalan dalam mode produksi.')
                    : ($isEn ? 'Set APP_ENV=production before go-live.' : 'Ubah APP_ENV=production saat deploy produksi.'),
            ],
            [
                'key' => 'app_debug',
                'label' => $isEn ? 'Debug Mode (APP_DEBUG)' : 'Mode Debug (APP_DEBUG)',
                'status' => ! $appDebug ? 'pass' : 'fail',
                'current' => $appDebug ? 'TRUE (ON)' : 'FALSE (OFF)',
                'target' => 'FALSE (OFF)',
                'advice' => ! $appDebug
                    ? ($isEn ? 'Stack traces are hidden from users.' : 'Stack trace aman disembunyikan dari pengguna.')
                    : ($isEn ? 'CRITICAL: Set APP_DEBUG=false in production.' : 'KRITIS: Wajib ubah APP_DEBUG=false di produksi.'),
            ],
            [
                'key' => 'testing_login',
                'label' => $isEn ? 'Quick Demo Login (APP_TESTING_LOGIN)' : 'Login Cepat Demo (APP_TESTING_LOGIN)',
                'status' => ! $testingLogin ? 'pass' : 'fail',
                'current' => $testingLogin ? 'ENABLED' : 'DISABLED',
                'target' => 'DISABLED',
                'advice' => ! $testingLogin
                    ? ($isEn ? 'Password-only authentication enforced.' : 'Autentikasi penuh dengan kata sandi aktif.')
                    : ($isEn ? 'CRITICAL: Set APP_TESTING_LOGIN=false in production.' : 'KRITIS: Wajib matikan APP_TESTING_LOGIN=false di produksi.'),
            ],
            [
                'key' => 'app_key',
                'label' => $isEn ? 'Encryption Key (APP_KEY)' : 'Kunci Enkripsi (APP_KEY)',
                'status' => $hasAppKey ? 'pass' : 'fail',
                'current' => $hasAppKey ? ($isEn ? 'Configured' : 'Terkonfigurasi') : ($isEn ? 'Missing' : 'Kosong'),
                'target' => $isEn ? 'Configured' : 'Terkonfigurasi',
                'advice' => $hasAppKey
                    ? ($isEn ? 'Session & cookie encryption key is set.' : 'Kunci enkripsi sesi & cookie tersedia.')
                    : ($isEn ? 'Run php artisan key:generate immediately.' : 'Jalankan php artisan key:generate segera.'),
            ],
            [
                'key' => 'https_cookies',
                'label' => $isEn ? 'HTTPS & Secure Session Cookie' : 'HTTPS & Secure Session Cookie',
                'status' => ($isHttps && $secureCookie) ? 'pass' : 'warn',
                'current' => ($isHttps ? 'HTTPS' : 'HTTP').' • '.($secureCookie ? 'Secure Cookie' : 'Standard Cookie'),
                'target' => 'HTTPS • Secure Cookie',
                'advice' => ($isHttps && $secureCookie)
                    ? ($isEn ? 'TLS & Secure cookie flags enforced.' : 'TLS dan flag Secure cookie aktif.')
                    : ($isEn ? 'Use https:// APP_URL & SESSION_SECURE_COOKIE=true on LAN/VPN TLS.' : 'Gunakan APP_URL https:// & SESSION_SECURE_COOKIE=true pada server TLS.'),
            ],
            [
                'key' => 'db_connection',
                'label' => $isEn ? 'Database Connectivity' : 'Koneksi Database Live',
                'status' => $db['healthy'] ? 'pass' : 'fail',
                'current' => $db['healthy']
                    ? strtoupper($db['driver'])." • {$db['latency_ms']} ms"
                    : ($isEn ? 'Disconnected' : 'Gagal Terhubung'),
                'target' => 'MYSQL • < 50 ms',
                'advice' => $db['healthy']
                    ? ($isEn ? "Connected to {$db['database']} ({$db['version']})." : "Terhubung ke {$db['database']} (v{$db['version']}).")
                    : ($isEn ? 'Verify MySQL service & DB_* credentials.' : 'Periksa service MySQL dan konfigurasi DB_* di .env.'),
            ],
            [
                'key' => 'storage_writable',
                'label' => $isEn ? 'Private Storage & Disk Headroom' : 'Penyimpanan Privat & Kapasitas Disk',
                'status' => ! $storage['writable'] ? 'fail' : (($storage['used_pct'] !== null && $storage['used_pct'] >= 85) ? 'warn' : 'pass'),
                'current' => $storage['writable']
                    ? ($storage['used_pct'] !== null ? "{$storage['used_pct']}% used ({$storage['free_gb']} GB free)" : 'Read/Write OK')
                    : ($isEn ? 'Not Writable' : 'Tidak Bisa Ditulis'),
                'target' => '< 85% used • Writable',
                'advice' => $storage['writable']
                    ? ($isEn ? 'storage/app/private read/write verified.' : 'Direktori storage/app/private siap baca/tulis.')
                    : ($isEn ? 'Check directory permissions on storage/app/private.' : 'Periksa permission folder storage/app/private.'),
            ],
            [
                'key' => 'backup_recency',
                'label' => $isEn ? 'Database Backup Snapshot' : 'Snapshot Backup Database',
                'status' => $backups['status'],
                'current' => $backups['count'] > 0
                    ? "{$backups['count']} file • {$backups['latest_age_label']}"
                    : ($isEn ? '0 backups' : 'Belum ada backup'),
                'target' => $isEn ? 'Snapshot <= 7 days' : 'Snapshot <= 7 hari',
                'advice' => $backups['advice_'.($isEn ? 'en' : 'id')],
            ],
            [
                'key' => 'php_extensions',
                'label' => $isEn ? 'Required PHP Extensions' : 'Ekstensi PHP Wajib',
                'status' => count($missingExts) === 0 ? 'pass' : 'fail',
                'current' => count($missingExts) === 0
                    ? count(self::REQUIRED_EXTENSIONS).'/'.count(self::REQUIRED_EXTENSIONS).' Loaded'
                    : 'Missing: '.implode(', ', $missingExts),
                'target' => count(self::REQUIRED_EXTENSIONS).'/'.count(self::REQUIRED_EXTENSIONS).' Loaded',
                'advice' => count($missingExts) === 0
                    ? ($isEn ? 'All Laravel & Excel/PDF extensions loaded.' : 'Seluruh ekstensi Laravel, Excel & PDF aktif.')
                    : ($isEn ? 'Install missing PHP extensions in php.ini.' : 'Aktifkan ekstensi PHP yang belum terpasang.'),
            ],
            [
                'key' => 'vite_assets',
                'label' => $isEn ? 'Frontend Production Build' : 'Build Asset Produksi (Vite)',
                'status' => ($hasManifest && ! $hasHot) ? 'pass' : 'warn',
                'current' => ($hasManifest ? 'manifest.json OK' : 'No manifest').($hasHot ? ' • hot active' : ''),
                'target' => 'manifest.json OK • no hot',
                'advice' => ($hasManifest && ! $hasHot)
                    ? ($isEn ? 'Serving compiled assets from public/build.' : 'Melayani asset terkompilasi dari public/build.')
                    : ($isEn ? 'Run npm run build and remove public/hot.' : 'Jalankan npm run build dan pastikan public/hot tidak ada.'),
            ],
            $this->probeChatbotAi($isEn),
        ];

        $passCount = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'pass'));
        $warnCount = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'warn'));
        $failCount = count(array_filter($checks, fn (array $c): bool => $c['status'] === 'fail'));

        return [
            'db' => $db,
            'storage' => $storage,
            'backups' => $backups,
            'missing_extensions' => $missingExts,
            'has_manifest' => $hasManifest,
            'has_hot' => $hasHot,
            'app_env' => $appEnv,
            'app_debug' => $appDebug,
            'testing_login' => $testingLogin,
            'has_app_key' => $hasAppKey,
            'is_https' => $isHttps,
            'checks' => $checks,
            'summary' => [
                'total' => count($checks),
                'pass' => $passCount,
                'warn' => $warnCount,
                'fail' => $failCount,
                'checked_at_wita' => now()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA',
            ],
            'runtime' => [
                'hostname' => gethostname() ?: '-',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'sapi' => PHP_SAPI,
                'cache_driver' => (string) config('cache.default'),
                'session_driver' => (string) config('session.default'),
                'queue_driver' => (string) config('queue.default'),
                'failed_jobs' => $failedJobs,
                'timezone' => (string) config('app.timezone'),
            ],
        ];
    }

    /**
     * AI assistant reachability: Ollama up and the configured model installed. The chatbot still works without it
     * (keyword answers), so a failure is a warning, not a hard fail.
     */
    public function probeChatbotAi(bool $isEn, bool $livePing = false): array
    {
        $cfg = ChatbotService::resolveProviderConfig();
        $provider = $cfg['provider'];
        $model = $cfg['model'];
        $check = ['key' => 'chatbot_ai', 'label' => $isEn ? 'AI Assistant Model' : 'Model AI Asisten', 'target' => "{$provider} • {$model} ready"];

        if ($provider !== 'ollama') {
            $hasKey = $cfg['api_key'] !== '';
            $ping = null;
            if ($provider !== 'local' && $hasKey && $livePing) {
                $ping = app(ChatbotService::class)->probeLiveLlm();
            }

            $breakerOpen = Cache::get("chatbot.llm.{$provider}.open_until", 0) > time();
            $lastErr = (array) Cache::get("chatbot.llm.{$provider}.last_error", []);
            $errType = $ping && ! $ping['ok'] ? ($ping['error_type'] ?? null) : ($lastErr['type'] ?? null);
            $errStatus = $ping && ! $ping['ok'] ? ($ping['status'] ?? null) : ($lastErr['status'] ?? null);
            $errDetail = $ping && ! $ping['ok'] ? ($ping['message'] ?? '') : ($lastErr['message'] ?? '');

            $hasIssue = $provider !== 'local' && (! $hasKey || $breakerOpen || $errType !== null || ($ping && ! $ping['ok']));
            $status = $hasIssue ? 'warn' : 'pass';
            $statusCode = $errStatus ? " HTTP {$errStatus}" : '';

            $current = match (true) {
                $provider === 'local' => 'local (AI off)',
                ! $hasKey => "{$provider} • API key missing",
                $errType === 'quota_exceeded' => "{$provider} • {$model} (QUOTA / CREDIT HABIS{$statusCode})",
                $errType === 'rate_limit' => "{$provider} • {$model} (LIMIT / HTTP 429)",
                $errType === 'auth_error' => "{$provider} • {$model} (API KEY ERROR{$statusCode})",
                $errType === 'model_not_found' => "{$provider} • {$model} (MODEL 404)",
                $breakerOpen || $errType !== null => "{$provider} • {$model} ({$errType}{$statusCode})",
                $ping && $ping['ok'] => "{$provider} • {$model} (OK {$ping['ms']} ms)",
                default => "{$provider} • {$model}",
            };

            $advice = match (true) {
                $provider === 'local' => $isEn ? 'Deterministic local mode active.' : 'Mode lokal deterministik aktif.',
                ! $hasKey => $isEn ? "Set API key in .env for {$provider} or switch CHATBOT_PROVIDER=ollama." : "Isi API key di .env untuk {$provider} atau ubah CHATBOT_PROVIDER=ollama.",
                $errType === 'quota_exceeded' => $isEn
                    ? "Quota/credits exhausted{$statusCode}. Top up billing or switch model/provider in .env. {$errDetail}"
                    : "Kuota atau kredit API habis{$statusCode}. Isi ulang billing atau ubah model/provider di .env. {$errDetail}",
                $errType === 'rate_limit' => $isEn
                    ? "Rate limit reached{$statusCode}. Wait briefly before retrying, or switch model/provider in .env."
                    : "Batas limit permintaan tercapai{$statusCode}. Tunggu sejenak atau ganti model/provider di .env.",
                $errType === 'auth_error' => $isEn
                    ? "API key rejected{$statusCode}. Verify API key and permissions in .env."
                    : "API key ditolak{$statusCode}. Periksa API key serta izin project di .env.",
                $errType === 'model_not_found' => $isEn
                    ? "Model {$model} unavailable{$statusCode}. Update model name in .env."
                    : "Model {$model} tidak tersedia{$statusCode}. Perbarui nama model di .env.",
                $breakerOpen || $errType !== null => $isEn
                    ? "Recent upstream errors paused AI temporarily; retries resume automatically. {$errDetail}"
                    : "Error sementara menjeda AI; sistem otomatis mencoba ulang. {$errDetail}",
                default => $isEn ? 'Cloud AI provider ready.' : 'Provider AI cloud siap digunakan.',
            };

            return $check + ['status' => $status, 'current' => $current, 'advice' => trim($advice)];
        }

        $breakerOpen = Cache::get("chatbot.llm.{$provider}.open_until", 0) > time();

        $base = preg_replace('#/v1/.*$#', '', (string) $cfg['endpoint']);
        try {
            $start = microtime(true);
            $tags = Http::timeout(3)->withOptions(['proxy' => ''])->get(rtrim($base, '/').'/api/tags');
            $ms = round((microtime(true) - $start) * 1000);
            $installed = $tags->successful() && collect($tags->json('models', []))->contains(fn ($m) => ($m['name'] ?? '') === $model);
            $up = $tags->successful();
        } catch (\Throwable) {
            [$up, $installed, $ms] = [false, false, 0];
        }

        return $check + [
            'status' => $up && $installed && ! $breakerOpen ? 'pass' : 'warn',
            'current' => ! $up ? 'Ollama unreachable' : (! $installed ? "model {$model} not installed" : "OK ({$ms} ms)".($breakerOpen ? ' • breaker open' : '')),
            'advice' => ! $up
                ? ($isEn ? 'Start the Ollama service; chatbot falls back to keyword answers meanwhile.' : 'Jalankan service Ollama; sementara chatbot memakai jawaban kata kunci.')
                : (! $installed
                    ? ($isEn ? "Run: ollama pull {$model}" : "Jalankan: ollama pull {$model}")
                    : ($isEn ? 'AI assistant ready.' : 'Asisten AI siap.')),
        ];
    }

    private function probeDatabase(): array
    {
        $driver = (string) config('database.default', 'mysql');
        $database = (string) config("database.connections.{$driver}.database", '');
        $host = (string) config("database.connections.{$driver}.host", '127.0.0.1');

        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 1);

            $version = '-';
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $vRow = DB::selectOne('SELECT VERSION() AS v');
                $version = (string) ($vRow->v ?? '-');
            } elseif ($driver === 'sqlite') {
                $vRow = DB::selectOne('SELECT sqlite_version() AS v');
                $version = (string) ($vRow->v ?? '-');
            }

            return [
                'healthy' => true,
                'driver' => $driver,
                'database' => $driver === 'sqlite' ? basename($database ?: ':memory:') : $database,
                'host' => $driver === 'sqlite' ? 'local-file' : $host,
                'latency_ms' => $latencyMs,
                'version' => $version,
            ];
        } catch (\Throwable) {
            return [
                'healthy' => false,
                'driver' => $driver,
                'database' => $database,
                'host' => $host,
                'latency_ms' => 0,
                'version' => '-',
            ];
        }
    }

    private function probeStorage(): array
    {
        $writable = false;
        try {
            $writable = Storage::disk('local')->put('healthcheck.tmp', 'ok');
            if (Storage::disk('local')->exists('healthcheck.tmp')) {
                Storage::disk('local')->delete('healthcheck.tmp');
            }
        } catch (\Throwable) {
            $writable = false;
        }

        $privatePath = storage_path('app/private');
        $freeBytes = @disk_free_space($privatePath);
        $totalBytes = @disk_total_space($privatePath);
        $usedPct = ($freeBytes !== false && $totalBytes > 0)
            ? round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1)
            : null;
        $freeGb = $freeBytes !== false ? round($freeBytes / 1073741824, 1) : null;

        return [
            'writable' => $writable,
            'used_pct' => $usedPct,
            'free_gb' => $freeGb,
        ];
    }

    private function probeBackups(): array
    {
        $list = $this->backupService->listBackups();
        $count = count($list);
        if ($count === 0) {
            return [
                'count' => 0,
                'status' => 'warn',
                'latest_filename' => null,
                'latest_size' => null,
                'latest_age_label' => 'None',
                'advice_id' => 'Belum ada snapshot backup. Buat backup pertama di User Management atau jadwalkan mysqldump.',
                'advice_en' => 'No backup snapshot yet. Create one under User Management or schedule mysqldump.',
            ];
        }

        $latest = $list[0];
        $created = Carbon::parse($latest['created_at_iso']);
        $ageDays = (int) $created->diffInDays(now());
        $ageHours = (int) $created->diffInHours(now());
        $ageLabel = $ageHours < 24
            ? "{$ageHours}h ago"
            : "{$ageDays}d ago";

        $isFresh = $ageDays <= 7;

        return [
            'count' => $count,
            'status' => $isFresh ? 'pass' : 'warn',
            'latest_filename' => $latest['filename'],
            'latest_size' => $latest['size_human'],
            'latest_age_label' => $ageLabel,
            'advice_id' => $isFresh
                ? "Backup terbaru: {$latest['filename']} ({$latest['size_human']})."
                : "Backup terakhir sudah {$ageDays} hari lalu ({$latest['filename']}). Segera buat snapshot baru.",
            'advice_en' => $isFresh
                ? "Latest backup: {$latest['filename']} ({$latest['size_human']})."
                : "Latest backup is {$ageDays} days old ({$latest['filename']}). Create a fresh snapshot.",
        ];
    }
}
