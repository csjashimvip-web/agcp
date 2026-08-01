<?php

namespace App\Modules\Supplier\Infrastructure\Dhru;

use App\Modules\Supplier\Domain\Contracts\DhruCompatibleProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

final class DhruFusionProvider implements DhruCompatibleProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $apiKey,
    ) {
    }

    public function code(): string
    {
        return 'dhru-fusion';
    }

    public function services(): array
    {
        return $this->call('imeiservicelist');
    }

    public function balance(): ?array
    {
        $response = $this->call('accountinfo');

        if (! isset($response['balance'])) {
            return null;
        }

        return [
            'amount_minor' => (int) round(((float) $response['balance']) * 100),
            'currency' => strtoupper((string) ($response['currency'] ?? 'USD')),
        ];
    }

    public function submit(array $payload): array
    {
        $response = $this->call('placeimeiorder', $payload);

        $externalOrderId = (string) (
            $response['referenceid']
            ?? $response['reference_id']
            ?? $response['id']
            ?? ''
        );

        if ($externalOrderId === '') {
            throw new RuntimeException('Dhru supplier did not return an external order ID.');
        }

        return [
            'external_order_id' => $externalOrderId,
            'status' => 'submitted',
            'raw' => $response,
        ];
    }

    public function status(string $externalOrderId): array
    {
        $response = $this->call('getimeiorder', [
            'ID' => $externalOrderId,
        ]);

        return [
            'status' => strtolower((string) ($response['status'] ?? 'pending')),
            'result' => $response['code'] ?? null,
            'raw' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function call(string $action, array $parameters = []): array
    {
        $response = $this->http
            ->asForm()
            ->timeout(30)
            ->retry(2, 500)
            ->post(rtrim($this->baseUrl, '/').'/api/index.php', [
                'username' => $this->username,
                'apiaccesskey' => $this->apiKey,
                'action' => $action,
                'requestformat' => 'JSON',
                'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ])
            ->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Dhru supplier returned a non-JSON response.');
        }

        return $data;
    }
}