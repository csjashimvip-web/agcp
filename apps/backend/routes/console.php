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