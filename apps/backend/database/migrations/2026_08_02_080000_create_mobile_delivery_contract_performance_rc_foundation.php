<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 128);
            $table->string('platform', 24);
            $table->string('app_version', 64)->nullable();
            $table->char('push_token_hash', 64)->nullable();
            $table->longText('encrypted_push_token')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'user_id', 'device_uuid'],
                'uq_mobile_device'
            );
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('endpoint_url');
            $table->json('event_types');
            $table->char('secret_hash', 64);
            $table->longText('encrypted_secret');
            $table->boolean('external_delivery_enabled')->default(false);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_subscription_id')
                ->constrained('webhook_subscriptions')
                ->cascadeOnDelete();
            $table->string('delivery_uuid', 64)->unique();
            $table->string('event_id', 64);
            $table->string('event_type', 160);
            $table->json('payload');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('response_code')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['webhook_subscription_id', 'event_id'],
                'uq_webhook_subscription_event'
            );
        });

        Schema::create('email_provider_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 32)->default('laravel_mail');
            $table->longText('encrypted_config')->nullable();
            $table->boolean('external_delivery_enabled')->default(false);
            $table->string('status', 24)->default('disabled')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'driver', 'status']);
        });

        Schema::create('email_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_delivery_id')
                ->nullable()
                ->constrained('notification_channel_deliveries')
                ->nullOnDelete();
            $table->foreignId('email_provider_config_id')
                ->nullable()
                ->constrained('email_provider_configs')
                ->nullOnDelete();
            $table->string('attempt_uuid', 64)->unique();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->string('status', 32)->index();
            $table->text('last_error')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_contract_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('contract_key', 160);
            $table->string('version', 32);
            $table->string('method', 12);
            $table->string('path', 255);
            $table->char('schema_hash', 64);
            $table->json('schema');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->unique(
                ['contract_key', 'version'],
                'uq_api_contract_version'
            );
        });

        Schema::create('performance_baselines', function (Blueprint $table): void {
            $table->id();
            $table->string('baseline_uuid', 64)->unique();
            $table->string('environment', 64)->index();
            $table->string('probe', 96);
            $table->unsignedInteger('sample_count')->default(1);
            $table->unsignedInteger('p50_ms')->default(0);
            $table->unsignedInteger('p95_ms')->default(0);
            $table->unsignedInteger('p99_ms')->default(0);
            $table->unsignedInteger('max_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->dateTime('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['environment', 'probe', 'captured_at']);
        });

        Schema::create('release_candidate_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('audit_uuid', 64)->unique();
            $table->string('environment', 64)->index();
            $table->string('git_commit', 64);
            $table->string('status', 24)->index();
            $table->unsignedInteger('critical_findings')->default(0);
            $table->unsignedInteger('warning_findings')->default(0);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('release_candidate_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_candidate_audit_id')
                ->constrained('release_candidate_audits')
                ->cascadeOnDelete();
            $table->string('check_key', 128);
            $table->string('severity', 24);
            $table->string('status', 24);
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->unique(
                ['release_candidate_audit_id', 'check_key'],
                'uq_rc_audit_check'
            );
        });

        $permissions = [
            ['name' => 'Manage mobile platform', 'slug' => 'mobile.manage', 'module' => 'mobile'],
            ['name' => 'Manage webhooks', 'slug' => 'webhooks.manage', 'module' => 'gateway'],
            ['name' => 'Manage email providers', 'slug' => 'email.manage', 'module' => 'notifications'],
            ['name' => 'Manage API contracts', 'slug' => 'contracts.manage', 'module' => 'gateway'],
            ['name' => 'Manage performance baselines', 'slug' => 'performance.manage', 'module' => 'reliability'],
            ['name' => 'Run release candidate audit', 'slug' => 'release.audit', 'module' => 'reliability'],
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
            'mobile.manage',
            'webhooks.manage',
            'email.manage',
            'contracts.manage',
            'performance.manage',
            'release.audit',
        ];

        $ids = DB::table('permissions')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')
            ->whereIn('slug', $slugs)
            ->delete();

        Schema::dropIfExists('release_candidate_findings');
        Schema::dropIfExists('release_candidate_audits');
        Schema::dropIfExists('performance_baselines');
        Schema::dropIfExists('api_contract_snapshots');
        Schema::dropIfExists('email_delivery_attempts');
        Schema::dropIfExists('email_provider_configs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('mobile_devices');
    }
};