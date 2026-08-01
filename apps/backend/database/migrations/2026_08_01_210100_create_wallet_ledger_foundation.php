<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('type', 24)->index();
            $table->string('currency', 3);
            $table->string('status', 24)->default('active')->index();
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code', 'currency'], 'uq_ledger_account_code');
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('currency', 3);
            $table->string('status', 24)->default('active')->index();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('held_balance_minor')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'currency'], 'uq_wallet_owner_currency');
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_uuid', 64)->unique();
            $table->string('idempotency_key', 160)->nullable();
            $table->string('reference_type', 96)->nullable();
            $table->string('reference_id', 96)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 24)->default('posted')->index();
            $table->dateTime('posted_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'uq_ledger_idempotency');
            $table->index(['reference_type', 'reference_id'], 'idx_ledger_reference');
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'created_at'], 'idx_entry_account_date');
        });

        Schema::create('wallet_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('hold_uuid', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 96);
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('deposit_uuid', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('ledger_accounts');
    }
};