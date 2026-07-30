<?php
namespace Modules\Suppliers\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Suppliers\Application\Jobs\PollSupplierOrder;
use Modules\Suppliers\Domain\Enums\SupplierOrderStatus;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;

final class PollSupplierOrders extends Command
{
    protected $signature = 'supplier:poll {--limit=100}';
    protected $description = 'Queue status checks for supplier orders that are due.';

    public function handle(): int
    {
        $orders = SupplierOrder::query()
            ->whereIn('status', [SupplierOrderStatus::Submitted->value, SupplierOrderStatus::Processing->value])
            ->whereNotNull('supplier_reference')
            ->where(fn ($query) => $query->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now()))
            ->oldest('next_poll_at')
            ->limit(max(1, min(1000, (int) $this->option('limit'))))
            ->get();

        foreach ($orders as $order) PollSupplierOrder::dispatch($order->id)->onQueue('supplier');
        $this->info('Queued '.$orders->count().' supplier status check(s).');
        return self::SUCCESS;
    }
}
