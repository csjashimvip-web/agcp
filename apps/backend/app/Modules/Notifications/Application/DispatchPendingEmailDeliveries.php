<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Jobs\DeliverEmailNotification;
use Illuminate\Support\Facades\DB;

final class DispatchPendingEmailDeliveries
{
    public function run(int $limit = 100): int
    {
        $rows = DB::table('notification_channel_deliveries')
            ->where('channel_type', 'email')
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get(['id']);

        foreach ($rows as $row) {
            DB::table('notification_channel_deliveries')
                ->where('id', $row->id)
                ->update([
                    'status' => 'dispatching',
                    'updated_at' => now(),
                ]);

            DeliverEmailNotification::dispatch((int) $row->id)
                ->onQueue('external-delivery');
        }

        return $rows->count();
    }
}