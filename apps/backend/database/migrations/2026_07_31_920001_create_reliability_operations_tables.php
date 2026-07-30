<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->default('database')->index();
            $table->string('status', 24)->default('running')->index();
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path', 1000)->nullable();
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('encrypted')->default(true);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('restore_drills', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('system_backup_id')->constrained('system_backups')->restrictOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('running')->index();
            $table->boolean('checksum_verified')->default(false);
            $table->boolean('decryption_verified')->default(false);
            $table->boolean('archive_verified')->default(false);
            $table->unsignedBigInteger('inspected_bytes')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('release_checks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->index();
            $table->string('environment', 32);
            $table->string('version', 80)->nullable();
            $table->json('checks');
            $table->json('summary');
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('runtime_heartbeats', function (Blueprint $table): void {
            $table->string('component', 80)->primary();
            $table->string('status', 24)->default('healthy');
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_heartbeats');
        Schema::dropIfExists('release_checks');
        Schema::dropIfExists('restore_drills');
        Schema::dropIfExists('system_backups');
    }
};
