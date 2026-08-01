<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminAnalyticsController
{
    public function __invoke(TenantContext $tenant): JsonResponse
    {
        $tenantId = $tenant->id();

        $activeOrders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled']);

        $completedOrders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        $statusBreakdown = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNotIn('orders.status', ['cancelled'])
            ->select(
                'order_items.sku',
                'order_items.name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.line_total_minor) as gross_minor')
            )
            ->groupBy('order_items.sku', 'order_items.name')
            ->orderByDesc('gross_minor')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'orders_total' => DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'gmv_minor' => (int) (clone $activeOrders)->sum('total_minor'),
                'completed_gmv_minor' => (int) (clone $completedOrders)->sum('total_minor'),
                'discount_minor' => (int) DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->sum('discount_minor'),
                'tax_minor' => (int) DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->sum('tax_minor'),
                'coupon_redemptions' => DB::table('coupon_redemptions')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'commission_accrued_minor' => (int) DB::table('commission_accruals')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'accrued')
                    ->sum('amount_minor'),
                'marketplace_sellers' => DB::table('marketplace_sellers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'marketplace_listings' => DB::table('marketplace_listings')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'tier_members' => DB::table('reseller_tier_memberships')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'order_status_breakdown' => $statusBreakdown,
                'top_products' => $topProducts,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}