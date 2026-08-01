<?php

namespace App\Modules\SaaS\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminSaaSController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'plans' => DB::table('saas_plans')->orderBy('price_minor')->get(),
                'subscriptions' => DB::table('tenant_subscriptions')
                    ->leftJoin(
                        'saas_plans',
                        'saas_plans.id',
                        '=',
                        'tenant_subscriptions.saas_plan_id'
                    )
                    ->where('tenant_subscriptions.tenant_id', $tenant->id())
                    ->orderByDesc('tenant_subscriptions.id')
                    ->get([
                        'tenant_subscriptions.*',
                        'saas_plans.name as plan_name',
                    ]),
            ],
        ]);
    }

    public function createPlan(
        Request $request,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:96'],
            'billing_period' => ['required', 'in:monthly,yearly,one_time'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        abort_if(
            DB::table('saas_plans')->where('slug', $slug)->exists(),
            422,
            'Plan slug already exists.'
        );

        $id = DB::table('saas_plans')->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'billing_period' => $validated['billing_period'],
            'price_minor' => $validated['price_minor'],
            'currency' => strtoupper($validated['currency']),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => DB::table('saas_plans')->where('id', $id)->first(),
        ], 201);
    }

    public function subscribe(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'saas_plan_id' => ['nullable', 'integer', 'exists:saas_plans,id'],
            'mode' => ['required', 'in:cloud,self_hosted'],
        ]);

        DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenant->id())
            ->where('status', 'active')
            ->update([
                'status' => 'replaced',
                'ends_at' => now(),
                'updated_at' => now(),
            ]);

        $id = DB::table('tenant_subscriptions')->insertGetId([
            'tenant_id' => $tenant->id(),
            'saas_plan_id' => $validated['saas_plan_id'] ?? null,
            'subscription_uuid' => (string) Str::uuid(),
            'mode' => $validated['mode'],
            'status' => 'active',
            'starts_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'saas.subscription.changed',
            'tenant_subscription',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('tenant_subscriptions')
                ->where('id', $id)
                ->first(),
        ], 201);
    }
}