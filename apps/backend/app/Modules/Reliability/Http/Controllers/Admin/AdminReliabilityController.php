<?php

namespace Modules\Reliability\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reliability\Application\Services\BackupVerificationService;
use Modules\Reliability\Application\Services\DatabaseBackupService;
use Modules\Reliability\Application\Services\EnvironmentReadinessService;
use Modules\Reliability\Infrastructure\Models\ReleaseCheck;
use Modules\Reliability\Infrastructure\Models\RestoreDrill;
use Modules\Reliability\Infrastructure\Models\SystemBackup;

final class AdminReliabilityController extends Controller
{
    public function index(EnvironmentReadinessService $readiness): array
    {
        return ['data' => [
            'readiness' => $readiness->evaluate(),
            'backups' => SystemBackup::query()->latest('started_at')->limit(30)->get()->map(fn (SystemBackup $backup): array => $this->backupSummary($backup))->values(),
            'restore_drills' => RestoreDrill::query()->with('backup:id,status,checksum_sha256,completed_at')->latest('started_at')->limit(30)->get(),
            'release_checks' => ReleaseCheck::query()->latest('started_at')->limit(30)->get(),
        ]];
    }

    public function backup(Request $request, DatabaseBackupService $service): array
    {
        return ['data' => $this->backupSummary($service->create($request->user()))];
    }

    public function verify(Request $request, SystemBackup $backup, BackupVerificationService $service): array
    {
        return ['data' => $service->verify($backup, $request->user())];
    }

    public function check(Request $request, EnvironmentReadinessService $service): array
    {
        return ['data' => $service->persist($request->user())];
    }

    /** @return array<string,mixed> */
    private function backupSummary(SystemBackup $backup): array
    {
        return [
            'id' => $backup->id,
            'type' => $backup->type,
            'status' => $backup->status,
            'checksum_sha256' => $backup->checksum_sha256,
            'file_size' => $backup->file_size,
            'encrypted' => $backup->encrypted,
            'started_at' => $backup->started_at,
            'completed_at' => $backup->completed_at,
            'verified_at' => $backup->verified_at,
            'expires_at' => $backup->expires_at,
            'error_message' => $backup->error_message,
        ];
    }
}
