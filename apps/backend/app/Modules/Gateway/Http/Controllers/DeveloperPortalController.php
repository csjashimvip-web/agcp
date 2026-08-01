<?php

namespace App\Modules\Gateway\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class DeveloperPortalController
{
    public function openApi(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'AGCP Reseller API',
                'version' => '1.0.0',
                'description' => 'Authenticated downstream reseller API for AGCP.',
            ],
            'servers' => [
                ['url' => '/api/reseller/v1'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Use one-time AGCP reseller API token.',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => [
                '/services' => [
                    'get' => [
                        'summary' => 'List active services',
                        'x-agcp-ability' => 'services:read',
                    ],
                ],
                '/balance' => [
                    'get' => [
                        'summary' => 'Get reseller wallet balances',
                        'x-agcp-ability' => 'wallet:read',
                    ],
                ],
                '/orders' => [
                    'post' => [
                        'summary' => 'Create idempotent reseller order',
                        'x-agcp-ability' => 'orders:create',
                    ],
                    'get' => [
                        'summary' => 'List reseller orders',
                        'x-agcp-ability' => 'orders:read',
                    ],
                ],
                '/orders/{orderId}' => [
                    'get' => [
                        'summary' => 'Get one reseller order',
                        'x-agcp-ability' => 'orders:read',
                    ],
                ],
            ],
        ]);
    }
}