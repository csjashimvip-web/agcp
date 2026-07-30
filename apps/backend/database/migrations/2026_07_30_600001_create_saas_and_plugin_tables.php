<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 100)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('price_monthly_minor')->default(0);
            $table->unsignedBigInteger('price_yearly_minor')->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('features');
            $table->json('limits')->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->string('billing_cycle', 16)->default('monthly');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->string('external_reference', 190)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_branding_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('display_name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('favicon_url', 2048)->nullable();
            $table->string('primary_color', 16)->default('#2563eb');
            $table->string('secondary_color', 16)->default('#0f172a');
            $table->string('support_email', 254)->nullable();
            $table->string('support_url', 2048)->nullable();
            $table->string('locale', 10)->default('en');
            $table->text('custom_css')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('tenant_domains', function (Blueprint $table): void {
            $table->string('verification_token', 96)->nullable()->after('verified');
            $table->string('verification_method', 32)->default('manual')->after('verification_token');
            $table->string('verification_status', 32)->default('pending')->index()->after('verification_method');
            $table->string('ssl_status', 32)->default('pending')->after('verification_status');
            $table->timestamp('last_checked_at')->nullable()->after('verified_at');
        });

        DB::table('tenant_domains')->where('verified', true)->update([
            'verification_status' => 'verified',
            'ssl_status' => 'managed',
        ]);

        Schema::create('tenant_feature_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('feature_key', 160);
            $table->boolean('enabled')->nullable();
            $table->json('value')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('reason', 500)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_key']);
        });

        Schema::create('tenant_usage_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric', 160);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->unsignedBigInteger('limit_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'metric', 'period_start', 'period_end'], 'tenant_usage_period_unique');
        });

        Schema::create('plugins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 140)->unique();
            $table->string('name', 160);
            $table->string('version', 40);
            $table->string('category', 80)->index();
            $table->string('provider_type', 80)->nullable()->index();
            $table->string('provider_key', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('available')->index();
            $table->boolean('is_core')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('config_schema')->nullable();
            $table->json('requested_permissions')->nullable();
            $table->json('manifest');
            $table->string('checksum', 64);
            $table->timestamps();
        });

        Schema::create('plugin_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plugin_id')->constrained('plugins')->restrictOnDelete();
            $table->string('status', 32)->default('installed')->index();
            $table->string('installed_version', 40);
            $table->boolean('enabled')->default(false)->index();
            $table->longText('configuration')->nullable();
            $table->foreignUuid('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'plugin_id']);
        });

        Schema::create('plugin_installation_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plugin_installation_id')->constrained('plugin_installations')->cascadeOnDelete();
            $table->string('event', 80)->index();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_installation_events');
        Schema::dropIfExists('plugin_installations');
        Schema::dropIfExists('plugins');
        Schema::dropIfExists('tenant_usage_counters');
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::table('tenant_domains', function (Blueprint $table): void {
            $table->dropColumn(['verification_token', 'verification_method', 'verification_status', 'ssl_status', 'last_checked_at']);
        });
        Schema::dropIfExists('tenant_branding_profiles');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
