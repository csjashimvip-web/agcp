<?php

namespace App\Modules\Platform\Application;

use App\Modules\Reliability\Application\ReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DeploymentReadinessService
{
    public function __construct(
        private readonly ReadinessService $readiness,
    ) {
    }

    public function record(
        string $environment,
        string $gitCommit,
        ?int $userId = null,
    ): object {
        $releaseId = DB::table('deployment_releases')->insertGetId([
            'release_uuid' => (string) Str::uuid(),
            'environment' => $environment,
            'git_commit' => $gitCommit,
            'status' => 'started',
            'deployed_by_user_id' => $userId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checks = [
            'runtime_readiness' => $this->readiness->probe(null, false)['ready'],
            'migrations_table' => Schema::hasTable('migrations'),
            'failed_jobs_table' => Schema::hasTable('failed_jobs'),
            'outbox_table' => Schema::hasTable('outbox_events'),
        ];

        foreach ($checks as $key => $passed) {
            DB::table('deployment_checks')->insert([
                'deployment_release_id' => $releaseId,
                'check_key' => $key,
                'status' => $passed ? 'passed' : 'failed',
                'detail' => $passed ? 'OK' : 'Check failed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $allPassed = ! in_array(false, $checks, true);

        DB::table('deployment_releases')
            ->where('id', $releaseId)
            ->update([
                'status' => $allPassed ? 'ready' : 'blocked',
                'completed_at' => now(),
                'metadata' => json_encode(
                    ['checks' => $checks],
                    JSON_THROW_ON_ERROR
                ),
                'updated_at' => now(),
            ]);

        return DB::table('deployment_releases')
            ->where('id', $releaseId)
            ->first();
    }
}