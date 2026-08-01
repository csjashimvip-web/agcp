<?php

namespace App\Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PrivacyRequestService
{
    public function create(
        int $tenantId,
        int $userId,
        string $type,
        ?string $note = null,
    ): object {
        $id = DB::table('privacy_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'request_uuid' => (string) Str::uuid(),
            'type' => $type,
            'status' => 'submitted',
            'request_note' => $note,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('privacy_requests')->where('id', $id)->first();
    }

    public function review(
        int $tenantId,
        int $requestId,
        string $status,
        ?string $note = null,
    ): object {
        DB::table('privacy_requests')
            ->where('tenant_id', $tenantId)
            ->where('id', $requestId)
            ->update([
                'status' => $status,
                'review_note' => $note,
                'reviewed_at' => now(),
                'completed_at' => $status === 'completed' ? now() : null,
                'updated_at' => now(),
            ]);

        return DB::table('privacy_requests')
            ->where('tenant_id', $tenantId)
            ->where('id', $requestId)
            ->firstOrFail();
    }
}