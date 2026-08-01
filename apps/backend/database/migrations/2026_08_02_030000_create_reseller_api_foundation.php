<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 32)->unique();
            $table->string('name');
            $table->char('secret_hash', 64);
            $table->json('abilities')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('rate_limit_per_minute')->default(120);
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('reseller_api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_api_client_id')
                ->constrained('reseller_api_clients')
                ->cascadeOnDelete();
            $table->string('request_id', 64)->unique();
            $table->string('method', 12);
            $table->string('path', 512);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(
                ['reseller_api_client_id', 'created_at'],
                'idx_reseller_api_client_created'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_api_request_logs');
        Schema::dropIfExists('reseller_api_clients');
    }
};