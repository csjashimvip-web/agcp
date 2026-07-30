<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_routing_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('slug', 160);
            $table->string('strategy', 40)->default('balanced')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('weights')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_default', 'status']);
        });

        Schema::create('supplier_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 100);
            $table->string('provider', 100)->index();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('max_retries')->default(3);
            $table->json('country_codes')->nullable();
            $table->longText('credentials')->nullable();
            $table->string('health_status', 24)->default('unknown')->index();
            $table->decimal('health_score', 5, 2)->default(100);
            $table->decimal('success_rate', 5, 2)->default(100);
            $table->unsignedInteger('average_latency_ms')->default(0);
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('successful_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('disabled_until')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'priority']);
        });

        Schema::create('supplier_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_account_id')->constrained('supplier_accounts')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->cascadeOnDelete();
            $table->string('supplier_service_code', 160);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->char('currency', 3)->index();
            $table->unsignedInteger('estimated_seconds')->default(60);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('max_retries')->nullable();
            $table->json('field_map')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['supplier_account_id', 'catalog_variant_id'], 'supplier_variant_unique');
            $table->index(['tenant_id', 'catalog_variant_id', 'enabled']);
        });

        Schema::create('supplier_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('supplier_account_id')->nullable()->constrained('supplier_accounts')->nullOnDelete();
            $table->foreignUuid('supplier_service_id')->nullable()->constrained('supplier_services')->nullOnDelete();
            $table->foreignUuid('routing_profile_id')->nullable()->constrained('supplier_routing_profiles')->nullOnDelete();
            $table->foreignUuid('refund_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('client_reference', 120);
            $table->string('supplier_reference', 190)->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('next_poll_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('order_item_id');
            $table->unique(['tenant_id', 'client_reference']);
            $table->index(['tenant_id', 'status', 'next_poll_at']);
        });

        Schema::create('supplier_routing_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_order_id')->constrained('supplier_orders')->cascadeOnDelete();
            $table->foreignUuid('selected_supplier_account_id')->nullable()->constrained('supplier_accounts')->nullOnDelete();
            $table->foreignUuid('selected_supplier_service_id')->nullable()->constrained('supplier_services')->nullOnDelete();
            $table->string('strategy', 40);
            $table->json('candidate_scores');
            $table->string('reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['supplier_order_id', 'created_at']);
        });

        Schema::create('supplier_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_order_id')->constrained('supplier_orders')->cascadeOnDelete();
            $table->foreignUuid('supplier_account_id')->constrained('supplier_accounts')->restrictOnDelete();
            $table->foreignUuid('supplier_service_id')->constrained('supplier_services')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 32)->index();
            $table->decimal('routing_score', 8, 3)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_order_id', 'attempt_number']);
        });

        Schema::create('supplier_health_checks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_account_id')->constrained('supplier_accounts')->cascadeOnDelete();
            $table->string('status', 24);
            $table->decimal('score', 5, 2);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            $table->index(['supplier_account_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_health_checks');
        Schema::dropIfExists('supplier_attempts');
        Schema::dropIfExists('supplier_routing_decisions');
        Schema::dropIfExists('supplier_orders');
        Schema::dropIfExists('supplier_services');
        Schema::dropIfExists('supplier_accounts');
        Schema::dropIfExists('supplier_routing_profiles');
    }
};
