<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View system architecture', 'slug' => 'platform.architecture.view', 'module' => 'platform'],
            ['name' => 'Manage tenants', 'slug' => 'tenancy.manage', 'module' => 'tenancy'],
            ['name' => 'Manage users', 'slug' => 'identity.users.manage', 'module' => 'identity'],
            ['name' => 'Manage roles', 'slug' => 'identity.roles.manage', 'module' => 'identity'],
            ['name' => 'View wallets', 'slug' => 'wallet.view', 'module' => 'wallet'],
            ['name' => 'Manage wallet operations', 'slug' => 'wallet.manage', 'module' => 'wallet'],
            ['name' => 'Manage catalog', 'slug' => 'catalog.manage', 'module' => 'catalog'],
            ['name' => 'Manage inventory', 'slug' => 'inventory.manage', 'module' => 'inventory'],
            ['name' => 'Manage orders', 'slug' => 'orders.manage', 'module' => 'orders'],
            ['name' => 'Manage suppliers', 'slug' => 'supplier.manage', 'module' => 'supplier'],
            ['name' => 'Manage payments', 'slug' => 'payments.manage', 'module' => 'payments'],
            ['name' => 'View audit records', 'slug' => 'reliability.audit.view', 'module' => 'reliability'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $roles = [
            ['name' => 'Platform Super Admin', 'slug' => 'platform-super-admin', 'scope' => 'platform', 'system' => true],
            ['name' => 'Tenant Admin', 'slug' => 'tenant-admin', 'scope' => 'tenant', 'system' => true],
            ['name' => 'Finance Manager', 'slug' => 'finance-manager', 'scope' => 'tenant', 'system' => true],
            ['name' => 'Supplier Manager', 'slug' => 'supplier-manager', 'scope' => 'tenant', 'system' => true],
            ['name' => 'Reseller', 'slug' => 'reseller', 'scope' => 'tenant', 'system' => true],
            ['name' => 'Customer', 'slug' => 'customer', 'scope' => 'tenant', 'system' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['tenant_id' => null, 'slug' => $role['slug']],
                $role + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'platform-super-admin')->value('id');
        $allPermissionIds = DB::table('permissions')->pluck('id');

        foreach ($allPermissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRoleId,
            ]);
        }
    }
}