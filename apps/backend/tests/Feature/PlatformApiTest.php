<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PlatformApiTest extends TestCase
{
    public function test_versioned_platform_endpoint_exposes_master_architecture_metadata(): void
    {
        $response = $this->getJson('/api/v1/platform');

        $response
            ->assertOk()
            ->assertJsonPath('data.version', '2026-2027-master')
            ->assertJsonPath('data.architecture', 'modular-monolith-event-driven');
    }
}