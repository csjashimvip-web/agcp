<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('granularity', 24)->default('daily');
            $table->char('currency', 3)->index();
            $table->unsignedBigInteger('orders_count')->default(0);
            $table->unsignedBigInteger('completed_orders_count')->default(0);
            $table->unsignedBigInteger('gross_revenue_minor')->default(0);
            $table->unsignedBigInteger('net_revenue_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->unsignedBigInteger('discounts_minor')->default(0);
            $table->unsignedBigInteger('surcharges_minor')->default(0);
            $table->unsignedBigInteger('unique_customers')->default(0);
            $table->unsignedBigInteger('average_order_value_minor')->default(0);
            $table->unsignedBigInteger('risk_review_count')->default(0);
            $table->decimal('supplier_success_rate', 5, 2)->default(0);
            $table->json('metrics')->nullable();
            $table->timestamp('calculated_at')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'period_start', 'period_end', 'granularity', 'currency'], 'analytics_snapshot_period_unique');
            $table->index(['tenant_id', 'calculated_at']);
        });

        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('segment_code', 40)->index();
            $table->unsignedInteger('score')->default(0)->index();
            $table->unsignedInteger('recency_days')->nullable();
            $table->unsignedInteger('frequency_orders')->default(0);
            $table->unsignedBigInteger('monetary_minor')->default(0);
            $table->unsignedBigInteger('average_order_minor')->default(0);
            $table->timestamp('last_order_at')->nullable()->index();
            $table->json('signals')->nullable();
            $table->timestamp('calculated_at')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'segment_code', 'score']);
        });

        Schema::create('sales_forecasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('currency', 3)->index();
            $table->unsignedInteger('horizon_days');
            $table->string('method', 80);
            $table->date('basis_start');
            $table->date('basis_end');
            $table->unsignedBigInteger('predicted_revenue_minor')->default(0);
            $table->decimal('confidence', 5, 2)->default(0);
            $table->decimal('trend_percent', 8, 2)->default(0);
            $table->json('points');
            $table->timestamp('generated_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'generated_at']);
        });

        Schema::create('supplier_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->nullable()->constrained('catalog_variants')->cascadeOnDelete();
            $table->foreignUuid('recommended_supplier_account_id')->nullable()->constrained('supplier_accounts')->nullOnDelete();
            $table->string('strategy', 80);
            $table->decimal('score', 8, 3)->default(0);
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('candidates');
            $table->string('reason', 1000)->nullable();
            $table->timestamp('generated_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'catalog_variant_id', 'generated_at'], 'supplier_recommendation_lookup');
        });

        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('type', 40)->index();
            $table->string('severity', 24)->index();
            $table->string('title', 190);
            $table->text('summary');
            $table->json('recommendations')->nullable();
            $table->json('evidence')->nullable();
            $table->string('provider_key', 80)->default('deterministic');
            $table->string('model_version', 80)->default('agcp-explainable-v1');
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('generated_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'fingerprint']);
            $table->index(['tenant_id', 'status', 'severity']);
        });

        Schema::create('ai_model_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('run_type', 80)->index();
            $table->string('provider_key', 80)->default('deterministic');
            $table->string('model_version', 80)->default('agcp-explainable-v1');
            $table->string('status', 24)->default('pending')->index();
            $table->date('input_window_start')->nullable();
            $table->date('input_window_end')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->unsignedBigInteger('records_processed')->default(0);
            $table->json('metrics')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'run_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_runs');
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('supplier_recommendations');
        Schema::dropIfExists('sales_forecasts');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('analytics_snapshots');
    }
};
