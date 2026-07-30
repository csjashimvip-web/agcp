<?php
namespace Modules\Notifications\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Application\Jobs\DeliverNotification;
use Modules\Notifications\Domain\Enums\NotificationDeliveryStatus;
use Modules\Notifications\Infrastructure\Models\NotificationDelivery;
use Modules\Notifications\Infrastructure\Models\NotificationPreference;
use Modules\Notifications\Infrastructure\Models\NotificationTemplate;
use Modules\Notifications\Infrastructure\Models\UserNotification;
final class NotificationService
{
    public function __construct(private readonly TemplateRenderer $renderer){}
    public function notify(User $user,string $tenantId,string $eventName,array $data,array $channels=['in_app','email'],?string $deduplicationKey=null):array
    {
        return DB::transaction(function()use($user,$tenantId,$eventName,$data,$channels,$deduplicationKey):array{
            $preference=NotificationPreference::query()->where('tenant_id',$tenantId)->where('user_id',$user->id)->whereIn('event_name',[$eventName,'*'])->orderByRaw("event_name = ? desc",[$eventName])->first();
            $created=[];
            foreach(array_values(array_unique($channels)) as $channel){$enabled=$preference?->{$channel.'_enabled'}??($channel!=='sms'&&$channel!=='web_push');if(!$enabled)continue;
                $template=NotificationTemplate::query()->where(fn($q)=>$q->where('tenant_id',$tenantId)->orWhereNull('tenant_id'))->where('event_name',$eventName)->where('channel',$channel)->where('locale',$user->locale?:'en')->where('status','active')->latest('version')->first();
                if(!$template)continue;$subject=$template->subject?$this->renderer->render($template->subject,$data):ucwords(str_replace(['.','_'],' ',$eventName));$body=$this->renderer->render($template->body,$data);
                if($channel==='in_app'){$notification=UserNotification::query()->firstOrCreate(['tenant_id'=>$tenantId,'user_id'=>$user->id,'deduplication_key'=>$deduplicationKey?"{$channel}:{$deduplicationKey}":null],['event_name'=>$eventName,'title'=>$subject,'body'=>$body,'action_url'=>$data['action_url']??null,'severity'=>$data['severity']??'info','data'=>$data]);$created[]=$notification;continue;}
                $recipient=$channel==='email'?$user->email:(string)($data['phone']??'');if($recipient==='')continue;
                $delivery=NotificationDelivery::query()->create(['tenant_id'=>$tenantId,'user_id'=>$user->id,'template_id'=>$template->id,'event_name'=>$eventName,'channel'=>$channel,'recipient'=>$recipient,'status'=>NotificationDeliveryStatus::Queued,'provider'=>'log','payload'=>['subject'=>$subject,'body'=>$body,'data'=>$data]]);DeliverNotification::dispatch($delivery->id);$created[]=$delivery;
            }return $created;
        });
    }
}
