<?php

namespace Modules\Reliability\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Modules\Reliability\Infrastructure\Console\CreateDatabaseBackup;
use Modules\Reliability\Infrastructure\Console\PurgeExpiredBackups;
use Modules\Reliability\Infrastructure\Console\RecordRuntimeHeartbeat;
use Modules\Reliability\Infrastructure\Console\RunReleaseCheck;
use Modules\Reliability\Infrastructure\Console\VerifyDatabaseBackup;

final class ReliabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateDatabaseBackup::class,
                VerifyDatabaseBackup::class,
                RunReleaseCheck::class,
                RecordRuntimeHeartbeat::class,
                PurgeExpiredBackups::class,
            ]);
        }
    }
}
