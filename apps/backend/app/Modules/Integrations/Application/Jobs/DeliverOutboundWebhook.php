<?php
namespace Modules\Integrations\Application\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Infrastructure\Models\WebhookDelivery;
use Throwable;
final class DeliverOutboundWebhook implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=8; public array $backoff=[30,120,600,1800,3600,10800,21600];
    public function __construct(public readonly string $deliveryId){$this->onQueue('webhooks');}
    public function handle():void
    {
        $delivery=WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);if(!$delivery||in_array($delivery->status,['delivered','dead'],true)||!$delivery->endpoint||$delivery->endpoint->status!=='active')return;$endpoint=$delivery->endpoint;$payload=(string)$delivery->payload;$timestamp=(string)now()->timestamp;$signature=hash_hmac('sha256',$timestamp.'.'.$payload,$endpoint->signing_secret);
        $delivery->forceFill(['status'=>'sending','attempts'=>$delivery->attempts+1,'last_error'=>null])->save();
        try{if(str_starts_with($endpoint->url,'log://')){Log::info('AGCP outbound webhook',['endpoint'=>$endpoint->name,'event'=>$delivery->event_name,'event_id'=>$delivery->event_id,'payload'=>json_decode($payload,true)]);$status=204;$body='logged';}else{$response=Http::timeout($endpoint->timeout_seconds)->withOptions(['verify'=>$endpoint->verify_tls])->withHeaders(array_merge($endpoint->custom_headers??[],['User-Agent'=>'AGCP-Webhooks/1.0','X-AGCP-Event-ID'=>$delivery->event_id,'X-AGCP-Event'=>$delivery->event_name,'X-AGCP-Timestamp'=>$timestamp,'X-AGCP-Signature'=>'v1='.$signature,'Content-Type'=>'application/json']))->withBody($payload,'application/json')->post($endpoint->url);$status=$response->status();$body=mb_substr($response->body(),0,10000);if($status<200||$status>=300)throw new \RuntimeException('Webhook returned HTTP '.$status);}
            $delivery->forceFill(['status'=>'delivered','response_status'=>$status,'response_body'=>$body,'delivered_at'=>now()])->save();$endpoint->forceFill(['last_success_at'=>now()])->save();}
        catch(Throwable $e){$terminal=$delivery->attempts>=$endpoint->max_attempts;$delivery->forceFill(['status'=>$terminal?'dead':'failed','last_error'=>mb_substr($e->getMessage(),0,2000),'next_attempt_at'=>$terminal?null:now()->addSeconds($this->backoff[min($delivery->attempts-1,count($this->backoff)-1)]),'failed_at'=>$terminal?now():null])->save();$endpoint->forceFill(['last_failure_at'=>now()])->save();throw $e;}
    }
}
