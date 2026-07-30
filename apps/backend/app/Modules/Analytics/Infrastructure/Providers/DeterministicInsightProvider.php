<?php
namespace Modules\Analytics\Infrastructure\Providers;

use Modules\Analytics\Domain\Contracts\AiInsightProvider;

final class DeterministicInsightProvider implements AiInsightProvider
{
    public function key(): string { return 'deterministic'; }
    public function version(): string { return 'agcp-explainable-v1'; }

    public function generate(array $context): array
    {
        $snapshot = $context['snapshot'] ?? [];
        $forecast = $context['forecast'] ?? [];
        $segments = $context['segments'] ?? [];
        $recommendations = $context['supplier_recommendations'] ?? [];
        $insights = [];

        $gross = (int) ($snapshot['gross_revenue_minor'] ?? 0);
        $refunded = (int) ($snapshot['refunded_minor'] ?? 0);
        $refundRate = $gross > 0 ? ($refunded / $gross) * 100 : 0;
        if ($gross === 0) {
            $insights[] = $this->insight('sales-no-revenue', 'sales', 'warning', 'No revenue recorded in the analysis window',
                'The commerce pipeline did not record paid non-canceled revenue during the selected period.',
                ['Review catalog publication, wallet funding and checkout conversion.'], ['gross_revenue_minor' => 0]);
        } elseif (($forecast['trend_percent'] ?? 0) >= 10) {
            $insights[] = $this->insight('sales-positive-trend', 'sales', 'opportunity', 'Revenue momentum is positive',
                sprintf('The explainable forecast detects a %.1f%% near-term trend.', (float) $forecast['trend_percent']),
                ['Protect supplier capacity and inventory for the projected demand.'], ['forecast' => $forecast]);
        } elseif (($forecast['trend_percent'] ?? 0) <= -10) {
            $insights[] = $this->insight('sales-negative-trend', 'sales', 'warning', 'Revenue momentum is weakening',
                sprintf('The explainable forecast detects a %.1f%% near-term trend.', (float) $forecast['trend_percent']),
                ['Review pricing, failed checkout signals and inactive catalog items.'], ['forecast' => $forecast]);
        }

        if ($refundRate >= 10) {
            $insights[] = $this->insight('operations-refund-rate', 'operations', $refundRate >= 25 ? 'critical' : 'warning', 'Refund rate needs attention',
                sprintf('Refunded value is %.1f%% of gross revenue for the analysis window.', $refundRate),
                ['Inspect supplier failures and product-specific refund reasons.'], ['refund_rate_percent' => round($refundRate, 2)]);
        }

        $atRisk = (int) ($segments['at_risk'] ?? 0);
        if ($atRisk > 0) {
            $insights[] = $this->insight('customer-at-risk', 'customer', 'opportunity', 'Customers are showing churn risk',
                sprintf('%d customer segment record(s) are currently classified as at risk.', $atRisk),
                ['Create a permission-safe retention offer and measure reactivation.'], ['segments' => $segments]);
        }

        $supplierRate = (float) ($snapshot['supplier_success_rate'] ?? 0);
        if (($snapshot['metrics']['supplier_terminal_orders'] ?? 0) > 0 && $supplierRate < 90) {
            $insights[] = $this->insight('supplier-low-success', 'supplier', 'warning', 'Supplier success rate is below target',
                sprintf('Terminal supplier orders completed successfully at %.1f%%.', $supplierRate),
                ['Review the recommendation ranking and disable persistently failing routes.'], ['supplier_success_rate' => $supplierRate]);
        }

        if (count($recommendations) > 0) {
            $insights[] = $this->insight('supplier-recommendation-ready', 'supplier', 'info', 'Explainable supplier recommendations are available',
                sprintf('%d catalog variant(s) have an auditable ranked supplier recommendation.', count($recommendations)),
                ['Review confidence and candidate evidence before changing production routing.'], ['top_recommendation' => $recommendations[0]]);
        }

        if ((int) ($snapshot['risk_review_count'] ?? 0) > 0) {
            $insights[] = $this->insight('fraud-review-queue', 'fraud', 'warning', 'Risk reviews are waiting for attention',
                sprintf('%d checkout risk review(s) were generated during this period.', (int) $snapshot['risk_review_count']),
                ['Review held orders using two-person approval where required.'], ['risk_review_count' => (int) $snapshot['risk_review_count']]);
        }

        return $insights;
    }

    private function insight(string $fingerprint, string $type, string $severity, string $title, string $summary, array $recommendations, array $evidence): array
    {
        return compact('fingerprint', 'type', 'severity', 'title', 'summary', 'recommendations', 'evidence');
    }
}
