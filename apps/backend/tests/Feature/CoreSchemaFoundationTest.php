<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CoreSchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_migrates_the_complete_transactional_core_foundation(): void
    {
        $tables = [
            'tenants',
            'tenant_memberships',
            'roles',
            'permissions',
            'idempotency_keys',
            'outbox_events',
            'ledger_accounts',
            'wallets',
            'ledger_transactions',
            'ledger_entries',
            'wallet_holds',
            'deposits',
            'categories',
            'products',
            'inventory_items',
            'inventory_reservations',
            'orders',
            'order_items',
            'order_status_events',
            'suppliers',
            'supplier_services',
            'supplier_routes',
            'supplier_orders',
            'payment_providers',
            'payment_intents',
            'payment_events',
            'refunds',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Missing table: {$table}"
            );
        }
    }
}