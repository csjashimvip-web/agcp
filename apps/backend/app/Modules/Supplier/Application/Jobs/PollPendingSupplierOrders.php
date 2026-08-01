<?php

namespace App\Modules\Supplier\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class PollPendingSupplierOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('supplier');
    }

    public function handle(): void
    {
        DB::table('supplier_orders')
            ->whereNotNull('external_order_id')
            ->whereIn('status', ['submitted', 'processing', 'pending'])
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(
                fn ($id) => ReconcileSupplierOrder::dispatch((int) $id)
                    ->onQueue('supplier')
            );
    }
}