<?php
namespace Modules\Analytics\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Analytics\Application\Jobs\RefreshTenantAnalytics;
use Modules\Analytics\Application\Services\AnalyticsPipelineService;
use Modules\Analytics\Infrastructure\Models\AiInsight;
use Modules\Analytics\Infrastructure\Models\AiModelRun;
use Modules\Analytics\Infrastructure\Models\AnalyticsSnapshot;
use Modules\Analytics\Infrastructure\Models\CustomerSegment;
use Modules\Analytics\Infrastructure\Models\SalesForecast;
use Modules\Analytics\Infrastructure\Models\SupplierRecommendation;
use Modules\Tenancy\Application\TenantContext;

final class AdminAnalyticsController extends Controller
{
    public function index(TenantContext $context): array
    {
        $tenantId = $context->requireId();
        $snapshot = AnalyticsSnapshot::query()->where('tenant_id', $tenantId)->latest('calculated_at')->first();
        $forecast = SalesForecast::query()->where('tenant_id', $tenantId)->latest('generated_at')->first();
        $segments = CustomerSegment::query()->with('user:id,name,email')->where('tenant_id', $tenantId)->orderByDesc('score')->limit(100)->get();
        $recommendations = SupplierRecommendation::query()->with(['supplier:id,name,code,health_score,success_rate,average_latency_ms', 'variant:id,name,sku'])
            ->where('tenant_id', $tenantId)->latest('generated_at')->limit(50)->get();
        $insights = AiInsight::query()->where('tenant_id', $tenantId)->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 WHEN 'opportunity' THEN 3 ELSE 4 END")->latest('generated_at')->limit(50)->get();
        $runs = AiModelRun::query()->where('tenant_id', $tenantId)->latest()->limit(10)->get();

        return ['data' => [
            'snapshot' => $snapshot,
            'forecast' => $forecast,
            'segment_summary' => $segments->groupBy(fn (CustomerSegment $segment): string => $segment->segment_code->value)->map->count(),
            'segments' => $segments,
            'supplier_recommendations' => $recommendations,
            'insights' => $insights,
            'runs' => $runs,
        ]];
    }

    public function refresh(Request $request, TenantContext $context, AnalyticsPipelineService $pipeline): JsonResponse|array
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'async' => ['nullable', 'boolean'],
            'window_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
        $tenantId = $context->requireId();
        $currency = strtoupper($data['currency'] ?? $context->tenant()?->default_currency ?? 'USD');
        if ((bool) ($data['async'] ?? false)) {
            RefreshTenantAnalytics::dispatch($tenantId, $currency);
            return response()->json(['data' => ['queued' => true, 'queue' => 'reports']], 202);
        }
        $result = $pipeline->run($tenantId, $currency, (int) ($data['window_days'] ?? config('analytics.window_days', 30)), (int) ($data['horizon_days'] ?? config('analytics.forecast_horizon_days', 14)));
        return ['data' => [
            'run_id' => $result['run']->id,
            'status' => $result['run']->status->value,
            'segments' => $result['segments']->count(),
            'supplier_recommendations' => $result['supplier_recommendations']->count(),
            'insights' => $result['insights']->count(),
        ]];
    }

    public function updateInsight(Request $request, TenantContext $context, AiInsight $insight): array
    {
        abort_unless($insight->tenant_id === $context->requireId(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'dismissed'])]]);
        $insight->update(['status' => $data['status']]);
        return ['data' => $insight->fresh()];
    }
}
