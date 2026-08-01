<?php

namespace Tests\Feature;

use App\Modules\Automation\Application\AutomationRuleEngine;
use App\Modules\Plugins\Application\PluginRegistry;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PluginAutomationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_entitlement_gate_and_safe_notification_automation(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Automation Tenant',
            'slug' => 'automation-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $definitionId = DB::table('entitlement_definitions')->insertGetId([
            'key' => 'plugins.demo',
            'name' => 'Demo Plugin',
            'module' => 'plugins',
            'value_type' => 'boolean',
            'default_value' => json_encode(false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_entitlements')->insert([
            'tenant_id' => $tenant->id,
            'entitlement_definition_id' => $definitionId,
            'value' => json_encode(true),
            'source' => 'override',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manifestId = DB::table('plugin_manifests')->insertGetId([
            'plugin_key' => 'demo.safe.plugin',
            'name' => 'Demo Safe Plugin',
            'version' => '1.0.0',
            'vendor' => 'AGCP',
            'capabilities' => json_encode(['notifications']),
            'required_entitlements' => json_encode(['plugins.demo']),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plugin = app(PluginRegistry::class)->enable(
            $tenant->id,
            $manifestId,
            ['mode' => 'test']
        );

        $this->assertSame('enabled', $plugin->status);

        DB::table('notification_channels')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'In App',
            'channel_type' => 'in_app',
            'status' => 'enabled',
            'external_delivery_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('automation_rules')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Order Notification',
            'event_type' => 'commerce.order.completed.v1',
            'action_type' => 'notify',
            'action_config' => json_encode([
                'channel' => 'in_app',
                'subject' => 'Order completed',
                'body' => 'Your order is complete.',
            ]),
            'priority' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(AutomationRuleEngine::class)->dispatch(
            $tenant->id,
            'commerce.order.completed.v1',
            ['user_id' => null]
        );

        $this->assertSame('completed', $result[0]['status']);

        $this->assertDatabaseHas('notification_channel_deliveries', [
            'tenant_id' => $tenant->id,
            'channel_type' => 'in_app',
            'status' => 'queued',
        ]);
    }
}