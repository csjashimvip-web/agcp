<?php

namespace App\Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('notification_deliveries')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'event_id',
                'channel',
                'template',
                'status',
                'title',
                'message',
                'delivered_at',
                'read_at',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function read(Request $request, int $notificationId): JsonResponse
    {
        $updated = DB::table('notification_deliveries')
            ->where('id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        abort_unless($updated === 1, 404);

        return response()->json([
            'data' => [
                'read' => true,
                'notification_id' => $notificationId,
            ],
        ]);
    }
}