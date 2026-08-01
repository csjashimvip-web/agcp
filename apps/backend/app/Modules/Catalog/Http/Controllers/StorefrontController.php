<?php

namespace App\Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class StorefrontController
{
    public function catalog(string $tenantSlug): JsonResponse
    {
        $tenant = DB::table('tenants')
            ->where('slug', $tenantSlug)
            ->where('status', 'active')
            ->first();

        abort_unless($tenant, 404);

        $products = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(500)
            ->get([
                'id',
                'sku',
                'name',
                'slug',
                'type',
                'description',
                'currency',
                'price_minor',
                'service_schema',
            ]);

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'currency' => $tenant->default_currency,
                ],
                'products' => $products,
            ],
        ]);
    }
}