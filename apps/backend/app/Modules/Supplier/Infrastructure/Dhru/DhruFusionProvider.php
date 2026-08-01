<?php

namespace App\Modules\Supplier\Infrastructure\Dhru;

use App\Modules\Supplier\Domain\Contracts\DhruCompatibleProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use SimpleXMLElement;

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

        $account = $response['SUCCESS'][0]['AccoutInfo']
            ?? $response['SUCCESS'][0]['AccountInfo']
            ?? $response['SUCCESS']['AccoutInfo']
            ?? $response['SUCCESS']['AccountInfo']
            ?? null;

        if (! is_array($account)) {
            return null;
        }

        $raw = $account['creditraw'] ?? $account['balance'] ?? null;

        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return [
            'amount_minor' => (int) round(((float) $raw) * 100),
            'currency' => strtoupper((string) ($account['currency'] ?? 'USD')),
        ];
    }

    public function submit(array $payload): array
    {
        $parameters = $this->toDhruXml($payload);
        $response = $this->call('placeimeiorder', [
            'parameters' => $parameters,
        ]);

        $success = $response['SUCCESS'][0]
            ?? $response['SUCCESS']
            ?? null;

        $externalOrderId = is_array($success)
            ? (string) ($success['REFERENCEID'] ?? $success['referenceid'] ?? '')
            : '';

        if ($externalOrderId === '') {
            throw new RuntimeException(
                'Dhru supplier did not return a REFERENCEID.'
            );
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
            'parameters' => $this->toDhruXml([
                'ID' => $externalOrderId,
            ]),
        ]);

        $success = $response['SUCCESS'][0]
            ?? $response['SUCCESS']
            ?? [];

        return [
            'status' => strtolower((string) (
                is_array($success) ? ($success['STATUS'] ?? 'pending') : 'pending'
            )),
            'result' => is_array($success) ? ($success['CODE'] ?? null) : null,
            'raw' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function call(string $action, array $extra = []): array
    {
        if ($this->baseUrl === '' || $this->username === '' || $this->apiKey === '') {
            throw new RuntimeException('Dhru credentials are incomplete.');
        }

        $response = $this->http
            ->asMultipart()
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($this->endpoint(), array_merge([
                'username' => $this->username,
                'apiaccesskey' => $this->apiKey,
                'requestformat' => 'JSON',
                'action' => $action,
            ], $extra))
            ->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Dhru supplier returned a non-JSON response.');
        }

        $error = $data['ERROR'][0]['MESSAGE']
            ?? $data['ERROR']['MESSAGE']
            ?? null;

        if (is_string($error) && $error !== '') {
            throw new RuntimeException('Dhru API error: '.$error);
        }

        return $data;
    }

    private function endpoint(): string
    {
        $base = rtrim(trim($this->baseUrl), '/');

        if (str_ends_with($base, '/api/index.php')) {
            return $base;
        }

        return $base.'/api/index.php';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function toDhruXml(array $payload): string
    {
        $xml = new SimpleXMLElement('<PARAMETERS/>');

        foreach ($payload as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }

            $xml->addChild(
                strtoupper((string) $key),
                htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );
        }

        $result = $xml->asXML();

        if (! is_string($result)) {
            throw new RuntimeException('Unable to encode Dhru parameters.');
        }

        return preg_replace('/<\?xml.*?\?>\s*/', '', $result) ?: $result;
    }
}