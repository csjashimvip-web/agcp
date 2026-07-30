<?php
namespace Modules\Notifications\Application\Listeners;
use App\Models\User;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
use Modules\Notifications\Application\Services\NotificationService;
use Modules\Wallet\Infrastructure\Models\Wallet;
final class RouteOutboxNotifications
{
    private const EVENTS=['commerce.order.placed','wallet.deposit.approved','wallet.adjusted','payments.intent.created','payments.intent.captured','payments.refund.completed'];
    public function __construct(private readonly NotificationService $notifications){}
    public function handle(OutboxMessagePublished $event):void
    {
        if(!in_array($event->eventName,self::EVENTS,true)||!$event->tenantId)return;$userId=$event->payload['user_id']??null;
        if(!$userId&&!empty($event->payload['wallet_id']))$userId=Wallet::query()->whereKey($event->payload['wallet_id'])->value('owner_id');
        if(!$userId)return;$user=User::query()->find($userId);if(!$user)return;
        $data=$event->payload+['action_url'=>$this->actionUrl($event->eventName),'severity'=>'info'];
        $this->notifications->notify($user,$event->tenantId,$event->eventName,$data,['in_app','email'],$event->eventId);
    }
    private function actionUrl(string $name):string{return str_starts_with($name,'commerce.')?'/orders':(str_starts_with($name,'payments.')?'/payments':'/wallet');}
}
