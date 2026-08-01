<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminPricingRuleController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => DB::table('pricing_rules')
                ->where('tenant_id', $tenant->id())
                ->orderBy('priority')
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:96'],
            'effect' => ['required', Rule::in(['discount', 'surcharge'])],
            'value_type' => ['required', Rule::in(['fixed', 'percent'])],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'min_subtotal_minor' => ['nullable', 'integer', 'min:0'],
            'max_subtotal_minor' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'stackable' => ['nullable', 'boolean'],
        ]);

        if ($validated['value_type'] === 'fixed') {
            abort_if(
                $validated['amount_minor'] === null,
                422,
                'Fixed rule requires amount_minor.'
            );
        }

        if ($validated['value_type'] === 'percent') {
            abort_if(
                $validated['rate_bps'] === null,
                422,
                'Percent rule requires rate_bps.'
            );
        }

        $code = strtoupper(trim($validated['code']));

        abort_if(
            DB::table('pricing_rules')
                ->where('tenant_id', $tenant->id())
                ->where('code', $code)
                ->exists(),
            422,
            'Pricing rule code already exists.'
        );

        $id = DB::table('pricing_rules')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'code' => $code,
            'effect' => $validated['effect'],
            'value_type' => $validated['value_type'],
            'amount_minor' => $validated['amount_minor'] ?? null,
            'rate_bps' => $validated['rate_bps'] ?? null,
            'min_subtotal_minor' => $validated['min_subtotal_minor'] ?? 0,
            'max_subtotal_minor' => $validated['max_subtotal_minor'] ?? null,
            'priority' => $validated['priority'] ?? 100,
            'stackable' => $validated['stackable'] ?? true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.rule.created',
            'pricing_rule',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('pricing_rules')->where('id', $id)->first(),
        ], 201);
    }
}