<?php
namespace Modules\Suppliers\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;

final class PollSupplierOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(public readonly string $supplierOrderId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('supplier-poll:'.$this->supplierOrderId))->expireAfter(120)];
    }

    public function handle(SupplierFulfillmentService $fulfillment): void
    {
        $fulfillment->poll($this->supplierOrderId);
    }
}
