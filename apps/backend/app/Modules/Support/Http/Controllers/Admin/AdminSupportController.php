<?php
namespace Modules\Support\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Support\Application\Services\SupportTicketService;
use Modules\Support\Infrastructure\Models\SupportTicket;
use Modules\Tenancy\Application\TenantContext;
final class AdminSupportController extends Controller
{
    public function index(TenantContext $context):array{$id=$context->requireId();return ['data'=>['tickets'=>SupportTicket::query()->with(['requester:id,name,email','assignee:id,name','messages'])->where('tenant_id',$id)->latest('last_activity_at')->limit(150)->get(),'metrics'=>['open'=>SupportTicket::query()->where('tenant_id',$id)->whereIn('status',['open','pending_customer','pending_internal'])->count(),'urgent'=>SupportTicket::query()->where('tenant_id',$id)->where('priority','urgent')->whereNotIn('status',['resolved','closed'])->count(),'overdue'=>SupportTicket::query()->where('tenant_id',$id)->whereNotIn('status',['resolved','closed'])->where(fn($q)=>$q->where('first_response_due_at','<',now())->whereNull('first_responded_at')->orWhere('resolution_due_at','<',now()))->count()]]];}
    public function show(TenantContext $context,SupportTicket $ticket):array{abort_unless($ticket->tenant_id===$context->requireId(),404);return ['data'=>$ticket->load(['requester:id,name,email','assignee:id,name,email','messages','events'])];}
    public function reply(Request $request,TenantContext $context,SupportTicket $ticket,SupportTicketService $service):array{abort_unless($ticket->tenant_id===$context->requireId(),404);$data=$request->validate(['message'=>['required','string','max:20000'],'internal'=>['nullable','boolean']]);return ['data'=>$service->reply($ticket,$request->user(),$data['message'],(bool)($data['internal']??false),true)];}
    public function transition(Request $request,TenantContext $context,SupportTicket $ticket,SupportTicketService $service):array{abort_unless($ticket->tenant_id===$context->requireId(),404);$data=$request->validate(['status'=>['nullable','in:open,pending_customer,pending_internal,resolved,closed'],'priority'=>['nullable','in:low,normal,high,urgent'],'assigned_to'=>['nullable','uuid','exists:users,id']]);return ['data'=>$service->transition($ticket,$request->user(),$data)];}
}
