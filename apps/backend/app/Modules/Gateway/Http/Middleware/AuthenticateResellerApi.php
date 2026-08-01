<?php

namespace App\Modules\Gateway\Http\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateResellerApi
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());

        if (! str_starts_with($token, 'agcp_') || ! str_contains($token, '.')) {
            return new JsonResponse(['message' => 'Invalid API credential.'], 401);
        }

        [$publicPart, $secret] = explode('.', $token, 2);
        $publicId = substr($publicPart, 5);

        if ($publicId === '' || $secret === '') {
            return new JsonResponse(['message' => 'Invalid API credential.'], 401);
        }

        $client = DB::table('reseller_api_clients')
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $client) {
            return new JsonResponse(['message' => 'Invalid API credential.'], 401);
        }

        if (! hash_equals(
            (string) $client->secret_hash,
            hash('sha256', $secret)
        )) {
            return new JsonResponse(['message' => 'Invalid API credential.'], 401);
        }

        $membership = DB::table('tenant_memberships')
            ->where('tenant_id', $client->tenant_id)
            ->where('user_id', $client->user_id)
            ->where('status', 'active')
            ->exists();

        if (! $membership) {
            return new JsonResponse(['message' => 'API client membership is inactive.'], 403);
        }

        $tenant = Tenant::query()
            ->whereKey($client->tenant_id)
            ->where('status', 'active')
            ->first();

        $user = User::query()
            ->whereKey($client->user_id)
            ->where('status', 'active')
            ->first();

        if (! $tenant || ! $user) {
            return new JsonResponse(['message' => 'API client identity is inactive.'], 403);
        }

        $this->tenantContext->set($tenant);

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('agcp_api_client', $client);
        $request->attributes->set('agcp_tenant', $tenant);

        DB::table('reseller_api_clients')
            ->where('id', $client->id)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

        return $next($request);
    }
}