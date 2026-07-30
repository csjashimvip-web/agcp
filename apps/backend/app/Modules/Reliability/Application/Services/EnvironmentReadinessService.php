<?php

namespace Modules\Reliability\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Modules\Reliability\Infrastructure\Models\ReleaseCheck;
use Modules\Reliability\Infrastructure\Models\RuntimeHeartbeat;
use Modules\Reliability\Infrastructure\Models\SystemBackup;
use Throwable;

final class EnvironmentReadinessService
{
    /** @return array{status:string,checks:array<int,array<string,mixed>>,summary:array<string,int>} */
    public function evaluate(): array
    {
        $checks = [];
        $production = app()->environment('production');

        $this->probe($checks, 'database', true, function (): void { DB::select('SELECT 1'); }, 'MySQL connection is available.');
        $this->probe($checks, 'redis', true, function (): void { Redis::connection()->ping(); }, 'Redis connection is available.');

        $this->value($checks, 'app_key', ! str_contains((string) config('app.key'), 'CHANGE_ME') && (string) config('app.key') !== '', true, 'Application encryption key is configured.');
        $this->value($checks, 'backup_key', $this->validBackupKey(), true, 'Backup encryption key is a valid base64-encoded 32-byte key.');
        $this->value($checks, 'queue_driver', config('queue.default') === 'redis', true, 'Queue connection uses Redis.');
        $this->value($checks, 'session_driver', config('session.driver') === 'redis', true, 'Session storage uses Redis.');
        $this->value($checks, 'debug_disabled', ! config('app.debug'), $production, config('app.debug') ? 'APP_DEBUG is enabled.' : 'APP_DEBUG is disabled.');
        $this->value($checks, 'https_origin', str_starts_with((string) config('app.url'), 'https://'), $production, 'Application URL uses HTTPS.');
        $this->value($checks, 'secure_cookie', (bool) config('session.secure'), $production, 'Secure session cookies are enabled.');
        $this->value($checks, 'production_mailer', config('mail.default') !== 'log', $production, config('mail.default') === 'log' ? 'Log mailer is not suitable for production delivery.' : 'Transactional mailer is configured.');
        $this->value($checks, 'backup_storage', $this->backupStorageWritable(), true, 'Private backup storage is writable.');

        $heartbeat = RuntimeHeartbeat::query()->find('scheduler');
        $heartbeatFresh = $heartbeat?->last_seen_at?->greaterThanOrEqualTo(now()->subMinutes(max(1, (int) config('reliability.heartbeat_ttl_minutes', 3)))) ?? false;
        $this->value($checks, 'scheduler_heartbeat', $heartbeatFresh, true, $heartbeatFresh ? 'Scheduler heartbeat is current.' : 'Scheduler heartbeat is missing or stale.');

        $latestBackup = SystemBackup::query()->where('status', 'completed')->latest('completed_at')->first();
        $backupFresh = $latestBackup?->completed_at?->greaterThanOrEqualTo(now()->subHours(max(1, (int) config('reliability.backup.max_age_hours', 36)))) ?? false;
        $this->value($checks, 'recent_backup', $backupFresh, $production, $backupFresh ? 'A recent encrypted database backup is available.' : 'No recent completed database backup is available.');
        $this->value($checks, 'verified_backup', $latestBackup?->verified_at !== null, $production, $latestBackup?->verified_at ? 'Latest backup has passed an integrity drill.' : 'Latest backup has not passed an integrity drill.');

        $summary = [
            'passed' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'passed')),
            'warnings' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'warning')),
            'failed' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'failed')),
        ];

        return [
            'status' => $summary['failed'] > 0 ? 'failed' : ($summary['warnings'] > 0 ? 'warning' : 'passed'),
            'checks' => $checks,
            'summary' => $summary,
        ];
    }

    public function persist(?User $actor = null): ReleaseCheck
    {
        $started = now();
        $report = $this->evaluate();
        return ReleaseCheck::query()->create([
            'initiated_by' => $actor?->id,
            'status' => $report['status'],
            'environment' => app()->environment(),
            'version' => (string) config('app.version'),
            'checks' => $report['checks'],
            'summary' => $report['summary'],
            'started_at' => $started,
            'completed_at' => now(),
        ]);
    }

    private function validBackupKey(): bool
    {
        $decoded = base64_decode(trim((string) config('reliability.backup.encryption_key')), true);
        return is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES;
    }

    private function backupStorageWritable(): bool
    {
        try {
            $disk = Storage::disk((string) config('reliability.backup.disk', 'local'));
            $probe = trim((string) config('reliability.backup.directory', 'backups/database'), '/').'/.write-probe-'.bin2hex(random_bytes(6));
            if (! $disk->put($probe, 'ok')) {
                return false;
            }
            $disk->delete($probe);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<int,array<string,mixed>> $checks */
    private function probe(array &$checks, string $key, bool $critical, callable $probe, string $success): void
    {
        try {
            $probe();
            $checks[] = ['key' => $key, 'status' => 'passed', 'critical' => $critical, 'message' => $success];
        } catch (Throwable) {
            $checks[] = ['key' => $key, 'status' => $critical ? 'failed' : 'warning', 'critical' => $critical, 'message' => ucfirst($key).' check failed.'];
        }
    }

    /** @param array<int,array<string,mixed>> $checks */
    private function value(array &$checks, string $key, bool $passed, bool $critical, string $message): void
    {
        $checks[] = [
            'key' => $key,
            'status' => $passed ? 'passed' : ($critical ? 'failed' : 'warning'),
            'critical' => $critical,
            'message' => $message,
        ];
    }
}
