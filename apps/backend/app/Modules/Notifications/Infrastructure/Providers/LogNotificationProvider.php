<?php
namespace Modules\Notifications\Infrastructure\Providers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Contracts\NotificationProvider;
final class LogNotificationProvider implements NotificationProvider
{
    public function __construct(private readonly string $forChannel='email'){}
    public function channel():string{return $this->forChannel;}
    public function send(string $recipient,array $message):array{ $id='log-'.Str::uuid(); Log::info('AGCP outbound notification',['provider_message_id'=>$id,'channel'=>$this->forChannel,'recipient'=>$recipient,'message'=>$message]); return ['provider_message_id'=>$id,'delivered'=>true]; }
}
