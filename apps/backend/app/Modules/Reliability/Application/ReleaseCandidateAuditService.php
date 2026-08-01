<?php

namespace App\Modules\Reliability\Application;

use App\Modules\Gateway\Application\ApiContractAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ReleaseCandidateAuditService
{
    public function __construct(
        private readonly ReadinessService $readiness,
        private readonly ApiContractAuditService $contracts,
    ) {
    }

    public function run(
        string $environment,
        string $gitCommit,
    ): object {
        $auditId = DB::table('release_candidate_audits')->insertGetId([
            'audit_uuid' => (string) Str::uuid(),
            'environment' => $environment,
            'git_commit' => $gitCommit,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readiness = $this->readiness->probe(null, false);
        $contracts = $this->contracts->audit(true);

        $checks = [
            [
                'key' => 'runtime.readiness',
                'severity' => 'critical',
                'passed' => (bool) $readiness['ready'],
                'detail' => $readiness['ready']
                    ? 'Database and cache readiness passed.'
                    : 'Runtime readiness failed.',
            ],
            [
                'key' => 'api.contracts',
                'severity' => 'critical',
                'passed' => (bool) $contracts['passed'],
                'detail' => $contracts['passed']
                    ? 'Required API contracts are present.'
                    : 'Missing: '.implode(', ', $contracts['missing']),
            ],
            [
                'key' => 'failed.jobs',
                'severity' => 'warning',
                'passed' => (int) $readiness['failed_jobs'] === 0,
                'detail' => 'Failed jobs: '.$readiness['failed_jobs'],
            ],
            [
                'key' => 'backup.verified',
                'severity' => 'warning',
                'passed' => Schema::hasTable('backup_catalogs')
                    && DB::table('backup_catalogs')
                        ->whereNotNull('verified_at')
                        ->exists(),
                'detail' => 'At least one backup should have a successful restore drill.',
            ],
            [
                'key' => 'performance.baseline',
                'severity' => 'warning',
                'passed' => DB::table('performance_baselines')->exists(),
                'detail' => 'Capture a performance baseline before production release.',
            ],
            [
                'key' => 'app.debug.production',
                'severity' => 'critical',
                'passed' => $environment !== 'production'
                    || config('app.debug') === false,
                'detail' => 'APP_DEBUG must be false in production.',
            ],
            [
                'key' => 'app.key',
                'severity' => 'critical',
                'passed' => filled(config('app.key')),
                'detail' => 'Application encryption key must be configured.',
            ],
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

            DB::table('release_candidate_findings')->insert([
                'release_candidate_audit_id' => $auditId,
                'check_key' => $check['key'],
                'severity' => $check['severity'],
                'status' => $check['passed'] ? 'passed' : 'failed',
                'detail' => $check['detail'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('release_candidate_audits')
            ->where('id', $auditId)
            ->update([
                'status' => $critical === 0 ? 'candidate' : 'blocked',
                'critical_findings' => $critical,
                'warning_findings' => $warnings,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('release_candidate_audits')
            ->where('id', $auditId)
            ->first();
    }
}