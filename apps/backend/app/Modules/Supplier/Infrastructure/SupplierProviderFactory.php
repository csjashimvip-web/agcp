<?php

namespace App\Modules\Supplier\Infrastructure;

use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory as SupplierProviderFactoryContract;
use App\Modules\Supplier\Domain\Contracts\SupplierProvider;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Supplier\Infrastructure\Dhru\DhruFusionProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class SupplierProviderFactory implements SupplierProviderFactoryContract
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function make(Supplier $supplier): SupplierProvider
    {
        $secret = $this->decrypt($supplier->secret_payload);

        return match ($supplier->driver) {
            'dhru-fusion' => new DhruFusionProvider(
                http: $this->http,
                baseUrl: (string) ($secret['base_url'] ?? ''),
                username: (string) ($secret['username'] ?? ''),
                apiKey: (string) ($secret['api_key'] ?? ''),
            ),
            default => throw new RuntimeException("Unsupported supplier driver: {$supplier->driver}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decrypt(?string $payload): array
    {
        if (! $payload) {
            throw new RuntimeException('Supplier credentials are not configured.');
        }

        $decoded = json_decode(Crypt::decryptString($payload), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Supplier credentials are invalid.');
        }

        return $decoded;
    }
}