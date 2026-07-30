<?php
namespace Modules\Analytics\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Infrastructure\Models\SalesForecast;

final class SalesForecastService
{
    public function generate(string $tenantId, string $currency = 'USD', int $horizonDays = 14, int $basisDays = 30): SalesForecast
    {
        $basisEnd = CarbonImmutable::today('UTC');
        $basisStart = $basisEnd->subDays($basisDays - 1);
        $rows = DB::table('orders')
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) as day, SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as revenue_minor', ['canceled'])
            ->where('tenant_id', $tenantId)
            ->where('currency', strtoupper($currency))
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$basisStart->startOfDay(), $basisEnd->endOfDay()])
            ->groupByRaw('DATE(COALESCE(placed_at, created_at))')
            ->pluck('revenue_minor', 'day');

        $series = [];
        for ($day = $basisStart; $day->lte($basisEnd); $day = $day->addDay()) {
            $series[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        $lastSeven = array_slice($series, -7);
        $previousSeven = array_slice($series, -14, 7);
        $lastAverage = count($lastSeven) > 0 ? array_sum($lastSeven) / count($lastSeven) : 0;
        $previousAverage = count($previousSeven) > 0 ? array_sum($previousSeven) / count($previousSeven) : 0;
        $trend = $previousAverage > 0 ? (($lastAverage - $previousAverage) / $previousAverage) * 100 : ($lastAverage > 0 ? 100 : 0);
        $trend = max(-50, min(50, $trend));
        $dailyGrowth = $trend / 100 / max(1, $horizonDays);
        $points = [];
        $predicted = 0;
        for ($index = 1; $index <= $horizonDays; $index++) {
            $amount = max(0, (int) round($lastAverage * (1 + ($dailyGrowth * $index))));
            $predicted += $amount;
            $points[] = ['date' => $basisEnd->addDays($index)->toDateString(), 'predicted_revenue_minor' => $amount];
        }
        $activeDays = count(array_filter($series, fn (int $value): bool => $value > 0));
        $confidence = min(95, round(35 + (($activeDays / max(1, $basisDays)) * 45) + min(15, count($rows)), 2));

        return SalesForecast::query()->create([
            'tenant_id' => $tenantId,
            'currency' => strtoupper($currency),
            'horizon_days' => $horizonDays,
            'method' => 'weighted-moving-average-v1',
            'basis_start' => $basisStart,
            'basis_end' => $basisEnd,
            'predicted_revenue_minor' => $predicted,
            'confidence' => $confidence,
            'trend_percent' => round($trend, 2),
            'points' => $points,
            'generated_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }
}
