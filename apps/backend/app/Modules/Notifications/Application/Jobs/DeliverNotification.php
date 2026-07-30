<?php
namespace Modules\Notifications\Application\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Application\Services\NotificationProviderRegistry;
use Modules\Notifications\Domain\Enums\NotificationDeliveryStatus;
use Modules\Notifications\Infrastructure\Models\NotificationDelivery;
use Throwable;
final class DeliverNotification implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=5; public array $backoff=[30,120,600,1800];
    public function __construct(public readonly string $deliveryId){$this->onQueue('notifications');}
    public function handle(NotificationProviderRegistry $registry):void
    {
        $delivery=NotificationDelivery::query()->find($this->deliveryId); if(!$delivery||!in_array($delivery->status,[NotificationDeliveryStatus::Queued,NotificationDeliveryStatus::Failed],true))return;
        $delivery->forceFill(['status'=>NotificationDeliveryStatus::Sending,'attempts'=>$delivery->attempts+1,'last_error'=>null])->save();
        try{$result=$registry->for($delivery->channel)->send($delivery->recipient,$delivery->payload);$delivery->forceFill(['status'=>($result['delivered']??false)?NotificationDeliveryStatus::Delivered:NotificationDeliveryStatus::Sent,'provider_message_id'=>$result['provider_message_id']??null,'sent_at'=>now(),'delivered_at'=>($result['delivered']??false)?now():null])->save();}
        catch(Throwable $e){$terminal=$delivery->attempts>=$this->tries;$delivery->forceFill(['status'=>NotificationDeliveryStatus::Failed,'last_error'=>mb_substr($e->getMessage(),0,2000),'next_attempt_at'=>$terminal?null:now()->addSeconds($this->backoff[min($delivery->attempts-1,count($this->backoff)-1)]),'failed_at'=>$terminal?now():null])->save();throw $e;}
    }
}
