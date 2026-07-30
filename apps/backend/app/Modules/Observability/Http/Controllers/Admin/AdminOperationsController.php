<?php
namespace Modules\Observability\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Integrations\Infrastructure\Models\WebhookDelivery;
use Modules\Notifications\Infrastructure\Models\NotificationDelivery;
use Modules\Observability\Application\Services\OperationsSnapshotService;
use Modules\Observability\Infrastructure\Models\OperationalIncident;
use Modules\Observability\Infrastructure\Models\OperationsSnapshot;
use Modules\Support\Infrastructure\Models\SupportTicket;
use Modules\Tenancy\Application\TenantContext;
final class AdminOperationsController extends Controller
{
    public function index(TenantContext $context):array{$id=$context->requireId();return ['data'=>['latest_snapshot'=>OperationsSnapshot::query()->where('tenant_id',$id)->latest('captured_at')->first(),'snapshots'=>OperationsSnapshot::query()->where('tenant_id',$id)->latest('captured_at')->limit(48)->get(),'incidents'=>OperationalIncident::query()->where('tenant_id',$id)->latest('last_seen_at')->limit(100)->get(),'support_tickets'=>SupportTicket::query()->with(['requester:id,name,email','assignee:id,name'])->where('tenant_id',$id)->latest('last_activity_at')->limit(50)->get(),'webhook_failures'=>WebhookDelivery::query()->with('endpoint:id,name')->where('tenant_id',$id)->whereIn('status',['failed','dead'])->latest()->limit(50)->get(),'notification_failures'=>NotificationDelivery::query()->where('tenant_id',$id)->where('status','failed')->latest()->limit(50)->get()]];}
    public function capture(TenantContext $context,OperationsSnapshotService $service):array{return ['data'=>$service->capture($context->requireId())];}
    public function acknowledge(Request $request,TenantContext $context,OperationalIncident $incident):array{abort_unless($incident->tenant_id===$context->requireId(),404);$incident->forceFill(['status'=>'acknowledged','acknowledged_by'=>$request->user()->id,'acknowledged_at'=>now()])->save();return ['data'=>$incident];}
    public function resolve(Request $request,TenantContext $context,OperationalIncident $incident):array{abort_unless($incident->tenant_id===$context->requireId(),404);$incident->forceFill(['status'=>'resolved','resolved_at'=>now()])->save();return ['data'=>$incident];}
}
