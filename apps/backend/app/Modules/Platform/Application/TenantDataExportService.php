<?php

namespace App\Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class TenantDataExportService
{
    public function create(
        int $tenantId,
        ?int $requestedByUserId = null,
    ): object {
        $uuid = (string) Str::uuid();

        $id = DB::table('data_export_jobs')->insertGetId([
            'tenant_id' => $tenantId,
            'requested_by_user_id' => $requestedByUserId,
            'export_uuid' => $uuid,
            'scope' => 'tenant_portability',
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $payload = [
                'version' => 1,
                'tenant_id' => $tenantId,
                'generated_at' => now()->toIso8601String(),
                'products' => DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->get(),
                'orders' => DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->get(),
                'wallets' => DB::table('wallets')
                    ->where('tenant_id', $tenantId)
                    ->get([
                        'id',
                        'user_id',
                        'currency',
                        'status',
                        'available_balance_minor',
                        'held_balance_minor',
                        'created_at',
                        'updated_at',
                    ]),
                'support_tickets' => DB::table('support_tickets')
                    ->where('tenant_id', $tenantId)
                    ->get(),
            ];

            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            $path = 'private/exports/'.$uuid.'.json';

            Storage::disk('local')->put($path, $json);

            DB::table('data_export_jobs')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'file_path' => $path,
                    'sha256' => hash('sha256', $json),
                    'size_bytes' => strlen($json),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            DB::table('data_export_jobs')
                ->where('id', $id)
                ->update([
                    'status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 5000),
                    'updated_at' => now(),
                ]);

            throw $e;
        }

        return DB::table('data_export_jobs')->where('id', $id)->first();
    }
}