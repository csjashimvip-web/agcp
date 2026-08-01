<?php

namespace Tests\Feature;

use App\Modules\Reliability\Application\DependencyAuditRecorder;
use App\Modules\Reliability\Application\PerformanceBaselineService;
use App\Modules\Reliability\Application\StagingAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Rc1StagingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_acceptance_passes_with_security_dependencies_and_performance_evidence(): void
    {
        app(DependencyAuditRecorder::class)->record(
            ecosystem: 'npm',
            critical: 0,
            high: 0,
            moderate: 0,
            low: 0,
            environment: 'test',
        );

        app(PerformanceBaselineService::class)->capture(
            'test',
            5
        );

        $row = app(StagingAcceptanceService::class)->run(
            str_repeat('d', 40),
            'test'
        );

        $this->assertSame('accepted', $row->status);
        $this->assertSame(0, $row->critical_failures);
    }
}