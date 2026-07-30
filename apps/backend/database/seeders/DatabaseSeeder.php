<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(['slug' => 'araabi-global'], [
            'name' => 'Araabi Global',
            'status' => 'active',
            'default_currency' => 'USD',
            'timezone' => 'UTC',
            'activated_at' => now(),
        ]);

        TenantDomain::query()->firstOrCreate(['domain' => 'localhost'], [
            'tenant_id' => $tenant->id,
            'is_primary' => true,
            'verified' => true,
            'verified_at' => now(),
        ]);

        $this->call([IdentitySeeder::class, CommerceSeeder::class, SupplierSeeder::class, RulesFraudSeeder::class, SaasPluginSeeder::class]);
    }
}
