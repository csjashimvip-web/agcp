<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class ReadinessService
{
    /**
     * @return array<string,mixed>
     */
    public function probe(?int $tenantId = null, bool $persist = false): array
    {
        $databaseOk = $this->databaseOk();
        $cacheOk = $this->cacheOk();

        $queueBacklog = Schema::hasTable('jobs')
            ? DB::table('jobs')->count()
            : 0;

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;

        $pendingOutbox = Schema::hasTable('outbox_events')
            ? DB::table('outbox_events')
                ->whereNull('published_at')
                ->count()
            : 0;

        $pendingSupplierOrders = Schema::hasTable('supplier_orders')
            ? DB::table('supplier_orders')
                ->whereIn('status', ['pending', 'submitted', 'processing'])
                ->count()
            : 0;

        $healthBps = ($databaseOk && $cacheOk)
            ? 10000
            : 0;

        $snapshot = [
            'database_ok' => $databaseOk,
            'cache_ok' => $cacheOk,
            'queue_backlog' => $queueBacklog,
            'failed_jobs' => $failedJobs,
            'pending_outbox' => $pendingOutbox,
            'pending_supplier_orders' => $pendingSupplierOrders,
            'health_bps' => $healthBps,
            'ready' => $databaseOk && $cacheOk,
            'captured_at' => now()->toIso8601String(),
        ];

        if ($persist && Schema::hasTable('reliability_snapshots')) {
            DB::table('reliability_snapshots')->insert([
                'tenant_id' => $tenantId,
                'snapshot_uuid' => (string) Str::uuid(),
                'database_ok' => $databaseOk,
                'cache_ok' => $cacheOk,
                'queue_backlog' => $queueBacklog,
                'failed_jobs' => $failedJobs,
                'pending_outbox' => $pendingOutbox,
                'pending_supplier_orders' => $pendingSupplierOrders,
                'health_bps' => $healthBps,
                'metadata' => json_encode([
                    'environment' => app()->environment(),
                ], JSON_THROW_ON_ERROR),
                'captured_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $snapshot;
    }

    private function databaseOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheOk(): bool
    {
        try {
            $key = 'agcp:readiness:'.Str::random(10);
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}