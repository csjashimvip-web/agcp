<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Models\WebhookEndpoint;
use Modules\Notifications\Infrastructure\Models\NotificationTemplate;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class EngagementOperationsSeeder extends Seeder
{
    public function run():void
    {
        $tenant=Tenant::query()->where('slug','araabi-global')->firstOrFail();$templates=[
            ['commerce.order.placed','Order {{number}} confirmed','Your order {{number}} for {{currency}} {{total_minor}} minor units has been confirmed.'],
            ['wallet.deposit.approved','Balance added','Your {{currency}} balance was credited by {{amount_minor}} minor units.'],
            ['wallet.adjusted','Wallet adjusted','A controlled wallet adjustment was completed.'],
            ['payments.intent.created','Payment started','Payment {{reference}} was created. Complete it from the payments page.'],
            ['payments.intent.captured','Payment verified','Payment {{reference}} was verified and your wallet was credited.'],
            ['payments.refund.completed','Refund completed','Your payment refund was completed.'],
            ['support.ticket.reply','Support replied to {{ticket_number}}','{{message}}'],
        ];
        foreach($templates as[$event,$subject,$body])foreach(['in_app','email'] as $channel)NotificationTemplate::query()->updateOrCreate(['tenant_id'=>$tenant->id,'event_name'=>$event,'channel'=>$channel,'locale'=>'en','version'=>1],['status'=>'active','subject'=>$subject,'body'=>$body]);
        $endpoint=WebhookEndpoint::query()->firstOrCreate(['tenant_id'=>$tenant->id,'name'=>'Local Event Log'],['url'=>'log://agcp-events','signing_secret'=>Str::random(64),'status'=>'active','timeout_seconds'=>10,'max_attempts'=>8]);$endpoint->subscriptions()->firstOrCreate(['tenant_id'=>$tenant->id,'event_name'=>'*'],['enabled'=>true]);
    }
}
