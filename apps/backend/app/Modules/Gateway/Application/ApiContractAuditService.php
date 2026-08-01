<?php

namespace App\Modules\Gateway\Application;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;

final class ApiContractAuditService
{
    public function __construct(
        private readonly Router $router,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function audit(bool $persist = true): array
    {
        $contracts = $this->contracts();
        $routes = collect($this->router->getRoutes()->getRoutes());

        $missing = [];
        $captured = 0;

        foreach ($contracts as $contract) {
            $exists = $routes->contains(function ($route) use ($contract): bool {
                return in_array($contract['method'], $route->methods(), true)
                    && $route->uri() === $contract['path'];
            });

            if (! $exists) {
                $missing[] = $contract['key'];
                continue;
            }

            if ($persist) {
                $schema = [
                    'method' => $contract['method'],
                    'path' => $contract['path'],
                    'auth' => $contract['auth'],
                    'idempotency' => $contract['idempotency'],
                ];

                DB::table('api_contract_snapshots')->updateOrInsert(
                    [
                        'contract_key' => $contract['key'],
                        'version' => 'v1',
                    ],
                    [
                        'method' => $contract['method'],
                        'path' => $contract['path'],
                        'schema_hash' => hash(
                            'sha256',
                            json_encode($schema, JSON_THROW_ON_ERROR)
                        ),
                        'schema' => json_encode(
                            $schema,
                            JSON_THROW_ON_ERROR
                        ),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $captured++;
            }
        }

        return [
            'total' => count($contracts),
            'captured' => $captured,
            'missing' => $missing,
            'passed' => $missing === [],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function contracts(): array
    {
        return [
            [
                'key' => 'platform.readiness',
                'method' => 'GET',
                'path' => 'api/v1/platform/readiness',
                'auth' => 'public',
                'idempotency' => false,
            ],
            [
                'key' => 'checkout.create',
                'method' => 'POST',
                'path' => 'api/v1/checkout',
                'auth' => 'sanctum+tenant',
                'idempotency' => true,
            ],
            [
                'key' => 'reseller.services',
                'method' => 'GET',
                'path' => 'api/reseller/v1/services',
                'auth' => 'reseller-token',
                'idempotency' => false,
            ],
            [
                'key' => 'reseller.orders.create',
                'method' => 'POST',
                'path' => 'api/reseller/v1/orders',
                'auth' => 'reseller-token',
                'idempotency' => true,
            ],
            [
                'key' => 'mobile.bootstrap',
                'method' => 'GET',
                'path' => 'api/mobile/v1/bootstrap',
                'auth' => 'sanctum+tenant',
                'idempotency' => false,
            ],
        ];
    }
}