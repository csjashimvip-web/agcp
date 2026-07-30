<?php
namespace Modules\Reporting\Application\Services;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Reporting\Domain\Enums\ExportStatus;
use Modules\Reporting\Infrastructure\Models\DataExport;
use Modules\Reporting\Infrastructure\Models\Invoice;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
use Throwable;
final class DataExportService
{
    public const TYPES=['orders','invoices','payments','deposits','tax_summary'];
    public function create(string $tenantId,string $type,?User $requester=null,?string $start=null,?string $end=null,array $filters=[]):DataExport
    {
        abort_unless(in_array($type,self::TYPES,true),422,'Unsupported export type.');
        $export=DataExport::query()->create(['tenant_id'=>$tenantId,'requested_by'=>$requester?->id,'type'=>$type,'format'=>'csv','status'=>ExportStatus::Processing,'period_start'=>$start,'period_end'=>$end,'filters'=>$filters,'storage_disk'=>config('reporting.export_disk','local'),'started_at'=>now(),'expires_at'=>now()->addDays((int)config('reporting.export_retention_days',30))]);
        try{$this->generate($export);}catch(Throwable $e){$export->update(['status'=>ExportStatus::Failed,'error_message'=>mb_substr($e->getMessage(),0,2000),'completed_at'=>now()]);throw $e;}return $export->fresh();
    }
    public function generate(DataExport $export):void
    {
        [$headers,$rows]=$this->dataset($export);$stream=fopen('php://temp','w+');fputcsv($stream,$headers);$count=0;foreach($rows as $row){fputcsv($stream,$row);$count++;}rewind($stream);$contents=stream_get_contents($stream);fclose($stream);$path=trim((string)config('reporting.export_directory','exports'),'/').'/'.$export->tenant_id.'/'.$export->id.'.csv';if(!Storage::disk($export->storage_disk)->put($path,$contents))throw new \RuntimeException('Unable to persist export file.');$export->update(['status'=>ExportStatus::Completed,'storage_path'=>$path,'checksum_sha256'=>hash('sha256',$contents),'file_size'=>strlen($contents),'row_count'=>$count,'completed_at'=>now()]);
    }
    private function scope(Builder $query,DataExport $export):Builder
    {
        if($export->period_start)$query->where('created_at','>=',$export->period_start);if($export->period_end)$query->where('created_at','<=',$export->period_end);return $query;
    }
    private function dataset(DataExport $export):array
    {
        return match($export->type){
            'orders'=>[['number','status','payment_status','fulfillment_status','currency','subtotal_minor','discount_minor','surcharge_minor','total_minor','created_at'],$this->scope(Order::query()->where('tenant_id',$export->tenant_id),$export)->orderBy('created_at')->get()->map(fn($x)=>[$x->number,$x->status->value,$x->payment_status,$x->fulfillment_status,$x->currency,$x->subtotal_minor,$x->discount_minor,$x->surcharge_minor,$x->total_minor,$x->created_at?->toAtomString()])],
            'invoices'=>[['number','order_id','status','currency','subtotal_minor','discount_minor','surcharge_minor','tax_minor','total_minor','amount_paid_minor','amount_due_minor','issued_at'],$this->scope(Invoice::query()->where('tenant_id',$export->tenant_id),$export)->orderBy('created_at')->get()->map(fn($x)=>[$x->number,$x->order_id,$x->status->value,$x->currency,$x->subtotal_minor,$x->discount_minor,$x->surcharge_minor,$x->tax_minor,$x->total_minor,$x->amount_paid_minor,$x->amount_due_minor,$x->issued_at?->toAtomString()])],
            'payments'=>[['reference','status','currency','amount_minor','fee_minor','total_minor','provider_payment_id','completed_at'],$this->scope(PaymentIntent::query()->where('tenant_id',$export->tenant_id),$export)->orderBy('created_at')->get()->map(fn($x)=>[$x->reference,$x->status->value,$x->currency,$x->amount_minor,$x->fee_minor,$x->total_minor,$x->provider_payment_id,$x->completed_at?->toAtomString()])],
            'deposits'=>[['id','status','method','currency','amount_minor','external_reference','submitted_at','reviewed_at'],$this->scope(DepositRequest::query()->where('tenant_id',$export->tenant_id),$export)->orderBy('created_at')->get()->map(fn($x)=>[$x->id,$x->status->value,$x->method,$x->currency,$x->amount_minor,$x->external_reference,$x->submitted_at?->toAtomString(),$x->reviewed_at?->toAtomString()])],
            'tax_summary'=>[['currency','invoice_count','tax_minor','gross_minor'],Invoice::query()->where('tenant_id',$export->tenant_id)->where('status','!=','void')->selectRaw('currency, count(*) invoice_count, sum(tax_minor) tax_minor, sum(total_minor) gross_minor')->groupBy('currency')->get()->map(fn($x)=>[$x->currency,$x->invoice_count,$x->tax_minor,$x->gross_minor])],
        };
    }
}
