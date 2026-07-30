<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_tax_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('legal_name', 190);
            $table->string('tax_registration_number', 120)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('region_code', 80)->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->unsignedBigInteger('next_invoice_sequence')->default(1);
            $table->string('default_tax_behavior', 24)->default('inclusive');
            $table->json('address')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('jurisdiction', 160)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('region_code', 80)->nullable();
            $table->unsignedInteger('rate_basis_points')->default(0);
            $table->boolean('price_inclusive')->default(true);
            $table->string('applies_to', 32)->default('all')->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('valid_from')->nullable()->index();
            $table->timestamp('valid_until')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'applies_to']);
        });

        Schema::create('customer_tax_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('legal_name', 190)->nullable();
            $table->string('tax_identifier', 120)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('region_code', 80)->nullable();
            $table->json('address')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->string('exemption_reference', 190)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 48);
            $table->string('status', 24)->default('issued')->index();
            $table->char('currency', 3)->index();
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('surcharge_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('amount_paid_minor')->default(0);
            $table->bigInteger('amount_due_minor')->default(0);
            $table->json('seller_snapshot');
            $table->json('buyer_snapshot');
            $table->json('tax_snapshot')->nullable();
            $table->string('content_hash', 64)->index();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('description', 255);
            $table->string('sku', 140)->nullable();
            $table->string('item_type', 32)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('net_minor');
            $table->unsignedInteger('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('gross_minor');
            $table->json('tax_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'sequence']);
        });

        Schema::create('invoice_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80)->index();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['invoice_id', 'created_at']);
        });

        Schema::create('data_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 60)->index();
            $table->string('format', 12)->default('csv');
            $table->string('status', 24)->default('queued')->index();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('filters')->nullable();
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path', 1000)->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('report_type', 60)->index();
            $table->string('frequency', 24)->default('monthly')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->json('recipients')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'enabled', 'next_run_at']);
        });

        Schema::create('report_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('report_schedule_id')->nullable()->constrained('report_schedules')->nullOnDelete();
            $table->foreignUuid('data_export_id')->nullable()->constrained('data_exports')->nullOnDelete();
            $table->string('report_type', 60)->index();
            $table->string('status', 24)->default('running')->index();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('metrics')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('invoice_events');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customer_tax_profiles');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tenant_tax_profiles');
    }
};
