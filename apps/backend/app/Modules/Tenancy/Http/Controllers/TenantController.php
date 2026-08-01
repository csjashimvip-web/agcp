<?php

namespace App\Modules\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantController
{
    public function index(Request $request): JsonResponse
    {
        $tenants = DB::table('tenant_memberships')
            ->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->where('tenant_memberships.user_id', $request->user()->id)
            ->where('tenant_memberships.status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.name')
            ->get([
                'tenants.id',
                'tenants.name',
                'tenants.slug',
                'tenants.default_currency',
            ]);

        return response()->json(['data' => $tenants]);
    }
}