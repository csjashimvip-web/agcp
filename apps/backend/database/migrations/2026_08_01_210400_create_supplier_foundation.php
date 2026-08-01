<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->string('driver', 64);
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('max_retries')->default(2);
            $table->json('credentials_encrypted')->nullable();
            $table->json('settings')->nullable();
            $table->dateTime('last_healthcheck_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_supplier_tenant_code');
        });

        Schema::create('supplier_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('external_service_id', 128);
            $table->string('external_name')->nullable();
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3);
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id', 'external_service_id'], 'uq_supplier_service_map');
        });

        Schema::create('supplier_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('weight')->default(100);
            $table->boolean('enabled')->default(true)->index();
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_service_id'], 'uq_supplier_route');
        });

        Schema::create('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_order_uuid', 64)->unique();
            $table->string('external_order_id', 160)->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3);
            $table->text('failure_reason')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
        Schema::dropIfExists('supplier_routes');
        Schema::dropIfExists('supplier_services');
        Schema::dropIfExists('suppliers');
    }
};