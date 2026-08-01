<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminOperationsHealthController
{
    public function __invoke(TenantContext $tenant): JsonResponse
    {
        $tenantId = $tenant->id();

        $oldestOutbox = DB::table('outbox_events')
            ->where('tenant_id', $tenantId)
            ->whereNull('published_at')
            ->min('created_at');

        return response()->json([
            'data' => [
                'database' => 'ok',
                'queue_connection' => config('queue.default'),
                'cache_store' => config('cache.default'),
                'pending_outbox_events' => DB::table('outbox_events')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('published_at')
                    ->count(),
                'oldest_pending_outbox_at' => $oldestOutbox,
                'failed_queue_jobs' => DB::table('failed_jobs')->count(),
                'pending_supplier_orders' => DB::table('supplier_orders')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['queued', 'submitted', 'processing', 'pending'])
                    ->count(),
                'failed_supplier_orders' => DB::table('supplier_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'failed')
                    ->count(),
                'unread_notifications' => DB::table('notification_deliveries')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('read_at')
                    ->count(),
                'financial_compensations' => DB::table('financial_compensations')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}