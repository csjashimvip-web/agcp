<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'sort_order']);
        });

        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->string('type', 32)->index();
            $table->string('name', 190);
            $table->string('slug', 210);
            $table->string('sku', 120)->nullable();
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('fulfillment_mode', 40)->default('manual')->index();
            $table->boolean('inventory_tracking')->default(false);
            $table->boolean('allow_backorder')->default(false);
            $table->json('service_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'status', 'type']);
        });

        Schema::create('catalog_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('sku', 140);
            $table->json('attributes')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['catalog_item_id', 'sku']);
            $table->index(['catalog_item_id', 'status']);
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('slug', 160);
            $table->char('currency', 3)->index();
            $table->string('customer_segment', 80)->nullable()->index();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug', 'currency']);
        });

        Schema::create('catalog_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('compare_at_minor')->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['price_list_id', 'catalog_variant_id', 'min_quantity'], 'catalog_price_tier_unique');
        });

        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('code', 80);
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('inventory_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->cascadeOnDelete();
            $table->bigInteger('on_hand')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            $table->unsignedBigInteger('safety_stock')->default(0);
            $table->timestamps();
            $table->unique(['inventory_location_id', 'catalog_variant_id']);
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('currency', 3)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->cascadeOnDelete();
            $table->foreignUuid('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->index(['cart_id', 'catalog_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 32)->default('pending')->index();
            $table->string('payment_status', 32)->default('pending')->index();
            $table->string('fulfillment_status', 32)->default('unfulfilled')->index();
            $table->char('currency', 3)->index();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('idempotency_key_hash', 64)->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('canceled_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'user_id', 'idempotency_key_hash'], 'order_idempotency_unique');
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->string('item_name', 190);
            $table->string('variant_name', 160)->nullable();
            $table->string('sku', 140);
            $table->string('item_type', 32);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('status', 32)->default('pending')->index();
            $table->json('configuration')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('inventory_level_id')->constrained('inventory_levels')->restrictOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_id', 'status']);
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('catalog_prices');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('catalog_variants');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('catalog_categories');
    }
};
