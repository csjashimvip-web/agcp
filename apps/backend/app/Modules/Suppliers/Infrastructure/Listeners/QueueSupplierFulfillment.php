<?php
namespace Modules\Suppliers\Infrastructure\Listeners;

use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;

final class QueueSupplierFulfillment
{
    public function __construct(private readonly SupplierFulfillmentService $fulfillment) {}

    public function handle(OutboxMessagePublished $event): void
    {
        if ($event->eventName !== 'commerce.order.placed') return;
        $orderId = $event->payload['order_id'] ?? null;
        if (! is_string($orderId)) return;
        $order = Order::query()->with(['items.variant.item'])->find($orderId);
        if ($order && $order->fulfillment_status !== 'on_hold') $this->fulfillment->createForOrder($order);
    }
}
