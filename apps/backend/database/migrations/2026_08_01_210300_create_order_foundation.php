<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_uuid', 64)->unique();
            $table->string('order_number', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('surcharge_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->foreignId('ledger_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number'], 'uq_order_tenant_number');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 96);
            $table->string('name');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->json('service_input')->nullable();
            $table->string('fulfillment_status', 32)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('order_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'idx_order_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};