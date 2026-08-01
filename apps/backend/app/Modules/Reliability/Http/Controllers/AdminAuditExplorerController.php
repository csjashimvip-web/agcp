<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditExplorerController
{
    public function __invoke(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $query = DB::table('admin_audit_events')
            ->leftJoin('users', 'users.id', '=', 'admin_audit_events.user_id')
            ->where('admin_audit_events.tenant_id', $tenant->id());

        if ($action = trim((string) $request->query('action'))) {
            $query->where('admin_audit_events.action', 'like', $action.'%');
        }

        if ($resource = trim((string) $request->query('resource_type'))) {
            $query->where('admin_audit_events.resource_type', $resource);
        }

        return response()->json([
            'data' => $query
                ->orderByDesc('admin_audit_events.id')
                ->limit(300)
                ->get([
                    'admin_audit_events.id',
                    'admin_audit_events.action',
                    'admin_audit_events.resource_type',
                    'admin_audit_events.resource_id',
                    'admin_audit_events.changes',
                    'admin_audit_events.ip_address',
                    'admin_audit_events.created_at',
                    'users.name as user_name',
                    'users.email as user_email',
                ]),
        ]);
    }
}