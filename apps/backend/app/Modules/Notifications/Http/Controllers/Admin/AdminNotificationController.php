<?php
namespace Modules\Notifications\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Notifications\Application\Services\NotificationService;
use Modules\Notifications\Infrastructure\Models\NotificationDelivery;
use Modules\Notifications\Infrastructure\Models\NotificationTemplate;
use Modules\Tenancy\Application\TenantContext;
final class AdminNotificationController extends Controller
{
    public function index(TenantContext $context):array{$id=$context->requireId();return ['data'=>['templates'=>NotificationTemplate::query()->where(fn($q)=>$q->where('tenant_id',$id)->orWhereNull('tenant_id'))->latest()->get(),'deliveries'=>NotificationDelivery::query()->where('tenant_id',$id)->latest()->limit(100)->get(),'metrics'=>['queued'=>NotificationDelivery::query()->where('tenant_id',$id)->where('status','queued')->count(),'failed'=>NotificationDelivery::query()->where('tenant_id',$id)->where('status','failed')->count(),'delivered'=>NotificationDelivery::query()->where('tenant_id',$id)->where('status','delivered')->count()]]];}
    public function storeTemplate(Request $request,TenantContext $context):array{$data=$request->validate(['event_name'=>['required','string','max:160'],'channel'=>['required',Rule::in(['in_app','email','sms','web_push'])],'locale'=>['nullable','string','max:12'],'subject'=>['nullable','string','max:255'],'body'=>['required','string','max:20000']]);$version=(int)NotificationTemplate::query()->where('tenant_id',$context->requireId())->where('event_name',$data['event_name'])->where('channel',$data['channel'])->max('version')+1;$template=NotificationTemplate::query()->create($data+['tenant_id'=>$context->requireId(),'locale'=>$data['locale']??'en','version'=>$version,'status'=>'active']);return ['data'=>$template];}
    public function sendTest(Request $request,TenantContext $context,NotificationService $service):array{$data=$request->validate(['user_id'=>['required','uuid'],'event_name'=>['required','string','max:160']]);$user=User::query()->findOrFail($data['user_id']);$result=$service->notify($user,$context->requireId(),$data['event_name'],['name'=>$user->name,'reference'=>'TEST-'.now()->format('YmdHis'),'amount_minor'=>1000,'currency'=>'USD','action_url'=>'/dashboard'],['in_app','email'],'test-'.now()->timestamp);return ['data'=>['created'=>count($result)]];}
}
