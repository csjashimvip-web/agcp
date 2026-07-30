<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dependencies = ['database' => $this->check(fn () => DB::select('SELECT 1')), 'redis' => $this->check(fn () => Redis::connection()->ping())];
        $healthy = collect($dependencies)->every(fn (array $item) => $item['status'] === 'ok');
        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'agcp-api',
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
            'request_id' => $request->attributes->get('request_id'),
            'dependencies' => $dependencies,
        ], $healthy ? 200 : 503);
    }
    private function check(callable $callback): array
    {
        $start = hrtime(true);
        try { $callback(); $status = 'ok'; } catch (Throwable) { $status = 'error'; }
        return ['status' => $status, 'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000)];
    }
}
