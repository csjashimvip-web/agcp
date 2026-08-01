<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_seller_id')
                ->constrained('marketplace_sellers')->cascadeOnDelete();
            $table->foreignId('beneficiary_user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_id')
                ->constrained('wallets')->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->nullOnDelete();
            $table->string('settlement_uuid', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('settled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->foreignId('accrual_ledger_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->nullOnDelete();

            $table->foreignId('commission_settlement_id')
                ->nullable()
                ->constrained('commission_settlements')
                ->nullOnDelete();
        });

        Schema::create('payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_hold_id')
                ->nullable()->constrained('wallet_holds')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')
                ->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('payout_uuid', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method', 64);
            $table->string('destination_label');
            $table->longText('destination_payload')->nullable();
            $table->string('status', 32)->default('pending_review')->index();
            $table->text('review_note')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_tier_id')
                ->nullable()->constrained('reseller_tiers')->nullOnDelete();
            $table->string('name');
            $table->string('code', 96);
            $table->string('effect', 24);
            $table->string('value_type', 24);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('min_subtotal_minor')->default(0);
            $table->unsignedBigInteger('max_subtotal_minor')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('stackable')->default(true);
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('fraud_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 96);
            $table->string('metric', 64);
            $table->unsignedBigInteger('threshold_value');
            $table->unsignedInteger('risk_points')->default(25);
            $table->string('action', 24);
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('fraud_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assessment_uuid', 64)->unique();
            $table->unsignedInteger('risk_score')->default(0);
            $table->string('decision', 24)->index();
            $table->unsignedBigInteger('quote_total_minor');
            $table->string('fingerprint_hash', 64)->nullable();
            $table->json('reasons')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 64)->unique();
            $table->string('subject');
            $table->string('category', 64)->default('general');
            $table->string('priority', 24)->default('normal')->index();
            $table->string('status', 24)->default('open')->index();
            $table->dateTime('last_activity_at')->useCurrent();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')
                ->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->text('body');
            $table->timestamps();
        });

        $permissions = [
            ['name' => 'Manage API gateway', 'slug' => 'gateway.manage', 'module' => 'gateway'],
            ['name' => 'Manage pricing', 'slug' => 'pricing.manage', 'module' => 'pricing'],
            ['name' => 'Manage marketplace', 'slug' => 'marketplace.manage', 'module' => 'marketplace'],
            ['name' => 'Manage payouts', 'slug' => 'payouts.manage', 'module' => 'wallet'],
            ['name' => 'Manage fraud controls', 'slug' => 'fraud.manage', 'module' => 'fraud'],
            ['name' => 'Manage support', 'slug' => 'support.manage', 'module' => 'support'],
            ['name' => 'View analytics', 'slug' => 'analytics.view', 'module' => 'analytics'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $superAdminRoleId = DB::table('roles')
            ->where('slug', 'platform-super-admin')
            ->value('id');

        if ($superAdminRoleId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionSlugs = [
            'gateway.manage',
            'pricing.manage',
            'marketplace.manage',
            'payouts.manage',
            'fraud.manage',
            'support.manage',
            'analytics.view',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('slug', $permissionSlugs)
            ->delete();

        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('fraud_assessments');
        Schema::dropIfExists('fraud_rules');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('payout_requests');

        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->dropForeign(['commission_settlement_id']);
            $table->dropForeign(['accrual_ledger_transaction_id']);
            $table->dropColumn([
                'commission_settlement_id',
                'accrual_ledger_transaction_id',
            ]);
        });

        Schema::dropIfExists('commission_settlements');
    }
};