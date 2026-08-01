<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 32)->default('active')->index();
            $table->dateTime('last_login_at')->nullable();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default('active')->index();
            $table->string('default_currency', 3)->default('USD');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->dateTime('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'uq_membership_tenant_user');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('scope', 16)->default('tenant')->index();
            $table->boolean('system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug'], 'uq_role_tenant_slug');
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module', 64)->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('membership_role', function (Blueprint $table): void {
            $table->foreignId('tenant_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['tenant_membership_id', 'role_id'], 'pk_membership_role');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 96);
            $table->string('key', 160);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'scope', 'key'], 'uq_idempotency_scope_key');
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id', 64)->unique();
            $table->string('event_type', 160)->index();
            $table->string('aggregate_type', 96);
            $table->string('aggregate_id', 96);
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
            $table->dateTime('available_at')->useCurrent();
            $table->dateTime('published_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at'], 'idx_outbox_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('membership_role');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenants');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['status', 'last_login_at']);
        });
    }
};