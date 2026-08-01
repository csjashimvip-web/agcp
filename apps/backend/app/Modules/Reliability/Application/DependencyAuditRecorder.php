<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DependencyAuditRecorder
{
    public function record(
        string $ecosystem,
        int $critical,
        int $high,
        int $moderate,
        int $low,
        ?string $reportPath = null,
        ?string $reportSha256 = null,
        string $environment = 'local',
    ): object {
        $status = ($critical + $high) === 0
            ? 'passed'
            : 'blocked';

        $id = DB::table('dependency_audit_snapshots')->insertGetId([
            'snapshot_uuid' => (string) Str::uuid(),
            'ecosystem' => $ecosystem,
            'environment' => $environment,
            'critical_count' => max(0, $critical),
            'high_count' => max(0, $high),
            'moderate_count' => max(0, $moderate),
            'low_count' => max(0, $low),
            'status' => $status,
            'report_sha256' => $reportSha256,
            'report_path' => $reportPath,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('dependency_audit_snapshots')
            ->where('id', $id)
            ->first();
    }
}