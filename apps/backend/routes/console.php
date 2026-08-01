<?php

use App\Modules\Reliability\Application\OutboxPublisher;
use App\Modules\Supplier\Application\Jobs\PollPendingSupplierOrders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'agcp:outbox-publish {--limit=100}',
    function (OutboxPublisher $publisher): int {
        $result = $publisher->publish((int) $this->option('limit'));

        $this->info(
            "Outbox published={$result['published']} failed={$result['failed']}"
        );

        return $result['failed'] > 0 ? 1 : 0;
    }
)->purpose('Publish pending AGCP transactional outbox events.');

Schedule::job(new PollPendingSupplierOrders)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('agcp:outbox-publish --limit=100')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// AGCP ENTERPRISE HARDENING V1
Artisan::command(
    'agcp:reliability-snapshot',
    function (\App\Modules\Reliability\Application\ReadinessService $readiness): int {
        $snapshot = $readiness->probe(null, true);
        $this->line(json_encode($snapshot, JSON_PRETTY_PRINT));

        return $snapshot['ready'] ? 0 : 1;
    }
)->purpose('Capture an AGCP operational readiness snapshot.');

Artisan::command(
    'agcp:backup-register {path} {sha256} {sizeBytes}',
    function (
        \App\Modules\Reliability\Application\BackupCatalogService $backups
    ): int {
        $row = $backups->register(
            (string) $this->argument('path'),
            (string) $this->argument('sha256'),
            (int) $this->argument('sizeBytes'),
        );

        $this->info('Registered backup #'.$row->id);

        return 0;
    }
)->purpose('Register a completed backup in the AGCP backup catalog.');

Artisan::command(
    'agcp:restore-drill {backupId} {--passed=1} {--evidence=manual verification}',
    function (
        \App\Modules\Reliability\Application\BackupCatalogService $backups
    ): int {
        $passed = filter_var(
            $this->option('passed'),
            FILTER_VALIDATE_BOOLEAN
        );

        $row = $backups->recordDrill(
            (int) $this->argument('backupId'),
            $passed,
            (string) $this->option('evidence'),
        );

        $this->info('Restore drill '.$row->status);

        return $passed ? 0 : 1;
    }
)->purpose('Record evidence from a restore drill.');

Schedule::command('agcp:reliability-snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
