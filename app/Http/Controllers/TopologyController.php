<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TopologyComponent;
use App\Services\SystemDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopologyController extends Controller
{
    public function update(Request $request, TopologyComponent $component): JsonResponse
    {
        abort_unless($request->user()->canEditTopology(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:healthy,warning,degraded'],
            'gm_summary' => ['required', 'string', 'max:255'],
            'gm_summary_en' => ['required', 'string', 'max:255'],
            'it_technical_detail' => ['required', 'string', 'max:255'],
            'host_or_endpoint' => ['nullable', 'string', 'max:255'],
        ]);

        $component->update(array_merge($validated, [
            'last_checked_at' => now(),
        ]));

        AuditLog::record($request->user(), 'topology.updated', 'Topology', "Komponen topologi {$component->name} diperbarui ({$component->status}).");

        return response()->json([
            'message' => "Komponen {$component->name} berhasil diperbarui.",
            'component' => $component->fresh(),
        ]);
    }

    public function runHealthCheck(Request $request, SystemDiagnosticsService $diagnostics): JsonResponse
    {
        abort_unless($request->user()->canEditTopology(), 403);

        $locale = app()->getLocale() === 'en' ? 'en' : 'id';
        $diag = $diagnostics->collect($locale === 'en');
        $db = $diag['db'];
        $storage = $diag['storage'];
        $backups = $diag['backups'];
        $runtime = $diag['runtime'];
        $now = now();

        // 1. Application Server (PHP runtime, extensions & host)
        $missingExts = $diag['missing_extensions'];
        $appServerStatus = count($missingExts) > 0
            ? 'degraded'
            : (($storage['used_pct'] !== null && $storage['used_pct'] > 85) ? 'warning' : 'healthy');
        TopologyComponent::where('component_key', 'app_server')->update([
            'status' => $appServerStatus,
            'it_technical_detail' => "Host: {$runtime['hostname']} • PHP {$runtime['php_version']} ({$runtime['sapi']}) • ".(count($missingExts) === 0 ? 'Extensions OK' : 'Missing: '.implode(',', $missingExts)),
            'last_checked_at' => $now,
        ]);

        // 2. Web Server (Build manifest, stale public/hot check, URL scheme)
        $webStatus = ($diag['has_manifest'] && ! ($diag['app_env'] === 'production' && $diag['has_hot'])) ? 'healthy' : 'warning';
        TopologyComponent::where('component_key', 'web_server')->update([
            'status' => $webStatus,
            'it_technical_detail' => ($diag['is_https'] ? 'HTTPS' : 'HTTP').' • Assets: '.($diag['has_manifest'] ? 'Built' : 'Missing').($diag['has_hot'] ? ' (public/hot active)' : ' • Vite Prod'),
            'host_or_endpoint' => (string) config('app.url'),
            'last_checked_at' => $now,
        ]);

        // 3. Laravel Application (APP_KEY, APP_DEBUG, APP_TESTING_LOGIN)
        $laravelStatus = ! $diag['has_app_key']
            ? 'degraded'
            : (($diag['app_debug'] || $diag['testing_login']) ? 'warning' : 'healthy');
        TopologyComponent::where('component_key', 'laravel_app')->update([
            'status' => $laravelStatus,
            'it_technical_detail' => "Laravel {$runtime['laravel_version']} • Env: {$diag['app_env']} • Debug: ".($diag['app_debug'] ? 'ON' : 'OFF').' • TestLogin: '.($diag['testing_login'] ? 'ON' : 'OFF'),
            'last_checked_at' => $now,
        ]);

        // 4. Database Server (Live PDO + SELECT 1 + latency + version)
        TopologyComponent::where('component_key', 'mysql_db')->update([
            'status' => $db['healthy'] ? 'healthy' : 'degraded',
            'it_technical_detail' => $db['healthy']
                ? "Driver: {$db['driver']} ({$db['version']}) • Latency: {$db['latency_ms']} ms • DB: {$db['database']}"
                : 'Database connection check failed!',
            'host_or_endpoint' => $db['host'],
            'last_checked_at' => $now,
        ]);

        // 5. Private Storage (Live R/W probe + disk space)
        TopologyComponent::where('component_key', 'private_storage')->update([
            'status' => $storage['writable'] ? ($storage['used_pct'] !== null && $storage['used_pct'] > 85 ? 'warning' : 'healthy') : 'degraded',
            'gm_summary' => $storage['used_pct'] !== null
                ? "Kapasitas penyimpanan dokumen aman ({$storage['used_pct']}% terpakai)"
                : 'Penyimpanan dokumen privat aktif',
            'gm_summary_en' => $storage['used_pct'] !== null
                ? "Document storage healthy ({$storage['used_pct']}% used)"
                : 'Private document storage active',
            'it_technical_detail' => $storage['used_pct'] !== null
                ? "storage/app/private • {$storage['used_pct']}% used ({$storage['free_gb']} GB free) • Read/Write OK"
                : 'storage/app/private • '.($storage['writable'] ? 'Read/Write OK' : 'Write Failed'),
            'last_checked_at' => $now,
        ]);

        // 6. Backup Storage (Real snapshot count & recency)
        $backupStatus = $backups['status'] === 'pass' ? 'healthy' : 'warning';
        TopologyComponent::whereIn('component_key', ['backup', 'nas_backup'])->update([
            'status' => $backupStatus,
            'gm_summary' => $backups['count'] > 0
                ? "Tersedia {$backups['count']} snapshot backup ({$backups['latest_age_label']})."
                : 'Belum ada snapshot backup database tersimpan.',
            'gm_summary_en' => $backups['count'] > 0
                ? "{$backups['count']} backup snapshot(s) available ({$backups['latest_age_label']})."
                : 'No database backup snapshots stored yet.',
            'it_technical_detail' => $backups['count'] > 0
                ? "Latest: {$backups['latest_filename']} ({$backups['latest_size']}, {$backups['latest_age_label']})"
                : '0 snapshot di storage/app/private/backups — segera buat backup.',
            'last_checked_at' => $now,
        ]);

        // Note: Perimeter appliances (vpn_gateway, firewall_network) retain their IT-attested status & timestamp.

        AuditLog::record(
            $request->user(),
            'topology.healthcheck',
            'Topology',
            "Health check live dijalankan (DB {$db['latency_ms']}ms, Storage ".($storage['writable'] ? 'OK' : 'FAIL').", Readiness {$diag['summary']['pass']}/{$diag['summary']['total']})."
        );

        return response()->json([
            'message' => "Health check live selesai (DB {$db['latency_ms']} ms • Readiness {$diag['summary']['pass']}/{$diag['summary']['total']}).",
            'components' => TopologyComponent::orderBy('sort_order')->get(),
            'diagnostics' => $diag,
        ]);
    }
}
