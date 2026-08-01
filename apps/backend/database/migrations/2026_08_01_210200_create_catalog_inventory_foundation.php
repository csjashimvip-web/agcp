<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug'], 'uq_category_tenant_slug');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 96);
            $table->string('name');
            $table->string('slug');
            $table->string('type', 24)->default('service')->index();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->json('service_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku'], 'uq_product_tenant_sku');
            $table->unique(['tenant_id', 'slug'], 'uq_product_tenant_slug');
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('on_hand')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->bigInteger('reorder_level')->default(0);
            $table->boolean('track_inventory')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id'], 'uq_inventory_product');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('reservation_uuid', 64)->unique();
            $table->string('reference_type', 64);
            $table->string('reference_id', 96);
            $table->unsignedBigInteger('quantity');
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'idx_inventory_reservation_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};