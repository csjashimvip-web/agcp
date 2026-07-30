<?php
namespace Modules\Notifications\Infrastructure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Application\Listeners\RouteOutboxNotifications;
use Modules\Notifications\Application\Services\NotificationProviderRegistry;
use Modules\Notifications\Infrastructure\Providers\LogNotificationProvider;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
final class NotificationsServiceProvider extends ServiceProvider
{
    public function register():void{$this->app->singleton(NotificationProviderRegistry::class,fn()=>new NotificationProviderRegistry([new LogNotificationProvider('email'),new LogNotificationProvider('sms'),new LogNotificationProvider('web_push')]));}
    public function boot():void{Event::listen(OutboxMessagePublished::class,RouteOutboxNotifications::class);}
}
