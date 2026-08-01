<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_event_id')
                ->constrained('outbox_events')
                ->cascadeOnDelete();
            $table->string('event_id', 64);
            $table->string('event_type', 160)->index();
            $table->string('transport', 32)->default('internal');
            $table->string('status', 24)->default('published')->index();
            $table->dateTime('published_at')->useCurrent();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique('outbox_event_id');
            $table->unique(['event_id', 'transport'], 'uq_event_publication_transport');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id', 64)->index();
            $table->string('channel', 24)->default('in_app');
            $table->string('template', 128);
            $table->string('status', 24)->default('delivered')->index();
            $table->string('title');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['event_id', 'channel', 'user_id'],
                'uq_notification_event_channel_user'
            );
        });

        Schema::create('financial_compensations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()
                ->constrained('ledger_transactions')
                ->nullOnDelete();
            $table->string('compensation_uuid', 64)->unique();
            $table->string('type', 48)->index();
            $table->string('status', 24)->default('completed')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type'], 'uq_order_compensation_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_compensations');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('event_publications');
    }
};