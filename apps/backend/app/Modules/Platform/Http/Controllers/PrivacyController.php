<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Application\PrivacyRequestService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PrivacyController
{
    public function customerIndex(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        return response()->json([
            'data' => DB::table('privacy_requests')
                ->where('tenant_id', $tenant->id())
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function customerStore(
        Request $request,
        TenantContext $tenant,
        PrivacyRequestService $privacy,
    ): JsonResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'access_export',
                    'correction_review',
                    'deletion_review',
                ]),
            ],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $row = $privacy->create(
            $tenant->id(),
            (int) $request->user()->id,
            $validated['type'],
            $validated['note'] ?? null,
        );

        return response()->json(['data' => $row], 201);
    }

    public function adminIndex(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => DB::table('privacy_requests')
                ->join('users', 'users.id', '=', 'privacy_requests.user_id')
                ->where('privacy_requests.tenant_id', $tenant->id())
                ->orderByDesc('privacy_requests.id')
                ->limit(300)
                ->get([
                    'privacy_requests.*',
                    'users.name as user_name',
                    'users.email as user_email',
                ]),
        ]);
    }

    public function adminReview(
        Request $request,
        int $requestId,
        TenantContext $tenant,
        PrivacyRequestService $privacy,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'under_review',
                    'approved',
                    'rejected',
                    'completed',
                ]),
            ],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $row = $privacy->review(
            $tenant->id(),
            $requestId,
            $validated['status'],
            $validated['note'] ?? null,
        );

        return response()->json(['data' => $row]);
    }
}