<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integrations\Application\Listeners\FanoutOutboxWebhooks;
use Modules\Integrations\Infrastructure\Models\WebhookEndpoint;
use Modules\Notifications\Application\Services\NotificationService;
use Modules\Notifications\Infrastructure\Models\NotificationTemplate;
use Modules\Notifications\Infrastructure\Models\UserNotification;
use Modules\Observability\Application\Services\OperationsSnapshotService;
use Modules\Support\Application\Services\SupportTicketService;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
uses(RefreshDatabase::class);
function engagementFixture():array{$tenant=Tenant::query()->create(['name'=>'Engagement Tenant','slug'=>'engagement-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);$user=User::query()->create(['name'=>'Customer','email'=>'engagement@example.test','password'=>'Secret123!','status'=>'active','email_verified_at'=>now()]);return compact('tenant','user');}
it('creates an in-app notification from a versioned template',function(){$f=engagementFixture();NotificationTemplate::query()->create(['tenant_id'=>$f['tenant']->id,'event_name'=>'test.event','channel'=>'in_app','locale'=>'en','version'=>1,'status'=>'active','subject'=>'Hello {{name}}','body'=>'Reference {{reference}}']);app(NotificationService::class)->notify($f['user'],$f['tenant']->id,'test.event',['name'=>'Customer','reference'=>'ABC'],['in_app'],'dedup-1');$notification=UserNotification::query()->firstOrFail();expect($notification->title)->toBe('Hello Customer')->and($notification->body)->toBe('Reference ABC');});
it('creates a signed webhook delivery only once per endpoint and event',function(){$f=engagementFixture();$endpoint=WebhookEndpoint::query()->create(['tenant_id'=>$f['tenant']->id,'name'=>'Log','url'=>'log://test','signing_secret'=>'secret','status'=>'active']);$endpoint->subscriptions()->create(['tenant_id'=>$f['tenant']->id,'event_name'=>'test.event','enabled'=>true]);$event=new OutboxMessagePublished('event-1','test.event',1,['value'=>1],[],$f['tenant']->id);app(FanoutOutboxWebhooks::class)->handle($event);app(FanoutOutboxWebhooks::class)->handle($event);expect($endpoint->fresh()->subscriptions)->toHaveCount(1)->and(\Modules\Integrations\Infrastructure\Models\WebhookDelivery::query()->count())->toBe(1);});
it('creates and replies to a support ticket with SLA timestamps',function(){$f=engagementFixture();$agent=User::query()->create(['name'=>'Agent','email'=>'agent@example.test','password'=>'Secret123!','status'=>'active']);$ticket=app(SupportTicketService::class)->create($f['tenant']->id,$f['user'],['subject'=>'Order help','message'=>'Please check my order','priority'=>'high']);$ticket=app(SupportTicketService::class)->reply($ticket,$agent,'We are checking this.',false,true);expect($ticket->messages)->toHaveCount(2)->and($ticket->first_responded_at)->not->toBeNull()->and($ticket->status->value)->toBe('pending_customer');});
it('captures an auditable operations snapshot',function(){$f=engagementFixture();$snapshot=app(OperationsSnapshotService::class)->capture($f['tenant']->id);expect($snapshot->status)->toBeIn(['healthy','degraded','critical'])->and($snapshot->checks)->toHaveKeys(['database','redis']);});
