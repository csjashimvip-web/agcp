<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Application\AdminOrderActionService;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminOrderActionController
{
    public function cancel(
        Request $request,
        int $orderId,
        TenantContext $tenant,
        AdminOrderActionService $actions,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $order = $actions->cancel(
            $tenant->id(),
            $orderId,
            $validated['reason'],
        );

        $audit->record(
            $request,
            $tenant->id(),
            'order.cancelled',
            'order',
            $orderId,
            ['reason' => $validated['reason']],
        );

        return response()->json(['data' => $order]);
    }

    public function retry(
        Request $request,
        int $orderId,
        TenantContext $tenant,
        AdminOrderActionService $actions,
        AdminAuditService $audit,
    ): JsonResponse {
        $order = $actions->retryFulfillment(
            $tenant->id(),
            $orderId,
        );

        $audit->record(
            $request,
            $tenant->id(),
            'order.fulfillment_retried',
            'order',
            $orderId,
        );

        return response()->json(['data' => $order], 202);
    }
}