<?php
namespace Modules\Notifications\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Notifications\Infrastructure\Models\NotificationPreference;
use Modules\Notifications\Infrastructure\Models\UserNotification;
use Modules\Tenancy\Application\TenantContext;
final class NotificationController extends Controller
{
    public function index(Request $request,TenantContext $context):array{$q=UserNotification::query()->where('tenant_id',$context->requireId())->where('user_id',$request->user()->id)->latest();return ['data'=>$q->paginate(30)];}
    public function unreadCount(Request $request,TenantContext $context):array{return ['data'=>['unread'=>UserNotification::query()->where('tenant_id',$context->requireId())->where('user_id',$request->user()->id)->whereNull('read_at')->count()]];}
    public function read(Request $request,TenantContext $context,UserNotification $notification):array{abort_unless($notification->tenant_id===$context->requireId()&&$notification->user_id===$request->user()->id,404);$notification->forceFill(['read_at'=>now()])->save();return ['data'=>$notification];}
    public function readAll(Request $request,TenantContext $context):array{UserNotification::query()->where('tenant_id',$context->requireId())->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);return ['data'=>['updated'=>true]];}
    public function preferences(Request $request,TenantContext $context):array{return ['data'=>NotificationPreference::query()->where('tenant_id',$context->requireId())->where('user_id',$request->user()->id)->get()];}
    public function updatePreferences(Request $request,TenantContext $context):array{$data=$request->validate(['event_name'=>['required','string','max:160'],'in_app_enabled'=>['required','boolean'],'email_enabled'=>['required','boolean'],'sms_enabled'=>['required','boolean'],'web_push_enabled'=>['required','boolean'],'timezone'=>['nullable','timezone']]);$pref=NotificationPreference::query()->updateOrCreate(['tenant_id'=>$context->requireId(),'user_id'=>$request->user()->id,'event_name'=>$data['event_name']],$data+['timezone'=>$data['timezone']??$request->user()->timezone??'UTC']);return ['data'=>$pref];}
}
