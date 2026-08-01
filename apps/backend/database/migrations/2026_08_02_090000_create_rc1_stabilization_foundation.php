<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('audit_uuid', 64)->unique();
            $table->string('environment', 64)->index();
            $table->string('git_commit', 64);
            $table->string('status', 24)->index();
            $table->unsignedInteger('critical_findings')->default(0);
            $table->unsignedInteger('warning_findings')->default(0);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_audit_run_id')
                ->constrained('security_audit_runs')
                ->cascadeOnDelete();
            $table->string('check_key', 128);
            $table->string('severity', 24);
            $table->string('status', 24);
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->unique(
                ['security_audit_run_id', 'check_key'],
                'uq_security_audit_check'
            );
        });

        Schema::create('dependency_audit_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_uuid', 64)->unique();
            $table->string('ecosystem', 32);
            $table->string('environment', 64)->default('local');
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('high_count')->default(0);
            $table->unsignedInteger('moderate_count')->default(0);
            $table->unsignedInteger('low_count')->default(0);
            $table->string('status', 24)->index();
            $table->char('report_sha256', 64)->nullable();
            $table->text('report_path')->nullable();
            $table->dateTime('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['ecosystem', 'captured_at']);
        });

        Schema::create('staging_acceptance_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_uuid', 64)->unique();
            $table->string('environment', 64)->default('staging')->index();
            $table->string('git_commit', 64);
            $table->string('status', 24)->index();
            $table->unsignedInteger('critical_failures')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staging_acceptance_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staging_acceptance_run_id')
                ->constrained('staging_acceptance_runs')
                ->cascadeOnDelete();
            $table->string('check_key', 128);
            $table->string('severity', 24);
            $table->string('status', 24);
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->unique(
                ['staging_acceptance_run_id', 'check_key'],
                'uq_staging_acceptance_check'
            );
        });

        Schema::create('production_cutover_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_uuid', 64)->unique();
            $table->string('environment', 64)->default('production')->index();
            $table->string('git_commit', 64);
            $table->string('status', 32)->default('preparing')->index();
            $table->boolean('traffic_open_allowed')->default(false);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('production_cutover_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_cutover_run_id')
                ->constrained('production_cutover_runs')
                ->cascadeOnDelete();
            $table->string('check_key', 128);
            $table->string('category', 64);
            $table->string('severity', 24);
            $table->boolean('manual')->default(false);
            $table->string('status', 24)->default('pending')->index();
            $table->text('evidence')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['production_cutover_run_id', 'check_key'],
                'uq_production_cutover_check'
            );
        });

        $permissions = [
            ['name' => 'Run security audits', 'slug' => 'security.audit', 'module' => 'reliability'],
            ['name' => 'Run staging acceptance', 'slug' => 'staging.acceptance', 'module' => 'reliability'],
            ['name' => 'Manage production cutover', 'slug' => 'cutover.manage', 'module' => 'reliability'],
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
            'security.audit',
            'staging.acceptance',
            'cutover.manage',
        ];

        $ids = DB::table('permissions')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        Schema::dropIfExists('production_cutover_checks');
        Schema::dropIfExists('production_cutover_runs');
        Schema::dropIfExists('staging_acceptance_checks');
        Schema::dropIfExists('staging_acceptance_runs');
        Schema::dropIfExists('dependency_audit_snapshots');
        Schema::dropIfExists('security_audit_findings');
        Schema::dropIfExists('security_audit_runs');
    }
};