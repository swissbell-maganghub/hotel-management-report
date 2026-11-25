<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TopologyComponent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    private const CORE_TABLES = [
        'departments',
        'users',
        'department_user_permissions',
        'report_templates',
        'department_reports',
        'report_histories',
        'pomec_imports',
        'pomec_records',
        'topology_components',
        'audit_logs',
        'sessions',
    ];

    /**
     * Collect active database engine metadata and live table row counts.
     */
    public function inspectDatabase(): array
    {
        $connection = config('database.default', 'sqlite');
        $dbName = (string) config("database.connections.{$connection}.database", '');
        $sizeBytes = 0;

        if ($connection === 'sqlite' && $dbName !== ':memory:' && is_file($dbName)) {
            $sizeBytes = (int) @filesize($dbName);
        } elseif (in_array($connection, ['mysql', 'mariadb'], true) && $dbName !== '') {
            try {
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes FROM information_schema.TABLES WHERE table_schema = ?',
                    [$dbName]
                );
                $sizeBytes = (int) ($row->size_bytes ?? 0);
            } catch (\Throwable) {
                $sizeBytes = 0;
            }
        }

        $tableCounts = [];
        foreach (self::CORE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $tableCounts[$table] = DB::table($table)->count();
            }
        }

        return [
            'connection' => strtoupper((string) $connection),
            'database' => $connection === 'sqlite' ? basename($dbName ?: 'database.sqlite') : $dbName,
            'size_human' => $sizeBytes > 0 ? $this->formatBytes($sizeBytes) : 'In-Memory / Server',
            'total_tables' => count($tableCounts),
            'total_rows' => array_sum($tableCounts),
            'table_counts' => $tableCounts,
        ];
    }

    /**
     * List stored backup snapshots in private storage ordered newest first.
     */
    public function listBackups(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('backups')) {
            return [];
        }

        $items = [];
        foreach ($disk->files('backups') as $path) {
            $name = basename($path);
            if (! $this->isValidFilename($name)) {
                continue;
            }
            $size = (int) $disk->size($path);
            $mtime = (int) $disk->lastModified($path);

            $items[] = [
                'filename' => $name,
                'extension' => strtoupper(pathinfo($name, PATHINFO_EXTENSION)),
                'size_bytes' => $size,
                'size_human' => $this->formatBytes($size),
                'created_at_iso' => Carbon::createFromTimestamp($mtime)->timezone('Asia/Makassar')->toIso8601String(),
                'created_wita' => Carbon::createFromTimestamp($mtime)->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA',
                'mtime' => $mtime,
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return array_map(function (array $item): array {
            unset($item['mtime']);

            return $item;
        }, $items);
    }

    /**
     * Create a consistent hot database backup under storage/app/private/backups.
     */
    public function createBackup(User $actor): array
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $stamp = now()->timezone('Asia/Makassar')->format('Ymd_His');
        $connection = config('database.default', 'sqlite');
        $dbPath = (string) config("database.connections.{$connection}.database", '');

        if ($connection === 'sqlite' && $dbPath !== ':memory:' && is_file($dbPath)) {
            $filename = "backup_swissbel_{$stamp}_wita.sqlite";
            $targetPath = $disk->path("backups/{$filename}");
            DB::statement('VACUUM INTO ?', [$targetPath]);
        } elseif (in_array($connection, ['mysql', 'mariadb'], true) && $this->dumpMysqlDatabase($connection, $disk->path("backups/backup_swissbel_{$stamp}_wita.sql"))) {
            $filename = "backup_swissbel_{$stamp}_wita.sql";
        } else {
            $filename = "backup_swissbel_{$stamp}_wita.json";
            $snapshot = [
                'app' => config('app.name'),
                'generated_at_wita' => now()->timezone('Asia/Makassar')->format('Y-m-d H:i:s').' WITA',
                'generated_by' => $actor->email,
                'tables' => [],
            ];
            foreach (self::CORE_TABLES as $table) {
                if (Schema::hasTable($table) && $table !== 'sessions') {
                    $snapshot['tables'][$table] = DB::table($table)->get()->all();
                }
            }
            $disk->put("backups/{$filename}", json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        TopologyComponent::whereIn('component_key', ['backup', 'nas_backup'])->update([
            'status' => 'healthy',
            'last_checked_at' => now(),
        ]);

        $size = (int) $disk->size("backups/{$filename}");
        $humanSize = $this->formatBytes($size);

        AuditLog::record($actor, 'backup.created', 'Database', "Backup database {$filename} berhasil dibuat ({$humanSize}).");

        return [
            'filename' => $filename,
            'extension' => strtoupper(pathinfo($filename, PATHINFO_EXTENSION)),
            'size_bytes' => $size,
            'size_human' => $humanSize,
            'created_at_iso' => now()->timezone('Asia/Makassar')->toIso8601String(),
            'created_wita' => now()->timezone('Asia/Makassar')->format('d M Y, H:i:s').' WITA',
        ];
    }

    public function resolveBackupStoragePath(string $filename): ?string
    {
        if (! $this->isValidFilename($filename)) {
            return null;
        }

        $relative = "backups/{$filename}";

        return Storage::disk('local')->exists($relative) ? $relative : null;
    }

    public function deleteBackup(string $filename, User $actor): bool
    {
        $relative = $this->resolveBackupStoragePath($filename);
        if (! $relative) {
            return false;
        }

        Storage::disk('local')->delete($relative);
        AuditLog::record($actor, 'backup.deleted', 'Database', "File backup {$filename} dihapus oleh IT Admin.");

        return true;
    }

    private function dumpMysqlDatabase(string $connection, string $targetPath): bool
    {
        $binary = $this->resolveMysqldumpBinary();
        if (! $binary) {
            return false;
        }

        $config = (array) config("database.connections.{$connection}", []);
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $database = (string) ($config['database'] ?? '');

        if ($database === '') {
            return false;
        }

        $command = [
            $binary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--quick',
            '--result-file='.$targetPath,
            $database,
        ];

        $env = $password !== '' ? ['MYSQL_PWD' => $password] : [];
        $process = new Process($command, null, $env, null, 120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($targetPath) || filesize($targetPath) === 0) {
            @unlink($targetPath);

            return false;
        }

        return true;
    }

    private function resolveMysqldumpBinary(): ?string
    {
        $candidates = ['mysqldump'];
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [] as $found) {
                $candidates[] = $found;
            }
            $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        } else {
            $candidates[] = '/usr/bin/mysqldump';
            $candidates[] = '/usr/local/bin/mysqldump';
        }

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                $probe = new Process(
                    DIRECTORY_SEPARATOR === '\\' ? ['where', 'mysqldump'] : ['which', 'mysqldump'],
                    null,
                    null,
                    null,
                    5
                );
                $probe->run();
                if ($probe->isSuccessful()) {
                    $resolved = trim(strtok($probe->getOutput(), "\r\n") ?: '');
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            } elseif (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]+$/', $filename)
            && ! str_contains($filename, '..');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / 1048576, 2, ',', '.').' MB';
    }
}
