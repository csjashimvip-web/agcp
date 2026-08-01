<?php

namespace App\Modules\Payments\Infrastructure;

use App\Modules\Payments\Domain\Contracts\PaymentProvider;
use RuntimeException;

final class GenericHmacPaymentProvider implements PaymentProvider
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function code(): string
    {
        return 'generic-hmac';
    }

    public function createIntent(array $payload): array
    {
        throw new RuntimeException('Generic HMAC provider is webhook-only.');
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $signature = (string) (
            $headers['x-agcp-signature'][0]
            ?? $headers['X-AGCP-Signature'][0]
            ?? ''
        );

        $expected = hash_hmac('sha256', $payload, $this->secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return ['valid' => false];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! isset($decoded['event_id'], $decoded['type'])) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'event_id' => (string) $decoded['event_id'],
            'event_type' => (string) $decoded['type'],
            'payload' => $decoded,
        ];
    }

    public function refund(string $providerReference, int $amountMinor, string $currency): array
    {
        throw new RuntimeException('Generic HMAC provider does not implement outbound refunds.');
    }
}