<?php

namespace App\Modules\Supplier\Application;

use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Domain\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DhruServiceSync
{
    public function __construct(
        private readonly SupplierProviderFactory $providers,
    ) {
    }

    /**
     * @return array{
     *   run_id:int,
     *   discovered:int,
     *   created:int,
     *   updated:int
     * }
     */
    public function sync(Supplier $supplier): array
    {
        $runId = DB::table('supplier_sync_runs')->insertGetId([
            'tenant_id' => $supplier->tenant_id,
            'supplier_id' => $supplier->id,
            'sync_uuid' => (string) Str::uuid(),
            'type' => 'services',
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $raw = $this->providers->make($supplier)->services();
            $services = $this->flatten($raw);

            $created = 0;
            $updated = 0;

            foreach ($services as $service) {
                $existing = DB::table('supplier_service_inbox')
                    ->where('supplier_id', $supplier->id)
                    ->where(
                        'external_service_id',
                        $service['external_service_id']
                    )
                    ->first();

                $values = [
                    'tenant_id' => $supplier->tenant_id,
                    'supplier_id' => $supplier->id,
                    'external_service_id' => $service['external_service_id'],
                    'external_name' => $service['external_name'],
                    'cost_minor' => $service['cost_minor'],
                    'currency' => $service['currency'],
                    'metadata' => json_encode(
                        $service['metadata'],
                        JSON_THROW_ON_ERROR
                    ),
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('supplier_service_inbox')
                        ->where('id', $existing->id)
                        ->update($values);

                    $updated++;

                    continue;
                }

                DB::table('supplier_service_inbox')->insert([
                    ...$values,
                    'status' => 'unmapped',
                    'mapped_product_id' => null,
                    'created_at' => now(),
                ]);

                $created++;
            }

            DB::table('supplier_sync_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'completed',
                    'discovered' => count($services),
                    'created' => $created,
                    'updated' => $updated,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                'run_id' => $runId,
                'discovered' => count($services),
                'created' => $created,
                'updated' => $updated,
            ];
        } catch (Throwable $e) {
            DB::table('supplier_sync_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            throw $e;
        }
    }

    /**
     * @return array<int, array{
     *   external_service_id:string,
     *   external_name:string,
     *   cost_minor:int,
     *   currency:string,
     *   metadata:array<string,mixed>
     * }>
     */
    private function flatten(array $raw): array
    {
        $list = $raw['SUCCESS'][0]['LIST']
            ?? $raw['SUCCESS']['LIST']
            ?? null;

        if (! is_array($list)) {
            throw new RuntimeException(
                'Dhru service list response does not contain SUCCESS.LIST.'
            );
        }

        $result = [];

        foreach ($list as $groupName => $group) {
            if (! is_array($group)) {
                continue;
            }

            $services = $group['SERVICES'] ?? [];

            if (! is_array($services)) {
                continue;
            }

            foreach ($services as $serviceKey => $service) {
                if (! is_array($service)) {
                    continue;
                }

                $externalId = (string) (
                    $service['SERVICEID']
                    ?? $service['ID']
                    ?? $serviceKey
                );

                if ($externalId === '') {
                    continue;
                }

                $credit = $service['CREDIT'] ?? 0;

                $result[] = [
                    'external_service_id' => $externalId,
                    'external_name' => (string) (
                        $service['SERVICENAME']
                        ?? $service['NAME']
                        ?? "Service {$externalId}"
                    ),
                    'cost_minor' => is_numeric($credit)
                        ? (int) round(((float) $credit) * 100)
                        : 0,
                    'currency' => strtoupper(
                        (string) ($service['CURRENCY'] ?? 'USD')
                    ),
                    'metadata' => [
                        'group' => (string) $groupName,
                        'service_type' => $service['SERVICETYPE'] ?? null,
                        'time' => $service['TIME'] ?? null,
                        'info' => $service['INFO'] ?? null,
                        'raw' => $service,
                    ],
                ];
            }
        }

        return $result;
    }
}