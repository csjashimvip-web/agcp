<?php

namespace App\Modules\Reliability\Application;

use App\Modules\Gateway\Application\ApiContractAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StagingAcceptanceService
{
    public function __construct(
        private readonly ReadinessService $readiness,
        private readonly ApiContractAuditService $contracts,
        private readonly SecurityAuditService $security,
    ) {
    }

    public function run(
        string $gitCommit,
        string $environment = 'staging',
    ): object {
        $runId = DB::table('staging_acceptance_runs')->insertGetId([
            'run_uuid' => (string) Str::uuid(),
            'environment' => $environment,
            'git_commit' => $gitCommit,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readiness = $this->readiness->probe(null, false);
        $contracts = $this->contracts->audit(true);
        $security = $this->security->run($environment, $gitCommit);

        $dependency = DB::table('dependency_audit_snapshots')
            ->orderByDesc('id')
            ->first();

        $performance = DB::table('performance_baselines')
            ->where('environment', $environment)
            ->orderByDesc('id')
            ->first()
            ?? DB::table('performance_baselines')
                ->orderByDesc('id')
                ->first();

        $checks = [
            [
                'key' => 'runtime.readiness',
                'severity' => 'critical',
                'passed' => (bool) $readiness['ready'],
                'detail' => 'Database/cache runtime readiness.',
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
                'key' => 'security.audit',
                'severity' => 'critical',
                'passed' => $security->status === 'passed',
                'detail' => 'Critical findings: '.$security->critical_findings,
            ],
            [
                'key' => 'dependency.audit',
                'severity' => 'critical',
                'passed' => $dependency !== null
                    && $dependency->status === 'passed',
                'detail' => $dependency
                    ? 'Latest dependency audit status: '.$dependency->status
                    : 'No dependency audit snapshot is recorded.',
            ],
            [
                'key' => 'performance.baseline',
                'severity' => 'warning',
                'passed' => $performance !== null,
                'detail' => $performance
                    ? 'Performance baseline exists.'
                    : 'No performance baseline exists.',
            ],
            [
                'key' => 'failed.jobs',
                'severity' => 'warning',
                'passed' => (int) $readiness['failed_jobs'] === 0,
                'detail' => 'Failed jobs: '.$readiness['failed_jobs'],
            ],
            [
                'key' => 'outbox.backlog',
                'severity' => 'warning',
                'passed' => (int) $readiness['pending_outbox'] < 100,
                'detail' => 'Pending outbox: '.$readiness['pending_outbox'],
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

            DB::table('staging_acceptance_checks')->insert([
                'staging_acceptance_run_id' => $runId,
                'check_key' => $check['key'],
                'severity' => $check['severity'],
                'status' => $check['passed'] ? 'passed' : 'failed',
                'detail' => $check['detail'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('staging_acceptance_runs')
            ->where('id', $runId)
            ->update([
                'status' => $critical === 0 ? 'accepted' : 'blocked',
                'critical_failures' => $critical,
                'warnings' => $warnings,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('staging_acceptance_runs')
            ->where('id', $runId)
            ->first();
    }
}