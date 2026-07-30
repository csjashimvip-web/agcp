<?php
namespace Modules\Shared\Infrastructure;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Shared\Infrastructure\Console\PublishOutboxMessages;
use Modules\Shared\Infrastructure\Outbox\DatabaseOutboxRecorder;
class SharedServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->bind(OutboxRecorder::class, DatabaseOutboxRecorder::class); }
    public function boot(): void { if ($this->app->runningInConsole()) $this->commands([PublishOutboxMessages::class]); }
}
