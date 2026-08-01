<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\DeploymentReadinessService;
use App\Modules\Reliability\Application\ReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EnterpriseReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_snapshot_and_deployment_gate_are_recorded(): void
    {
        $probe = app(ReadinessService::class)->probe(null, true);

        $this->assertTrue($probe['database_ok']);
        $this->assertTrue($probe['cache_ok']);

        $this->assertDatabaseCount('reliability_snapshots', 1);

        $release = app(DeploymentReadinessService::class)->record(
            'test',
            str_repeat('a', 40),
            null,
        );

        $this->assertSame('ready', $release->status);

        $this->assertDatabaseHas('deployment_checks', [
            'deployment_release_id' => $release->id,
            'check_key' => 'runtime_readiness',
            'status' => 'passed',
        ]);
    }
}