<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Infrastructure\Models\Tenant;

uses(RefreshDatabase::class);

it('exposes the versioned tenant-aware platform endpoint', function (): void {
    Tenant::query()->create([
        'name' => 'Araabi Global',
        'slug' => 'araabi-global',
        'status' => 'active',
        'default_currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    $response = $this->getJson('/api/v1/platform');

    $response->assertSuccessful()
        ->assertJsonPath('data.api_version', 'v1')
        ->assertHeader('X-Request-ID');
});
