<?php
namespace Modules\Reporting\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Reporting\Application\Services\InvoiceDocumentService;
use Modules\Reporting\Http\Resources\InvoiceResource;
use Modules\Reporting\Infrastructure\Models\Invoice;
use Modules\Tenancy\Application\TenantContext;
final class InvoiceController extends Controller
{
    public function index(TenantContext $context):array{return ['data'=>InvoiceResource::collection(Invoice::query()->with('order:id,number')->where('tenant_id',$context->requireId())->where('user_id',request()->user()->id)->latest()->paginate(30))->response()->getData(true)];}
    public function show(TenantContext $context,Invoice $invoice):InvoiceResource{$this->authorizeInvoice($context,$invoice);return new InvoiceResource($invoice->load(['lines','order:id,number']));}
    public function document(TenantContext $context,Invoice $invoice,InvoiceDocumentService $documents):Response{$this->authorizeInvoice($context,$invoice);return response($documents->html($invoice),200,['Content-Type'=>'text/html; charset=UTF-8','Content-Disposition'=>'inline; filename="'.$invoice->number.'.html"','X-Content-Hash'=>$invoice->content_hash]);}
    private function authorizeInvoice(TenantContext $context,Invoice $invoice):void{abort_unless($invoice->tenant_id===$context->requireId()&&$invoice->user_id===request()->user()->id,404);}
}
