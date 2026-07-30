<?php
namespace Modules\Suppliers\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;

final class SubmitSupplierOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $supplierOrderId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('supplier-order:'.$this->supplierOrderId))->expireAfter(180)];
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(SupplierFulfillmentService $fulfillment): void
    {
        $fulfillment->submit($this->supplierOrderId);
    }
}
