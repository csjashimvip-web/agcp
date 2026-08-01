<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('sync_uuid', 64)->unique();
            $table->string('type', 32)->default('services');
            $table->string('status', 24)->default('running')->index();
            $table->unsignedInteger('discovered')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->text('error')->nullable();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 128)->index();
            $table->string('resource_type', 96);
            $table->string('resource_id', 96)->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'resource_type', 'resource_id'],
                'idx_admin_audit_resource'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
        Schema::dropIfExists('supplier_sync_runs');
    }
};