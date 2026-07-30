<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('outbox:publish --limit='.env('OUTBOX_BATCH_SIZE', 100))->everyMinute()->withoutOverlapping()->onOneServer();
