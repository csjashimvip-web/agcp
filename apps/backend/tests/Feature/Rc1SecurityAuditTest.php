<?php

namespace Tests\Feature;

use App\Modules\Reliability\Application\SecurityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Rc1SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_production_security_audit_records_findings(): void
    {
        $audit = app(SecurityAuditService::class)->run(
            'test',
            str_repeat('c', 40)
        );

        $this->assertSame('passed', $audit->status);
        $this->assertSame(0, $audit->critical_findings);

        $this->assertDatabaseHas('security_audit_findings', [
            'security_audit_run_id' => $audit->id,
            'check_key' => 'app.key',
            'status' => 'passed',
        ]);
    }
}