<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_provider_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 60)->index();
            $table->string('code', 100);
            $table->string('name', 160);
            $table->string('mode', 20)->default('sandbox')->index();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('currencies');
            $table->unsignedBigInteger('minimum_amount_minor')->default(100);
            $table->unsignedBigInteger('maximum_amount_minor')->default(100000000);
            $table->unsignedInteger('fee_basis_points')->default(0);
            $table->unsignedBigInteger('fee_fixed_minor')->default(0);
            $table->text('credentials')->nullable();
            $table->text('webhook_secret');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'provider', 'status']);
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('provider_account_id')->constrained('payment_provider_accounts')->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('provider_payment_id', 190)->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->index();
            $table->string('status', 32)->default('created')->index();
            $table->foreignUuid('fee_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('idempotency_key_hash', 64);
            $table->string('request_hash', 64);
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'idempotency_key_hash'], 'payment_intent_idempotency_unique');
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_intent_id')->constrained('payment_intents')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24)->default('initiated')->index();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_intent_id', 'attempt_number']);
        });

        Schema::create('payment_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_account_id')->constrained('payment_provider_accounts')->restrictOnDelete();
            $table->foreignUuid('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('external_event_id', 190);
            $table->string('event_type', 100)->index();
            $table->string('status', 24)->default('received')->index();
            $table->string('signature_hash', 64)->nullable();
            $table->string('payload_hash', 64);
            $table->text('payload');
            $table->text('headers')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['provider_account_id', 'external_event_id'], 'payment_webhook_event_unique');
            $table->index(['tenant_id', 'status', 'received_at']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignUuid('wallet_hold_id')->nullable()->constrained('wallet_holds')->nullOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('provider_refund_id', 190)->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('requested')->index();
            $table->string('reason', 500);
            $table->string('idempotency_key_hash', 64);
            $table->string('request_hash', 64);
            $table->json('metadata')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'requested_by', 'idempotency_key_hash'], 'payment_refund_idempotency_unique');
        });

        Schema::create('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_account_id')->nullable()->constrained('payment_provider_accounts')->nullOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('running')->index();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->unsignedInteger('resolved_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'started_at']);
        });

        Schema::create('payment_reconciliation_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reconciliation_run_id')->constrained('payment_reconciliation_runs')->cascadeOnDelete();
            $table->foreignUuid('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->string('type', 80)->index();
            $table->string('severity', 20)->default('warning')->index();
            $table->string('status', 24)->default('open')->index();
            $table->unsignedBigInteger('expected_amount_minor')->nullable();
            $table->unsignedBigInteger('actual_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('description', 500);
            $table->json('evidence')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->foreignUuid('payment_intent_id')->nullable()->unique()->after('wallet_id')->constrained('payment_intents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_intent_id');
        });
        Schema::dropIfExists('payment_reconciliation_items');
        Schema::dropIfExists('payment_reconciliation_runs');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payment_provider_accounts');
    }
};
