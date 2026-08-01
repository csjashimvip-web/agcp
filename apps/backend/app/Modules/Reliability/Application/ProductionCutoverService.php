<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ProductionCutoverService
{
    /**
     * @return object
     */
    public function create(
        string $gitCommit,
        string $environment = 'production',
    ): object {
        $runId = DB::table('production_cutover_runs')->insertGetId([
            'run_uuid' => (string) Str::uuid(),
            'environment' => $environment,
            'git_commit' => $gitCommit,
            'status' => 'preparing',
            'traffic_open_allowed' => false,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $latestAcceptance = DB::table('staging_acceptance_runs')
            ->where('git_commit', $gitCommit)
            ->where('status', 'accepted')
            ->orderByDesc('id')
            ->first();

        $latestSecurity = DB::table('security_audit_runs')
            ->where('git_commit', $gitCommit)
            ->where('critical_findings', 0)
            ->orderByDesc('id')
            ->first();

        $verifiedBackup = DB::table('backup_catalogs')
            ->whereNotNull('verified_at')
            ->orderByDesc('id')
            ->first();

        $automatic = [
            [
                'key' => 'staging.accepted',
                'category' => 'release',
                'severity' => 'critical',
                'passed' => $latestAcceptance !== null,
                'evidence' => $latestAcceptance
                    ? 'Staging acceptance run #'.$latestAcceptance->id
                    : 'No accepted staging run for this commit.',
            ],
            [
                'key' => 'security.critical.zero',
                'category' => 'security',
                'severity' => 'critical',
                'passed' => $latestSecurity !== null,
                'evidence' => $latestSecurity
                    ? 'Security audit #'.$latestSecurity->id
                    : 'No zero-critical security audit for this commit.',
            ],
            [
                'key' => 'backup.restore.verified',
                'category' => 'recovery',
                'severity' => 'critical',
                'passed' => $verifiedBackup !== null,
                'evidence' => $verifiedBackup
                    ? 'Verified backup #'.$verifiedBackup->id
                    : 'No restore-verified backup.',
            ],
        ];

        foreach ($automatic as $check) {
            DB::table('production_cutover_checks')->insert([
                'production_cutover_run_id' => $runId,
                'check_key' => $check['key'],
                'category' => $check['category'],
                'severity' => $check['severity'],
                'manual' => false,
                'status' => $check['passed'] ? 'passed' : 'failed',
                'evidence' => $check['evidence'],
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $manual = [
            ['dns.tls', 'network', 'critical'],
            ['queue.scheduler.running', 'runtime', 'critical'],
            ['payment.provider.verified', 'payments', 'critical'],
            ['supplier.credentials.verified', 'supplier', 'critical'],
            ['offsite.backup.confirmed', 'recovery', 'critical'],
            ['monitoring.alerts.armed', 'observability', 'critical'],
            ['smoke.checkout.tested', 'commerce', 'critical'],
            ['smoke.refund.tested', 'finance', 'critical'],
            ['support.oncall.ready', 'operations', 'warning'],
            ['rollback.owner.assigned', 'release', 'critical'],
        ];

        foreach ($manual as [$key, $category, $severity]) {
            DB::table('production_cutover_checks')->insert([
                'production_cutover_run_id' => $runId,
                'check_key' => $key,
                'category' => $category,
                'severity' => $severity,
                'manual' => true,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->recalculate($runId);

        return DB::table('production_cutover_runs')
            ->where('id', $runId)
            ->first();
    }

    public function completeManualCheck(
        int $runId,
        string $checkKey,
        bool $passed,
        string $evidence,
    ): object {
        $check = DB::table('production_cutover_checks')
            ->where('production_cutover_run_id', $runId)
            ->where('check_key', $checkKey)
            ->where('manual', true)
            ->first();

        if (! $check) {
            throw new RuntimeException(
                'Manual cutover check was not found.'
            );
        }

        DB::table('production_cutover_checks')
            ->where('id', $check->id)
            ->update([
                'status' => $passed ? 'passed' : 'failed',
                'evidence' => $evidence,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->recalculate($runId);

        return DB::table('production_cutover_checks')
            ->where('id', $check->id)
            ->first();
    }

    public function openTraffic(int $runId): object
    {
        $this->recalculate($runId);

        $run = DB::table('production_cutover_runs')
            ->where('id', $runId)
            ->first();

        if (! $run || ! $run->traffic_open_allowed) {
            throw new RuntimeException(
                'Traffic cannot be opened until all critical cutover checks pass.'
            );
        }

        DB::table('production_cutover_runs')
            ->where('id', $runId)
            ->update([
                'status' => 'traffic_open',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('production_cutover_runs')
            ->where('id', $runId)
            ->first();
    }

    private function recalculate(int $runId): void
    {
        $blocking = DB::table('production_cutover_checks')
            ->where('production_cutover_run_id', $runId)
            ->where('severity', 'critical')
            ->where('status', '<>', 'passed')
            ->count();

        DB::table('production_cutover_runs')
            ->where('id', $runId)
            ->update([
                'traffic_open_allowed' => $blocking === 0,
                'status' => $blocking === 0
                    ? 'ready_for_traffic'
                    : 'preparing',
                'updated_at' => now(),
            ]);
    }
}