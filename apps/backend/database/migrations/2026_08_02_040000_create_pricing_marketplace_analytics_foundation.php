<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 96);
            $table->unsignedInteger('default_discount_bps')->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('reseller_tier_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_tier_id')
                ->constrained('reseller_tiers')
                ->cascadeOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('reseller_tier_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reseller_tier_id')
                ->constrained('reseller_tiers')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fixed_price_minor')->nullable();
            $table->unsignedInteger('discount_bps')->nullable();
            $table->timestamps();

            $table->unique(['reseller_tier_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('type', 24);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('min_subtotal_minor')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount_minor');
            $table->dateTime('redeemed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('rate_bps');
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('marketplace_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_seller_id')
                ->constrained('marketplace_sellers')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seller_commission_bps')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id']);
        });

        Schema::create('commission_accruals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_seller_id')
                ->constrained('marketplace_sellers')
                ->cascadeOnDelete();
            $table->foreignId('beneficiary_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->unsignedInteger('rate_bps');
            $table->string('status', 24)->default('accrued')->index();
            $table->dateTime('accrued_at')->useCurrent();
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_item_id', 'marketplace_seller_id'],
                'uq_commission_order_item_seller'
            );
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();
            $table->json('pricing_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'tax_minor',
                'coupon_id',
                'pricing_snapshot',
            ]);
        });

        Schema::dropIfExists('commission_accruals');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplace_sellers');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('reseller_tier_prices');
        Schema::dropIfExists('reseller_tier_memberships');
        Schema::dropIfExists('reseller_tiers');
    }
};