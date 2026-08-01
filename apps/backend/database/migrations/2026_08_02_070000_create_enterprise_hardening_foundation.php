<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_level_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric_key', 96);
            $table->unsignedInteger('target_bps')->default(9990);
            $table->unsignedInteger('window_minutes')->default(60);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key', 'status']);
        });

        Schema::create('reliability_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('snapshot_uuid', 64)->unique();
            $table->boolean('database_ok');
            $table->boolean('cache_ok');
            $table->unsignedBigInteger('queue_backlog')->default(0);
            $table->unsignedBigInteger('failed_jobs')->default(0);
            $table->unsignedBigInteger('pending_outbox')->default(0);
            $table->unsignedBigInteger('pending_supplier_orders')->default(0);
            $table->unsignedInteger('health_bps')->default(0);
            $table->json('metadata')->nullable();
            $table->dateTime('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'captured_at']);
        });

        Schema::create('backup_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('backup_uuid', 64)->unique();
            $table->string('kind', 32)->default('database');
            $table->string('status', 24)->default('completed')->index();
            $table->text('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('restore_drills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_catalog_id')
                ->constrained('backup_catalogs')
                ->cascadeOnDelete();
            $table->string('drill_uuid', 64)->unique();
            $table->string('status', 24)->index();
            $table->text('evidence')->nullable();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('dataset', 96);
            $table->unsignedInteger('retention_days');
            $table->string('mode', 24)->default('review')->index();
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'dataset']);
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_uuid', 64)->unique();
            $table->string('type', 32);
            $table->string('status', 24)->default('submitted')->index();
            $table->text('request_note')->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('requested_at')->useCurrent();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('data_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->string('export_uuid', 64)->unique();
            $table->string('scope', 64)->default('tenant_portability');
            $table->string('status', 24)->default('pending')->index();
            $table->text('file_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('error')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->string('import_uuid', 64)->unique();
            $table->string('resource_type', 64);
            $table->string('source_name');
            $table->boolean('dry_run')->default(true);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('rows_total')->default(0);
            $table->unsignedBigInteger('rows_valid')->default(0);
            $table->unsignedBigInteger('rows_failed')->default(0);
            $table->json('result')->nullable();
            $table->timestamps();
        });

        Schema::create('deployment_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('release_uuid', 64)->unique();
            $table->string('environment', 64)->index();
            $table->string('git_commit', 64);
            $table->string('status', 24)->default('started')->index();
            $table->foreignId('deployed_by_user_id')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('deployment_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deployment_release_id')
                ->constrained('deployment_releases')
                ->cascadeOnDelete();
            $table->string('check_key', 96);
            $table->string('status', 24);
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->unique(
                ['deployment_release_id', 'check_key'],
                'uq_deployment_release_check'
            );
        });

        $permissions = [
            ['name' => 'Manage reliability', 'slug' => 'reliability.manage', 'module' => 'reliability'],
            ['name' => 'Manage privacy', 'slug' => 'privacy.manage', 'module' => 'platform'],
            ['name' => 'Manage data operations', 'slug' => 'data.manage', 'module' => 'platform'],
            ['name' => 'Manage deployments', 'slug' => 'deployment.manage', 'module' => 'platform'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                $permission + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $roleId = DB::table('roles')
            ->where('slug', 'platform-super-admin')
            ->value('id');

        if ($roleId) {
            $ids = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'reliability.manage',
            'privacy.manage',
            'data.manage',
            'deployment.manage',
        ];

        $ids = DB::table('permissions')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        Schema::dropIfExists('deployment_checks');
        Schema::dropIfExists('deployment_releases');
        Schema::dropIfExists('data_import_jobs');
        Schema::dropIfExists('data_export_jobs');
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('restore_drills');
        Schema::dropIfExists('backup_catalogs');
        Schema::dropIfExists('reliability_snapshots');
        Schema::dropIfExists('service_level_objectives');
    }
};