<?php

namespace App\Modules\Fraud\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminFraudController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'rules' => DB::table('fraud_rules')
                    ->where('tenant_id', $tenant->id())
                    ->orderBy('priority')
                    ->get(),
                'assessments' => DB::table('fraud_assessments')
                    ->join('users', 'users.id', '=', 'fraud_assessments.user_id')
                    ->where('fraud_assessments.tenant_id', $tenant->id())
                    ->orderByDesc('fraud_assessments.id')
                    ->limit(200)
                    ->get([
                        'fraud_assessments.id',
                        'fraud_assessments.risk_score',
                        'fraud_assessments.decision',
                        'fraud_assessments.quote_total_minor',
                        'fraud_assessments.reasons',
                        'fraud_assessments.created_at',
                        'users.name as user_name',
                        'users.email as user_email',
                    ]),
            ],
        ]);
    }

    public function storeRule(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:96'],
            'metric' => [
                'required',
                Rule::in([
                    'order_total_minor',
                    'orders_10m',
                    'cancelled_orders_24h',
                ]),
            ],
            'threshold_value' => ['required', 'integer', 'min:0'],
            'risk_points' => ['required', 'integer', 'min:1', 'max:100'],
            'action' => ['required', Rule::in(['review', 'block'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $code = strtoupper(trim($validated['code']));

        abort_if(
            DB::table('fraud_rules')
                ->where('tenant_id', $tenant->id())
                ->where('code', $code)
                ->exists(),
            422,
            'Fraud rule code already exists.'
        );

        $id = DB::table('fraud_rules')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'code' => $code,
            'metric' => $validated['metric'],
            'threshold_value' => $validated['threshold_value'],
            'risk_points' => $validated['risk_points'],
            'action' => $validated['action'],
            'priority' => $validated['priority'] ?? 100,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'fraud.rule.created',
            'fraud_rule',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('fraud_rules')->where('id', $id)->first(),
        ], 201);
    }
}