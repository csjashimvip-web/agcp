<?php

use App\Modules\Supplier\Application\Jobs\PollPendingSupplierOrders;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PollPendingSupplierOrders)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();