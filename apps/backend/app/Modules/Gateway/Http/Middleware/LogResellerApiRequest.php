<?php

namespace App\Modules\Gateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LogResellerApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $response = $next($request);

        $client = $request->attributes->get('agcp_api_client');

        if ($client) {
            $durationMs = (int) round(
                (hrtime(true) - $started) / 1_000_000
            );

            DB::table('reseller_api_request_logs')->insert([
                'tenant_id' => $client->tenant_id,
                'reseller_api_client_id' => $client->id,
                'request_id' => (string) Str::uuid(),
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 512),
                'response_status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}