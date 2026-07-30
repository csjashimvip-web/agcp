<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('outbox:publish --limit='.env('OUTBOX_BATCH_SIZE', 100))->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('supplier:poll --limit='.env('SUPPLIER_POLL_BATCH_SIZE', 100))->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('supplier:health-check')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('analytics:refresh')->dailyAt((string) config('analytics.refresh_time', '02:10'))->withoutOverlapping()->onOneServer();
