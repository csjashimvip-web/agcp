<?php
namespace Modules\Analytics\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Infrastructure\Models\AnalyticsSnapshot;

final class AnalyticsSnapshotService
{
    public function calculate(string $tenantId, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null, string $currency = 'USD'): AnalyticsSnapshot
    {
        $end ??= CarbonImmutable::today('UTC');
        $start ??= $end->subDays(29);
        $from = $start->startOfDay();
        $until = $end->endOfDay();

        $orders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('currency', strtoupper($currency))
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$from, $until]);

        $ordersCount = (int) (clone $orders)->count();
        $completedCount = (int) (clone $orders)->where('status', 'completed')->count();
        $gross = (int) (clone $orders)->where('status', '!=', 'canceled')->sum('total_minor');
        $refunded = (int) (clone $orders)->where('payment_status', 'refunded')->sum('total_minor');
        $discounts = (int) (clone $orders)->sum('discount_minor');
        $surcharges = (int) (clone $orders)->sum('surcharge_minor');
        $uniqueCustomers = (int) (clone $orders)->distinct()->count('user_id');
        $average = $ordersCount > 0 ? intdiv($gross, max(1, $ordersCount)) : 0;
        $riskReviews = (int) DB::table('fraud_risk_assessments')->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $until])->where('decision', 'review')->count();
        $supplierTotal = (int) DB::table('supplier_orders')->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $until])->whereIn('status', ['completed', 'failed', 'refunded'])->count();
        $supplierCompleted = (int) DB::table('supplier_orders')->where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $until])->where('status', 'completed')->count();
        $supplierRate = $supplierTotal > 0 ? round(($supplierCompleted / $supplierTotal) * 100, 2) : 0.0;

        return AnalyticsSnapshot::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'granularity' => 'daily',
            'currency' => strtoupper($currency),
        ], [
            'orders_count' => $ordersCount,
            'completed_orders_count' => $completedCount,
            'gross_revenue_minor' => $gross,
            'net_revenue_minor' => max(0, $gross - $refunded),
            'refunded_minor' => $refunded,
            'discounts_minor' => $discounts,
            'surcharges_minor' => $surcharges,
            'unique_customers' => $uniqueCustomers,
            'average_order_value_minor' => $average,
            'risk_review_count' => $riskReviews,
            'supplier_success_rate' => $supplierRate,
            'metrics' => [
                'refund_rate_percent' => $gross > 0 ? round(($refunded / $gross) * 100, 2) : 0,
                'completion_rate_percent' => $ordersCount > 0 ? round(($completedCount / $ordersCount) * 100, 2) : 0,
                'supplier_terminal_orders' => $supplierTotal,
            ],
            'calculated_at' => now(),
        ]);
    }
}
