<?php
namespace Modules\Reporting\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Application\AuditLogger;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Reporting\Domain\Enums\InvoiceStatus;
use Modules\Reporting\Infrastructure\Models\CustomerTaxProfile;
use Modules\Reporting\Infrastructure\Models\Invoice;
use Modules\Reporting\Infrastructure\Models\TenantTaxProfile;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class InvoiceService
{
    public function __construct(private readonly TaxCalculationService $taxes,private readonly AuditLogger $audit){}
    public function generate(Order $order,?User $actor=null):Invoice
    {
        return DB::transaction(function()use($order,$actor):Invoice{
            $locked=Order::query()->with(['items','user'])->lockForUpdate()->findOrFail($order->id);
            $existing=Invoice::query()->with(['lines','events'])->where('order_id',$locked->id)->first();if($existing)return $existing;
            $tenant=Tenant::query()->findOrFail($locked->tenant_id);
            $profile=TenantTaxProfile::query()->where('tenant_id',$locked->tenant_id)->lockForUpdate()->first();
            if(!$profile)$profile=TenantTaxProfile::query()->create(['tenant_id'=>$locked->tenant_id,'legal_name'=>$tenant->name,'invoice_prefix'=>'INV','next_invoice_sequence'=>1,'default_tax_behavior'=>'inclusive','status'=>'active']);
            $sequence=(int)$profile->next_invoice_sequence;$profile->update(['next_invoice_sequence'=>$sequence+1]);
            $number=sprintf('%s-%s-%06d',$profile->invoice_prefix,now()->format('Y'),$sequence);
            $customerTax=CustomerTaxProfile::query()->where('tenant_id',$locked->tenant_id)->where('user_id',$locked->user_id)->first();
            $seller=['legal_name'=>$profile->legal_name,'tax_registration_number'=>$profile->tax_registration_number,'country_code'=>$profile->country_code,'region_code'=>$profile->region_code,'address'=>$profile->address,'footer'=>$profile->invoice_footer];
            $buyer=['name'=>$customerTax?->legal_name?:$locked->user->name,'email'=>$locked->user->email,'tax_identifier'=>$customerTax?->tax_identifier,'country_code'=>$customerTax?->country_code,'region_code'=>$customerTax?->region_code,'address'=>$customerTax?->address,'tax_exempt'=>(bool)($customerTax?->tax_exempt??false)];
            $invoice=Invoice::query()->create(['tenant_id'=>$locked->tenant_id,'order_id'=>$locked->id,'user_id'=>$locked->user_id,'created_by'=>$actor?->id,'number'=>$number,'status'=>InvoiceStatus::Issued,'currency'=>$locked->currency,'subtotal_minor'=>$locked->subtotal_minor,'discount_minor'=>$locked->discount_minor,'surcharge_minor'=>$locked->surcharge_minor??0,'tax_minor'=>0,'total_minor'=>$locked->total_minor,'amount_paid_minor'=>$locked->payment_status==='paid'?$locked->total_minor:0,'amount_due_minor'=>$locked->payment_status==='paid'?0:$locked->total_minor,'seller_snapshot'=>$seller,'buyer_snapshot'=>$buyer,'tax_snapshot'=>[],'content_hash'=>str_repeat('0',64),'issued_at'=>now(),'due_at'=>now()->addDays((int)config('reporting.invoice_due_days',0)),'paid_at'=>$locked->payment_status==='paid'?now():null]);
            $taxTotal=0;$exclusiveTax=0;$snapshots=[];$linePayload=[];$n=1;
            foreach($locked->items as $item){$rate=$this->taxes->resolve($locked->tenant_id,(string)$item->item_type,$customerTax);$split=$this->taxes->split((int)$item->total_minor,$rate);$taxTotal+=$split['tax_minor'];if($rate&&!$rate->price_inclusive)$exclusiveTax+=$split['tax_minor'];$snapshot=$rate?['code'=>$rate->code,'name'=>$rate->name,'jurisdiction'=>$rate->jurisdiction,'rate_basis_points'=>$rate->rate_basis_points,'price_inclusive'=>$rate->price_inclusive]:['code'=>'ZERO','name'=>'No tax','rate_basis_points'=>0,'price_inclusive'=>true];$snapshots[$snapshot['code']]=$snapshot;$line=['order_item_id'=>$item->id,'tax_rate_id'=>$rate?->id,'sequence'=>$n++,'description'=>$item->item_name.($item->variant_name?' — '.$item->variant_name:''),'sku'=>$item->sku,'item_type'=>$item->item_type,'quantity'=>$item->quantity,'unit_price_minor'=>$item->unit_price_minor,'net_minor'=>$split['net_minor'],'tax_rate_basis_points'=>$split['rate_basis_points'],'tax_minor'=>$split['tax_minor'],'gross_minor'=>$split['gross_minor'],'tax_snapshot'=>$snapshot];$invoice->lines()->create($line);$linePayload[]=$line;}
            if((int)$locked->discount_minor>0){$line=['sequence'=>$n++,'description'=>'Order discount','quantity'=>1,'unit_price_minor'=>-(int)$locked->discount_minor,'net_minor'=>-(int)$locked->discount_minor,'tax_rate_basis_points'=>0,'tax_minor'=>0,'gross_minor'=>-(int)$locked->discount_minor,'tax_snapshot'=>['code'=>'ADJUSTMENT']];$invoice->lines()->create($line);$linePayload[]=$line;}
            if((int)($locked->surcharge_minor??0)>0){$line=['sequence'=>$n++,'description'=>'Order surcharge','quantity'=>1,'unit_price_minor'=>(int)$locked->surcharge_minor,'net_minor'=>(int)$locked->surcharge_minor,'tax_rate_basis_points'=>0,'tax_minor'=>0,'gross_minor'=>(int)$locked->surcharge_minor,'tax_snapshot'=>['code'=>'ADJUSTMENT']];$invoice->lines()->create($line);$linePayload[]=$line;}
            $invoiceTotal=(int)$locked->total_minor+$exclusiveTax;$paid=$locked->payment_status==='paid'?(int)$locked->total_minor:0;$due=max(0,$invoiceTotal-$paid);$hash=hash('sha256',json_encode(['invoice'=>$number,'order'=>$locked->number,'seller'=>$seller,'buyer'=>$buyer,'currency'=>$locked->currency,'total'=>$invoiceTotal,'lines'=>$linePayload],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            $invoice->update(['status'=>$due===0?InvoiceStatus::Paid:InvoiceStatus::Issued,'tax_minor'=>$taxTotal,'total_minor'=>$invoiceTotal,'amount_paid_minor'=>$paid,'amount_due_minor'=>$due,'paid_at'=>$due===0?now():null,'tax_snapshot'=>array_values($snapshots),'content_hash'=>$hash]);
            $invoice->events()->create(['actor_id'=>$actor?->id,'event_type'=>'issued','data'=>['order_number'=>$locked->number,'content_hash'=>$hash]]);
            $this->audit->record('invoice.issued',Invoice::class,$invoice->id,['number'=>$number,'order_id'=>$locked->id],[], $locked->tenant_id,$actor?'user':'system',$actor?->id);
            return $invoice->fresh(['lines','events','order','user']);
        });
    }
    public function void(Invoice $invoice,User $actor,string $reason):Invoice
    {
        return DB::transaction(function()use($invoice,$actor,$reason){$locked=Invoice::query()->lockForUpdate()->findOrFail($invoice->id);abort_if($locked->status===InvoiceStatus::Void,409,'Invoice is already void.');$locked->update(['status'=>InvoiceStatus::Void,'voided_at'=>now(),'metadata'=>array_merge($locked->metadata??[],['void_reason'=>$reason])]);$locked->events()->create(['actor_id'=>$actor->id,'event_type'=>'voided','data'=>['reason'=>$reason]]);$this->audit->record('invoice.voided',Invoice::class,$locked->id,['reason'=>$reason],[],$locked->tenant_id,'user',$actor->id);return $locked->fresh(['lines','events']);});
    }
}
