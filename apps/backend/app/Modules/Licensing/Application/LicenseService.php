<?php

namespace App\Modules\Licensing\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LicenseService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {
    }

    /**
     * @return array{record:object,token:string}
     */
    public function issue(
        int $tenantId,
        string $edition,
        ?string $domain = null,
        ?string $serverFingerprint = null,
        ?string $expiresAt = null,
    ): array {
        $publicId = (string) Str::ulid();
        $secret = Str::random(64);

        $id = DB::table('license_keys')->insertGetId([
            'tenant_id' => $tenantId,
            'license_uuid' => (string) Str::uuid(),
            'public_id' => $publicId,
            'secret_hash' => hash('sha256', $secret),
            'edition' => $edition,
            'status' => 'active',
            'bound_domain' => $domain,
            'bound_server_fingerprint' => $serverFingerprint,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
            'entitlement_snapshot' => json_encode(
                $this->entitlements->resolveForTenant($tenantId),
                JSON_THROW_ON_ERROR
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'record' => DB::table('license_keys')->where('id', $id)->first(),
            'token' => 'agcp_license_'.$publicId.'.'.$secret,
        ];
    }

    public function validate(
        string $token,
        ?string $domain = null,
        ?string $serverFingerprint = null,
    ): object {
        if (! str_starts_with($token, 'agcp_license_')
            || ! str_contains($token, '.')) {
            throw new RuntimeException('Invalid license token.');
        }

        [$publicPart, $secret] = explode('.', $token, 2);
        $publicId = substr($publicPart, strlen('agcp_license_'));

        $license = DB::table('license_keys')
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->first();

        if (! $license
            || ! hash_equals(
                (string) $license->secret_hash,
                hash('sha256', $secret)
            )) {
            throw new RuntimeException('License token is invalid.');
        }

        if ($license->expires_at && now()->greaterThan($license->expires_at)) {
            throw new RuntimeException('License has expired.');
        }

        if ($license->bound_domain
            && $domain
            && strtolower($license->bound_domain) !== strtolower($domain)) {
            throw new RuntimeException('License domain binding mismatch.');
        }

        if ($license->bound_server_fingerprint
            && $serverFingerprint
            && ! hash_equals(
                (string) $license->bound_server_fingerprint,
                (string) $serverFingerprint
            )) {
            throw new RuntimeException('License server binding mismatch.');
        }

        DB::table('license_keys')
            ->where('id', $license->id)
            ->update([
                'last_checked_at' => now(),
                'updated_at' => now(),
            ]);

        return $license;
    }
}