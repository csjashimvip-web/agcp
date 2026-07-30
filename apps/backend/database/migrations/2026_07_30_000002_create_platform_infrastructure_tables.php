<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('event_name',190)->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('available_at')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('processed_events', function (Blueprint $table): void {
            $table->uuid('event_id');
            $table->string('consumer',190);
            $table->timestamp('processed_at');
            $table->json('metadata')->nullable();
            $table->primary(['event_id','consumer']);
        });
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('key_hash',64);
            $table->string('scope',160);
            $table->string('request_hash',64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['tenant_id','scope','key_hash'],'idempotency_scope_key_unique');
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('actor_type',120)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('action',190)->index();
            $table->string('subject_type',190)->index();
            $table->uuid('subject_id')->nullable()->index();
            $table->string('request_id',128)->nullable()->index();
            $table->string('ip_address',45)->nullable();
            $table->string('user_agent',1000)->nullable();
            $table->json('context')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('outbox_messages');
    }
};
