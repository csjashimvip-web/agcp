<?php
namespace Modules\Integrations\Application\Listeners;
use Modules\Integrations\Application\Jobs\DeliverOutboundWebhook;
use Modules\Integrations\Infrastructure\Models\WebhookDelivery;
use Modules\Integrations\Infrastructure\Models\WebhookEndpoint;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
final class FanoutOutboxWebhooks
{
    public function handle(OutboxMessagePublished $event):void
    {
        if(!$event->tenantId)return;$endpoints=WebhookEndpoint::query()->where('tenant_id',$event->tenantId)->where('status','active')->whereHas('subscriptions',fn($q)=>$q->where('enabled',true)->whereIn('event_name',[$event->eventName,'*']))->get();
        foreach($endpoints as $endpoint){$envelope=['id'=>$event->eventId,'type'=>$event->eventName,'schema_version'=>$event->schemaVersion,'tenant_id'=>$event->tenantId,'occurred_at'=>now()->toAtomString(),'data'=>$event->payload,'metadata'=>$event->metadata];$payload=json_encode($envelope,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$delivery=WebhookDelivery::query()->firstOrCreate(['webhook_endpoint_id'=>$endpoint->id,'event_id'=>$event->eventId],['tenant_id'=>$event->tenantId,'event_name'=>$event->eventName,'schema_version'=>$event->schemaVersion,'status'=>'queued','payload_hash'=>hash('sha256',$payload),'payload'=>$payload]);if($delivery->wasRecentlyCreated)DeliverOutboundWebhook::dispatch($delivery->id);}
    }
}
