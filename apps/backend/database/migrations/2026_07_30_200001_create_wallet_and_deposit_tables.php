<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 160);
            $table->string('account_type', 32)->index();
            $table->string('normal_balance', 8);
            $table->string('owner_type', 120)->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->char('currency', 3)->index();
            $table->bigInteger('balance_minor')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'owner_type', 'owner_id']);
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('owner_type', 120)->index();
            $table->uuid('owner_id')->index();
            $table->foreignUuid('ledger_account_id')->unique()->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('type', 32)->index();
            $table->char('currency', 3)->index();
            $table->string('status', 24)->default('active')->index();
            $table->json('limits')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'owner_type', 'owner_id', 'type', 'currency'], 'wallet_owner_type_currency_unique');
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_type', 120)->index();
            $table->string('reference_type', 120)->nullable()->index();
            $table->uuid('reference_id')->nullable()->index();
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('status', 24)->default('posted')->index();
            $table->string('idempotency_key_hash', 64)->nullable()->index();
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ledger_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUuid('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('balance_after_minor');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['ledger_transaction_id', 'sequence']);
            $table->index(['ledger_account_id', 'created_at']);
        });

        Schema::create('wallet_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('reference_type', 120)->nullable()->index();
            $table->uuid('reference_id')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 24)->default('active')->index();
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deposit_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('method', 40)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('external_reference', 190)->nullable()->index();
            $table->string('idempotency_key_hash', 64)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->unique(['tenant_id', 'user_id', 'idempotency_key_hash'], 'deposit_idempotency_unique');
        });

        Schema::create('wallet_adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending')->index();
            $table->string('reason', 500);
            $table->string('idempotency_key_hash', 64)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->unique(['tenant_id', 'requested_by', 'idempotency_key_hash'], 'wallet_adjustment_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_adjustments');
        Schema::dropIfExists('deposit_requests');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('ledger_accounts');
    }
};
