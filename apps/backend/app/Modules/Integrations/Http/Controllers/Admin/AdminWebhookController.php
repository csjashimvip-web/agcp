<?php
namespace Modules\Integrations\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Integrations\Application\Jobs\DeliverOutboundWebhook;
use Modules\Integrations\Application\Services\WebhookUrlValidator;
use Modules\Integrations\Infrastructure\Models\WebhookDelivery;
use Modules\Integrations\Infrastructure\Models\WebhookEndpoint;
use Modules\Tenancy\Application\TenantContext;
final class AdminWebhookController extends Controller
{
    public function index(TenantContext $context):array{$id=$context->requireId();return ['data'=>['endpoints'=>WebhookEndpoint::query()->with('subscriptions')->where('tenant_id',$id)->latest()->get(),'deliveries'=>WebhookDelivery::query()->with('endpoint:id,name')->where('tenant_id',$id)->latest()->limit(100)->get()]];}
    public function store(Request $request,TenantContext $context,WebhookUrlValidator $validator):array{$data=$request->validate(['name'=>['required','string','max:160'],'url'=>['required','string','max:1500'],'events'=>['required','array','min:1'],'events.*'=>['string','max:160'],'timeout_seconds'=>['nullable','integer','min:1','max:30'],'max_attempts'=>['nullable','integer','min:1','max:12']]);$validator->validate($data['url']);$secret=Str::random(64);$endpoint=WebhookEndpoint::query()->create(['tenant_id'=>$context->requireId(),'name'=>$data['name'],'url'=>$data['url'],'signing_secret'=>$secret,'status'=>'active','timeout_seconds'=>$data['timeout_seconds']??10,'max_attempts'=>$data['max_attempts']??8]);foreach(array_unique($data['events']) as $event)$endpoint->subscriptions()->create(['tenant_id'=>$context->requireId(),'event_name'=>$event,'enabled'=>true]);return ['data'=>$endpoint->load('subscriptions'),'signing_secret'=>$secret];}
    public function update(Request $request,TenantContext $context,WebhookEndpoint $endpoint,WebhookUrlValidator $validator):array{abort_unless($endpoint->tenant_id===$context->requireId(),404);$data=$request->validate(['name'=>['sometimes','string','max:160'],'url'=>['sometimes','string','max:1500'],'status'=>['sometimes','in:active,paused,disabled'],'timeout_seconds'=>['sometimes','integer','min:1','max:30'],'max_attempts'=>['sometimes','integer','min:1','max:12']]);if(isset($data['url']))$validator->validate($data['url']);$endpoint->update($data);return ['data'=>$endpoint->fresh('subscriptions')];}
    public function rotate(TenantContext $context,WebhookEndpoint $endpoint):array{abort_unless($endpoint->tenant_id===$context->requireId(),404);$secret=Str::random(64);$endpoint->update(['signing_secret'=>$secret]);return ['data'=>['endpoint_id'=>$endpoint->id,'signing_secret'=>$secret]];}
    public function retry(TenantContext $context,WebhookDelivery $delivery):array{abort_unless($delivery->tenant_id===$context->requireId(),404);$delivery->forceFill(['status'=>'queued','next_attempt_at'=>now(),'failed_at'=>null,'last_error'=>null])->save();DeliverOutboundWebhook::dispatch($delivery->id);return ['data'=>$delivery];}
}
