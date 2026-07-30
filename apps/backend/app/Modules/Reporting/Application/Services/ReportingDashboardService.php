<?php
namespace Modules\Reporting\Application\Services;
use Carbon\CarbonImmutable;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Reporting\Infrastructure\Models\DataExport;
use Modules\Reporting\Infrastructure\Models\Invoice;
use Modules\Reporting\Infrastructure\Models\ReportRun;
use Modules\Reporting\Infrastructure\Models\ReportSchedule;
use Modules\Reporting\Infrastructure\Models\TaxRate;
final class ReportingDashboardService
{
    public function build(string $tenantId,?CarbonImmutable $start=null,?CarbonImmutable $end=null):array
    {
        $start??=CarbonImmutable::now()->subDays(30)->startOfDay();$end??=CarbonImmutable::now()->endOfDay();
        $orders=Order::query()->where('tenant_id',$tenantId)->whereBetween('created_at',[$start,$end]);
        $invoices=Invoice::query()->where('tenant_id',$tenantId)->whereBetween('created_at',[$start,$end]);
        $payments=PaymentIntent::query()->where('tenant_id',$tenantId)->whereBetween('created_at',[$start,$end]);
        return ['period'=>['start'=>$start->toAtomString(),'end'=>$end->toAtomString()],'metrics'=>[
            'orders'=>(clone $orders)->count(),'gross_sales_minor'=>(int)(clone $orders)->sum('total_minor'),'discount_minor'=>(int)(clone $orders)->sum('discount_minor'),'surcharge_minor'=>(int)(clone $orders)->sum('surcharge_minor'),
            'invoices'=>(clone $invoices)->count(),'tax_minor'=>(int)(clone $invoices)->where('status','!=','void')->sum('tax_minor'),'outstanding_minor'=>(int)(clone $invoices)->where('status','!=','void')->sum('amount_due_minor'),
            'captured_payments'=>(clone $payments)->where('status','completed')->count(),'captured_payment_minor'=>(int)(clone $payments)->where('status','completed')->sum('amount_minor'),
        ],'invoices'=>Invoice::query()->with('user:id,name,email')->where('tenant_id',$tenantId)->latest()->limit(50)->get(),'tax_rates'=>TaxRate::query()->where('tenant_id',$tenantId)->orderBy('code')->get(),'exports'=>DataExport::query()->where('tenant_id',$tenantId)->latest()->limit(30)->get(),'schedules'=>ReportSchedule::query()->where('tenant_id',$tenantId)->latest()->get(),'runs'=>ReportRun::query()->where('tenant_id',$tenantId)->latest('started_at')->limit(30)->get()];
    }
}
