<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('outbox:publish --limit='.env('OUTBOX_BATCH_SIZE', 100))->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('supplier:poll --limit='.env('SUPPLIER_POLL_BATCH_SIZE', 100))->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('supplier:health-check')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('analytics:refresh')->dailyAt((string) config('analytics.refresh_time', '02:10'))->withoutOverlapping()->onOneServer();

Schedule::command('payments:reconcile')->dailyAt((string) config('payments.reconciliation_time', '03:10'))->withoutOverlapping()->onOneServer();

Schedule::command('ops:snapshot')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('invoices:generate-missing --limit=100')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('reports:run-due --limit='.env('REPORT_SCHEDULE_BATCH_SIZE',50))->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reports:purge-expired --limit=500')->dailyAt('04:10')->withoutOverlapping()->onOneServer();

Schedule::command('reliability:heartbeat scheduler')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('reliability:backup')->dailyAt((string) config('reliability.backup.daily_time', '01:30'))->withoutOverlapping()->onOneServer()->when(fn (): bool => (bool) config('reliability.backup.enabled'));
Schedule::command('reliability:verify-backup --latest')->dailyAt('02:30')->withoutOverlapping()->onOneServer()->when(fn (): bool => (bool) config('reliability.backup.enabled'));
Schedule::command('reliability:check --persist')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('reliability:purge-backups --limit=100')->dailyAt('05:10')->withoutOverlapping()->onOneServer();
