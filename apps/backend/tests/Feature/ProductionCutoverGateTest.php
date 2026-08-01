<?php

namespace Tests\Feature;

use App\Modules\Reliability\Application\ProductionCutoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductionCutoverGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_cannot_open_traffic_while_critical_checks_are_pending(): void
    {
        $run = app(ProductionCutoverService::class)->create(
            str_repeat('e', 40),
            'test'
        );

        $this->assertFalse((bool) $run->traffic_open_allowed);

        $this->expectException(\RuntimeException::class);

        app(ProductionCutoverService::class)->openTraffic($run->id);
    }
}