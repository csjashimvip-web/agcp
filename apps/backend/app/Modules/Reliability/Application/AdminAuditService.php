<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditService
{
    /**
     * @param array<string, mixed>|null $changes
     */
    public function record(
        Request $request,
        ?int $tenantId,
        string $action,
        string $resourceType,
        string|int|null $resourceId,
        ?array $changes = null,
    ): void {
        DB::table('admin_audit_events')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'changes' => $changes ? json_encode($changes, JSON_THROW_ON_ERROR) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}