<?php
namespace Modules\Reporting\Infrastructure\Console;
use Illuminate\Console\Command;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Reporting\Application\Services\InvoiceService;
use Modules\Reporting\Infrastructure\Models\Invoice;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class GenerateMissingInvoices extends Command
{
    protected $signature='invoices:generate-missing {--tenant=} {--limit=100}';
    protected $description='Generate immutable invoices for paid orders without an invoice.';
    public function handle(InvoiceService $service):int{$slug=(string)$this->option('tenant');$tenantId=$slug!==''?Tenant::query()->where('slug',$slug)->value('id'):null;$query=Order::query()->where('payment_status','paid')->whereNotExists(fn($q)=>$q->selectRaw('1')->from('invoices')->whereColumn('invoices.order_id','orders.id'));if($tenantId)$query->where('tenant_id',$tenantId);$count=0;foreach($query->oldest()->limit((int)$this->option('limit'))->get() as $order){$service->generate($order);$count++;}$this->info("Generated {$count} invoice(s).");return self::SUCCESS;}
}
