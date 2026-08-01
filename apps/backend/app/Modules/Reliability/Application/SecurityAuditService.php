<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SecurityAuditService
{
    public function run(
        string $environment,
        string $gitCommit,
    ): object {
        $runId = DB::table('security_audit_runs')->insertGetId([
            'audit_uuid' => (string) Str::uuid(),
            'environment' => $environment,
            'git_commit' => $gitCommit,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $production = $environment === 'production';

        $checks = [
            $this->check(
                'app.key',
                'critical',
                filled(config('app.key')),
                'Application encryption key is configured.'
            ),
            $this->check(
                'app.debug',
                'critical',
                ! $production || config('app.debug') === false,
                'APP_DEBUG must be false in production.'
            ),
            $this->check(
                'app.url.https',
                'critical',
                ! $production
                    || str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL must use HTTPS in production.'
            ),
            $this->check(
                'session.secure',
                'warning',
                ! $production || (bool) config('session.secure'),
                'Secure session cookies should be enabled in production.'
            ),
            $this->check(
                'queue.not_sync',
                'critical',
                ! $production
                    || (string) config('queue.default') !== 'sync',
                'Production queue connection must not be sync.'
            ),
            $this->check(
                'cache.not_array',
                'warning',
                ! $production
                    || (string) config('cache.default') !== 'array',
                'Production cache should use a persistent shared backend.'
            ),
            $this->check(
                'license.hashes',
                'critical',
                ! Schema::hasTable('license_keys')
                    || DB::table('license_keys')
                        ->whereRaw('LENGTH(secret_hash) <> 64')
                        ->doesntExist(),
                'Stored license secret hashes must be SHA-256 length.'
            ),
            $this->check(
                'reseller.hashes',
                'critical',
                ! Schema::hasTable('reseller_api_clients')
                    || DB::table('reseller_api_clients')
                        ->whereRaw('LENGTH(secret_hash) <> 64')
                        ->doesntExist(),
                'Stored reseller API secret hashes must be SHA-256 length.'
            ),
            $this->check(
                'webhook.https',
                'critical',
                ! Schema::hasTable('webhook_subscriptions')
                    || DB::table('webhook_subscriptions')
                        ->where('status', 'active')
                        ->where('endpoint_url', 'not like', 'https://%')
                        ->doesntExist(),
                'Active webhook destinations must use HTTPS.'
            ),
            $this->check(
                'backup.restore_verified',
                'warning',
                ! $production
                    || (
                        Schema::hasTable('backup_catalogs')
                        && DB::table('backup_catalogs')
                            ->whereNotNull('verified_at')
                            ->exists()
                    ),
                'Production cutover should have a restore-verified backup.'
            ),
            $this->check(
                'failed_jobs',
                'warning',
                ! Schema::hasTable('failed_jobs')
                    || DB::table('failed_jobs')->count() === 0,
                'No unresolved failed jobs should remain at release time.'
            ),
        ];

        $critical = 0;
        $warnings = 0;

        foreach ($checks as $check) {
            if (! $check['passed']) {
                if ($check['severity'] === 'critical') {
                    $critical++;
                } else {
                    $warnings++;
                }
            }

            DB::table('security_audit_findings')->insert([
                'security_audit_run_id' => $runId,
                'check_key' => $check['key'],
                'severity' => $check['severity'],
                'status' => $check['passed'] ? 'passed' : 'failed',
                'detail' => $check['detail'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('security_audit_runs')
            ->where('id', $runId)
            ->update([
                'status' => $critical === 0 ? 'passed' : 'blocked',
                'critical_findings' => $critical,
                'warning_findings' => $warnings,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('security_audit_runs')
            ->where('id', $runId)
            ->first();
    }

    /**
     * @return array{key:string,severity:string,passed:bool,detail:string}
     */
    private function check(
        string $key,
        string $severity,
        bool $passed,
        string $detail,
    ): array {
        return compact('key', 'severity', 'passed', 'detail');
    }
}