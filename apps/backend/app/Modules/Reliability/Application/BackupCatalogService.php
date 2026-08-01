<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class BackupCatalogService
{
    public function register(
        string $path,
        string $sha256,
        int $sizeBytes,
        string $kind = 'database',
    ): object {
        if (! preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            throw new RuntimeException('Backup SHA-256 is invalid.');
        }

        $id = DB::table('backup_catalogs')->insertGetId([
            'backup_uuid' => (string) Str::uuid(),
            'kind' => $kind,
            'status' => 'completed',
            'path' => $path,
            'size_bytes' => max(0, $sizeBytes),
            'sha256' => strtolower($sha256),
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('backup_catalogs')->where('id', $id)->first();
    }

    public function recordDrill(
        int $backupId,
        bool $passed,
        string $evidence,
    ): object {
        $backup = DB::table('backup_catalogs')->where('id', $backupId)->first();

        if (! $backup) {
            throw new RuntimeException('Backup catalog entry was not found.');
        }

        $id = DB::table('restore_drills')->insertGetId([
            'backup_catalog_id' => $backupId,
            'drill_uuid' => (string) Str::uuid(),
            'status' => $passed ? 'passed' : 'failed',
            'evidence' => $evidence,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($passed) {
            DB::table('backup_catalogs')
                ->where('id', $backupId)
                ->update([
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return DB::table('restore_drills')->where('id', $id)->first();
    }
}