<?php

namespace App\Modules\Platform\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogImportService
{
    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function run(
        int $tenantId,
        ?int $requestedByUserId,
        array $rows,
        bool $dryRun = true,
        string $sourceName = 'api',
    ): object {
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $priceMinor = $row['price_minor'] ?? null;
            $currency = strtoupper(trim((string) ($row['currency'] ?? 'USD')));

            if ($sku === '' || $name === '' || ! is_numeric($priceMinor)) {
                $errors[] = [
                    'row' => $index,
                    'error' => 'sku, name and numeric price_minor are required',
                ];
                continue;
            }

            $valid[] = [
                'sku' => $sku,
                'name' => $name,
                'slug' => Str::slug((string) ($row['slug'] ?? $name)),
                'type' => (string) ($row['type'] ?? 'service'),
                'status' => (string) ($row['status'] ?? 'active'),
                'currency' => $currency,
                'price_minor' => max(0, (int) $priceMinor),
                'cost_minor' => max(0, (int) ($row['cost_minor'] ?? 0)),
            ];
        }

        $jobId = DB::table('data_import_jobs')->insertGetId([
            'tenant_id' => $tenantId,
            'requested_by_user_id' => $requestedByUserId,
            'import_uuid' => (string) Str::uuid(),
            'resource_type' => 'catalog_products',
            'source_name' => $sourceName,
            'dry_run' => $dryRun,
            'status' => $errors === [] ? 'validated' : 'validated_with_errors',
            'rows_total' => count($rows),
            'rows_valid' => count($valid),
            'rows_failed' => count($errors),
            'result' => json_encode(
                ['errors' => $errors],
                JSON_THROW_ON_ERROR
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $dryRun && $errors === []) {
            DB::transaction(function () use ($tenantId, $valid): void {
                foreach ($valid as $row) {
                    DB::table('products')->updateOrInsert(
                        [
                            'tenant_id' => $tenantId,
                            'sku' => $row['sku'],
                        ],
                        $row + [
                            'tenant_id' => $tenantId,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            });

            DB::table('data_import_jobs')
                ->where('id', $jobId)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
        }

        return DB::table('data_import_jobs')->where('id', $jobId)->first();
    }
}