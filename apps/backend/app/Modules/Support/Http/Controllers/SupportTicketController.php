<?php
namespace Modules\Support\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Support\Application\Services\SupportTicketService;
use Modules\Support\Infrastructure\Models\SupportTicket;
use Modules\Tenancy\Application\TenantContext;
final class SupportTicketController extends Controller
{
    public function index(Request $request,TenantContext $context):array{return ['data'=>SupportTicket::query()->with(['messages'=>fn($q)=>$q->where('is_internal',false),'assignee:id,name'])->where('tenant_id',$context->requireId())->where('requester_id',$request->user()->id)->latest('last_activity_at')->paginate(30)];}
    public function store(Request $request,TenantContext $context,SupportTicketService $service):array{$data=$request->validate(['subject'=>['required','string','max:255'],'message'=>['required','string','max:20000'],'category'=>['nullable','in:general,order,payment,wallet,technical,account'],'priority'=>['nullable','in:low,normal,high,urgent'],'related_type'=>['nullable','string','max:190'],'related_id'=>['nullable','uuid']]);return ['data'=>$service->create($context->requireId(),$request->user(),$data)];}
    public function show(Request $request,TenantContext $context,SupportTicket $ticket):array{abort_unless($ticket->tenant_id===$context->requireId()&&$ticket->requester_id===$request->user()->id,404);return ['data'=>$ticket->load(['messages'=>fn($q)=>$q->where('is_internal',false),'events','assignee:id,name'])];}
    public function reply(Request $request,TenantContext $context,SupportTicket $ticket,SupportTicketService $service):array{abort_unless($ticket->tenant_id===$context->requireId()&&$ticket->requester_id===$request->user()->id,404);$data=$request->validate(['message'=>['required','string','max:20000']]);return ['data'=>$service->reply($ticket,$request->user(),$data['message'])];}
}
