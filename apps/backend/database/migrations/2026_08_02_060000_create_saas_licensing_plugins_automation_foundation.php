<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 96)->unique();
            $table->string('billing_period', 24)->default('monthly');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('limits')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')
                ->nullable()
                ->constrained('saas_plans')
                ->nullOnDelete();
            $table->string('subscription_uuid', 64)->unique();
            $table->string('mode', 24)->default('cloud');
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('renews_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('entitlement_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('name');
            $table->string('module', 96);
            $table->string('value_type', 24)->default('boolean');
            $table->json('default_value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saas_plan_id')
                ->constrained('saas_plans')
                ->cascadeOnDelete();
            $table->foreignId('entitlement_definition_id')
                ->constrained('entitlement_definitions')
                ->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['saas_plan_id', 'entitlement_definition_id'],
                'uq_plan_entitlement'
            );
        });

        Schema::create('tenant_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entitlement_definition_id')
                ->constrained('entitlement_definitions')
                ->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->string('source', 32)->default('override');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'entitlement_definition_id'],
                'uq_tenant_entitlement'
            );
        });

        Schema::create('license_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('license_uuid', 64)->unique();
            $table->string('public_id', 40)->unique();
            $table->char('secret_hash', 64);
            $table->string('edition', 64)->default('enterprise-self-hosted');
            $table->string('status', 24)->default('active')->index();
            $table->string('bound_domain')->nullable();
            $table->string('bound_server_fingerprint', 128)->nullable();
            $table->dateTime('issued_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->json('entitlement_snapshot')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('plugin_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 128)->unique();
            $table->string('name');
            $table->string('version', 64);
            $table->string('vendor', 128);
            $table->json('capabilities')->nullable();
            $table->json('required_entitlements')->nullable();
            $table->json('config_schema')->nullable();
            $table->string('status', 24)->default('approved')->index();
            $table->timestamps();
        });

        Schema::create('tenant_plugins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plugin_manifest_id')
                ->constrained('plugin_manifests')
                ->cascadeOnDelete();
            $table->string('status', 24)->default('disabled')->index();
            $table->longText('encrypted_config')->nullable();
            $table->dateTime('enabled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'plugin_manifest_id'],
                'uq_tenant_plugin'
            );
        });

        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('event_type', 160)->index();
            $table->string('action_type', 64);
            $table->json('conditions')->nullable();
            $table->json('action_config')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'status']);
        });

        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')
                ->constrained('automation_rules')
                ->cascadeOnDelete();
            $table->string('run_uuid', 64)->unique();
            $table->string('event_type', 160);
            $table->string('status', 24)->index();
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel_type', 32);
            $table->string('status', 24)->default('disabled')->index();
            $table->longText('encrypted_config')->nullable();
            $table->boolean('external_delivery_enabled')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'channel_type']);
        });

        Schema::create('notification_channel_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')
                ->nullable()
                ->constrained('notification_channels')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_uuid', 64)->unique();
            $table->string('channel_type', 32);
            $table->string('status', 24)->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });

        $permissions = [
            ['name' => 'Manage SaaS plans', 'slug' => 'saas.manage', 'module' => 'saas'],
            ['name' => 'Manage licensing', 'slug' => 'licensing.manage', 'module' => 'licensing'],
            ['name' => 'Manage plugins', 'slug' => 'plugins.manage', 'module' => 'plugins'],
            ['name' => 'Manage automation', 'slug' => 'automation.manage', 'module' => 'automation'],
            ['name' => 'Manage notification channels', 'slug' => 'notifications.channels.manage', 'module' => 'notifications'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $roleId = DB::table('roles')
            ->where('slug', 'platform-super-admin')
            ->value('id');

        if ($roleId) {
            $ids = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'saas.manage',
            'licensing.manage',
            'plugins.manage',
            'automation.manage',
            'notifications.channels.manage',
        ];

        $ids = DB::table('permissions')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        Schema::dropIfExists('notification_channel_deliveries');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('tenant_plugins');
        Schema::dropIfExists('plugin_manifests');
        Schema::dropIfExists('license_keys');
        Schema::dropIfExists('tenant_entitlements');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('entitlement_definitions');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('saas_plans');
    }
};