<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Modules\Automation\Application\AutomationRuleEngine;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAutomationController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'rules' => DB::table('automation_rules')
                    ->where('tenant_id', $tenant->id())
                    ->orderBy('priority')
                    ->get(),
                'runs' => DB::table('automation_runs')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get(),
            ],
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:160'],
            'action_type' => ['required', 'in:notify'],
            'action_config' => ['required', 'array'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $id = DB::table('automation_rules')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'event_type' => $validated['event_type'],
            'action_type' => $validated['action_type'],
            'action_config' => json_encode(
                $validated['action_config'],
                JSON_THROW_ON_ERROR
            ),
            'priority' => $validated['priority'] ?? 100,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'automation.rule.created',
            'automation_rule',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('automation_rules')->where('id', $id)->first(),
        ], 201);
    }

    public function simulate(
        Request $request,
        TenantContext $tenant,
        AutomationRuleEngine $engine,
    ): JsonResponse {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:160'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json([
            'data' => $engine->dispatch(
                $tenant->id(),
                $validated['event_type'],
                $validated['payload'] ?? [],
            ),
        ]);
    }
}