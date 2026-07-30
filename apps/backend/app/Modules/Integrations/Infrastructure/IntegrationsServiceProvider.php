<?php
namespace Modules\Integrations\Infrastructure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Integrations\Application\Listeners\FanoutOutboxWebhooks;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
final class IntegrationsServiceProvider extends ServiceProvider { public function boot():void{Event::listen(OutboxMessagePublished::class,FanoutOutboxWebhooks::class);} }
