<?php

namespace Tests\Feature;

use App\Modules\Gateway\Application\ApiContractAuditService;
use App\Modules\Reliability\Application\PerformanceBaselineService;
use App\Modules\Reliability\Application\ReleaseCandidateAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiContractReleaseCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_audit_performance_baseline_and_rc_audit_are_recorded(): void
    {
        $contracts = app(ApiContractAuditService::class)->audit(true);

        $this->assertTrue($contracts['passed']);
        $this->assertDatabaseCount(
            'api_contract_snapshots',
            $contracts['total']
        );

        $performance = app(PerformanceBaselineService::class)
            ->capture('test', 5);

        $this->assertNotEmpty($performance);

        $audit = app(ReleaseCandidateAuditService::class)->run(
            'test',
            str_repeat('b', 40)
        );

        $this->assertSame('candidate', $audit->status);

        $this->assertDatabaseHas('release_candidate_findings', [
            'release_candidate_audit_id' => $audit->id,
            'check_key' => 'api.contracts',
            'status' => 'passed',
        ]);
    }
}