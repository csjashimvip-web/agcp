<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name',160);
            $table->string('slug',120)->unique();
            $table->string('status',32)->default('active')->index();
            $table->string('default_currency',3)->default('USD');
            $table->string('timezone',64)->default('UTC');
            $table->json('settings')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain',253)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_domains'); Schema::dropIfExists('tenants'); }
};
