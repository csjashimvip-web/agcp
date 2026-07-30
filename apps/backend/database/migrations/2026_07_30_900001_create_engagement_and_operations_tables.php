<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('event_name', 160)->index();
            $table->string('channel', 30)->index();
            $table->string('locale', 12)->default('en');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('active')->index();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'event_name', 'channel', 'locale', 'version'], 'notification_template_version_unique');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_name', 160)->default('*');
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('web_push_enabled')->default(false);
            $table->string('quiet_hours_start', 5)->nullable();
            $table->string('quiet_hours_end', 5)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'event_name'], 'notification_preference_unique');
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_name', 160)->index();
            $table->string('title', 255);
            $table->text('body');
            $table->string('action_url', 1000)->nullable();
            $table->string('severity', 20)->default('info')->index();
            $table->string('deduplication_key', 190)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'deduplication_key'], 'user_notification_dedup_unique');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('event_name', 160)->index();
            $table->string('channel', 30)->index();
            $table->string('recipient', 255);
            $table->string('status', 24)->default('queued')->index();
            $table->string('provider', 80)->default('log');
            $table->string('provider_message_id', 190)->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'next_attempt_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('url', 1500);
            $table->text('signing_secret');
            $table->string('status', 24)->default('active')->index();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('max_attempts')->default(8);
            $table->boolean('verify_tls')->default(true);
            $table->json('custom_headers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event_name', 160);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_name']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event_id', 64);
            $table->string('event_name', 160)->index();
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('payload_hash', 64);
            $table->text('payload');
            $table->text('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_id'], 'webhook_delivery_event_unique');
            $table->index(['tenant_id', 'status', 'next_attempt_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('subject', 255);
            $table->string('category', 80)->default('general')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('open')->index();
            $table->string('source', 30)->default('portal');
            $table->string('related_type', 190)->nullable();
            $table->uuid('related_id')->nullable();
            $table->timestamp('first_response_due_at')->nullable()->index();
            $table->timestamp('resolution_due_at')->nullable()->index();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->index();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'priority', 'last_activity_at']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 30)->default('customer');
            $table->boolean('is_internal')->default(false);
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80)->index();
            $table->json('from_value')->nullable();
            $table->json('to_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('operations_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 20)->default('healthy')->index();
            $table->unsignedInteger('queue_depth')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
            $table->unsignedInteger('outbox_pending')->default(0);
            $table->unsignedInteger('outbox_failed')->default(0);
            $table->unsignedInteger('webhook_pending')->default(0);
            $table->unsignedInteger('webhook_failed')->default(0);
            $table->unsignedInteger('notification_pending')->default(0);
            $table->unsignedInteger('notification_failed')->default(0);
            $table->unsignedInteger('open_support_tickets')->default(0);
            $table->unsignedInteger('overdue_support_tickets')->default(0);
            $table->unsignedInteger('open_payment_mismatches')->default(0);
            $table->unsignedInteger('unhealthy_suppliers')->default(0);
            $table->json('checks');
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });

        Schema::create('operational_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('fingerprint', 190);
            $table->string('title', 255);
            $table->text('description');
            $table->string('severity', 20)->default('warning')->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('source', 80)->index();
            $table->json('evidence')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'fingerprint'], 'operational_incident_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_incidents');
        Schema::dropIfExists('operations_snapshots');
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
