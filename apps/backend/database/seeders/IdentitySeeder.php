<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Infrastructure\Models\Permission;
use Modules\Identity\Infrastructure\Models\Role;
use Modules\Identity\Infrastructure\Models\TenantMembership;
use Modules\Tenancy\Infrastructure\Models\Tenant;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'araabi-global')->firstOrFail();

        $definitions = [
            ['slug' => 'platform.admin.access', 'name' => 'Access platform administration', 'group' => 'Platform'],
            ['slug' => 'identity.admin.access', 'name' => 'Access tenant identity administration', 'group' => 'Identity'],
            ['slug' => 'identity.users.read', 'name' => 'Read users', 'group' => 'Identity'],
            ['slug' => 'identity.users.manage', 'name' => 'Manage users', 'group' => 'Identity'],
            ['slug' => 'identity.roles.manage', 'name' => 'Manage roles and permissions', 'group' => 'Identity'],
            ['slug' => 'identity.sessions.manage', 'name' => 'Manage account sessions', 'group' => 'Identity'],
            ['slug' => 'identity.audit.read', 'name' => 'Read identity audit events', 'group' => 'Identity'],
            ['slug' => 'profile.read', 'name' => 'Read own profile', 'group' => 'Profile'],
            ['slug' => 'profile.update', 'name' => 'Update own profile', 'group' => 'Profile'],
            ['slug' => 'api.tokens.manage', 'name' => 'Manage own API tokens', 'group' => 'API'],
            ['slug' => 'wallet.view', 'name' => 'View own wallets and ledger activity', 'group' => 'Wallet'],
            ['slug' => 'wallet.deposit.create', 'name' => 'Create own deposit requests', 'group' => 'Wallet'],
            ['slug' => 'wallet.admin.access', 'name' => 'Access wallet administration', 'group' => 'Wallet'],
            ['slug' => 'wallet.deposits.review', 'name' => 'Review customer deposit requests', 'group' => 'Wallet'],
            ['slug' => 'wallet.adjustments.request', 'name' => 'Request wallet adjustments', 'group' => 'Wallet'],
            ['slug' => 'wallet.adjustments.approve', 'name' => 'Approve wallet adjustments', 'group' => 'Wallet'],
            ['slug' => 'wallet.ledger.view', 'name' => 'View tenant ledger transactions', 'group' => 'Wallet'],
            ['slug' => 'commerce.catalog.view', 'name' => 'View published catalog', 'group' => 'Commerce'],
            ['slug' => 'commerce.cart.manage', 'name' => 'Manage own shopping cart', 'group' => 'Commerce'],
            ['slug' => 'commerce.checkout', 'name' => 'Checkout using own wallet', 'group' => 'Commerce'],
            ['slug' => 'commerce.orders.view', 'name' => 'View and manage own orders', 'group' => 'Commerce'],
            ['slug' => 'commerce.admin.access', 'name' => 'Access commerce administration', 'group' => 'Commerce'],
            ['slug' => 'commerce.catalog.manage', 'name' => 'Manage catalog and categories', 'group' => 'Commerce'],
            ['slug' => 'commerce.pricing.manage', 'name' => 'Manage price lists', 'group' => 'Commerce'],
            ['slug' => 'commerce.inventory.manage', 'name' => 'Manage inventory', 'group' => 'Commerce'],
            ['slug' => 'commerce.orders.manage', 'name' => 'Manage tenant orders', 'group' => 'Commerce'],
            ['slug' => 'supplier.admin.access', 'name' => 'Access supplier administration', 'group' => 'Suppliers'],
            ['slug' => 'supplier.accounts.manage', 'name' => 'Manage supplier accounts', 'group' => 'Suppliers'],
            ['slug' => 'supplier.services.manage', 'name' => 'Manage supplier service mappings', 'group' => 'Suppliers'],
            ['slug' => 'supplier.orders.manage', 'name' => 'Manage supplier orders and retries', 'group' => 'Suppliers'],
            ['slug' => 'supplier.health.manage', 'name' => 'Run supplier health checks', 'group' => 'Suppliers'],
        ];

        foreach ($definitions as $definition) {
            Permission::query()->updateOrCreate(['slug' => $definition['slug']], $definition);
        }

        $allPermissionIds = Permission::query()->pluck('id')->all();
        $superAdmin = Role::query()->firstOrCreate(
            ['tenant_id' => null, 'slug' => 'platform-super-admin'],
            ['name' => 'Platform Super Administrator', 'description' => 'Full platform access.', 'is_system' => true],
        );
        $superAdmin->permissions()->sync($allPermissionIds);

        $tenantAdmin = Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'tenant-admin'],
            ['name' => 'Tenant Administrator', 'description' => 'Administrative access inside the tenant.', 'is_system' => true],
        );
        $tenantAdmin->permissions()->sync(Permission::query()->whereIn('slug', [
            'identity.admin.access', 'identity.users.read', 'identity.users.manage', 'identity.roles.manage',
            'identity.sessions.manage', 'identity.audit.read', 'profile.read', 'profile.update', 'api.tokens.manage',
            'wallet.view', 'wallet.deposit.create', 'wallet.admin.access', 'wallet.deposits.review',
            'wallet.adjustments.request', 'wallet.adjustments.approve', 'wallet.ledger.view',
            'commerce.catalog.view', 'commerce.cart.manage', 'commerce.checkout', 'commerce.orders.view',
            'commerce.admin.access', 'commerce.catalog.manage', 'commerce.pricing.manage', 'commerce.inventory.manage', 'commerce.orders.manage',
            'supplier.admin.access', 'supplier.accounts.manage', 'supplier.services.manage', 'supplier.orders.manage', 'supplier.health.manage',
        ])->pluck('id')->all());

        $customer = Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'customer'],
            ['name' => 'Customer', 'description' => 'Default customer access.', 'is_system' => true],
        );
        $customer->permissions()->sync(Permission::query()->whereIn('slug', [
            'profile.read', 'profile.update', 'identity.sessions.manage', 'api.tokens.manage', 'wallet.view', 'wallet.deposit.create',
            'commerce.catalog.view', 'commerce.cart.manage', 'commerce.checkout', 'commerce.orders.view',
        ])->pluck('id')->all());

        $password = (string) env('INITIAL_ADMIN_PASSWORD', '');
        $email = Str::lower(trim((string) env('INITIAL_ADMIN_EMAIL', '')));

        if ($email === '' || $password === '' || str_starts_with($password, 'CHANGE_ME_')) {
            $this->command?->warn('Initial administrator was not created because INITIAL_ADMIN_EMAIL/PASSWORD are not configured.');
            return;
        }

        DB::transaction(function () use ($tenant, $email, $password, $superAdmin, $tenantAdmin): void {
            $admin = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => (string) env('INITIAL_ADMIN_NAME', 'AGCP Administrator'),
                    'password' => $password,
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                    'locale' => 'en',
                    'timezone' => 'UTC',
                ],
            );

            TenantMembership::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $admin->id],
                ['status' => 'active', 'joined_at' => now()],
            );

            $admin->roles()->syncWithoutDetaching([$superAdmin->id, $tenantAdmin->id]);
        });
    }
}
