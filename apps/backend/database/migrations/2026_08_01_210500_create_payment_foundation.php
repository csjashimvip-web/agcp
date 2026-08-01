<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->string('driver', 64);
            $table->string('status', 24)->default('active')->index();
            $table->json('credentials_encrypted')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_payment_provider_code');
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_provider_id')->constrained()->restrictOnDelete();
            $table->string('intent_uuid', 64)->unique();
            $table->string('idempotency_key', 160)->nullable();
            $table->string('provider_reference', 160)->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('provider_fee_minor')->default(0);
            $table->string('currency', 3);
            $table->foreignId('deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'uq_payment_intent_idem');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_event_id', 160);
            $table->string('event_type', 128)->index();
            $table->json('payload');
            $table->dateTime('received_at')->useCurrent();
            $table->dateTime('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->unique(['payment_provider_id', 'provider_event_id'], 'uq_payment_provider_event');
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('refund_uuid', 64)->unique();
            $table->string('provider_reference', 160)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payment_providers');
    }
};