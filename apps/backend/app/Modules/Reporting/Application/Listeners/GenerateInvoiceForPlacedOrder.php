<?php
namespace Modules\Reporting\Application\Listeners;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Reporting\Application\Services\InvoiceService;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
final class GenerateInvoiceForPlacedOrder
{
    public function __construct(private readonly InvoiceService $invoices){}
    public function handle(OutboxMessagePublished $event):void
    {
        if($event->eventName!=='commerce.order.placed')return;$orderId=$event->payload['order_id']??null;if(!is_string($orderId))return;$order=Order::query()->find($orderId);if($order)$this->invoices->generate($order);
    }
}
