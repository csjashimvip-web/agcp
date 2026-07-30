<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'araabi-global')->firstOrFail();
        PaymentProviderAccount::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'sandbox-default'],
            [
                'provider' => 'sandbox',
                'name' => 'AGCP Sandbox Gateway',
                'mode' => 'sandbox',
                'status' => 'active',
                'priority' => 10,
                'currencies' => ['USD', 'BDT', 'EUR'],
                'minimum_amount_minor' => 100,
                'maximum_amount_minor' => 100000000,
                'fee_basis_points' => 0,
                'fee_fixed_minor' => 0,
                'credentials' => ['environment' => 'local'],
                'webhook_secret' => (string) config('payments.sandbox_webhook_secret'),
                'metadata' => ['simulation_enabled' => true, 'production_ready' => false],
            ],
        );
    }
}
