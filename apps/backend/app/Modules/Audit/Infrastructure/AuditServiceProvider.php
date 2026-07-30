<?php
namespace Modules\Audit\Infrastructure;
use Illuminate\Support\ServiceProvider;
use Modules\Audit\Application\AuditLogger;
class AuditServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->bind(AuditLogger::class, DatabaseAuditLogger::class); }
}
