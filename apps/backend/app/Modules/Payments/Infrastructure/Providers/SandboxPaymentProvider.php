<?php
namespace Modules\Payments\Infrastructure\Providers;

use Illuminate\Validation\ValidationException;
use Modules\Payments\Domain\Contracts\PaymentProvider;
use Modules\Payments\Domain\Contracts\RefundablePaymentProvider;

final class SandboxPaymentProvider implements PaymentProvider, RefundablePaymentProvider
{
    public function code(): string { return 'sandbox'; }

    public function createPayment(string $reference, int $amountMinor, string $currency, array $context = []): array
    {
        return [
            'provider_payment_id' => 'sbx_'.strtolower(str_replace('-', '', $reference)),
            'checkout_url' => '/payments?reference='.urlencode($reference),
            'status' => 'pending',
            'expires_at' => now()->addMinutes((int) ($context['expiry_minutes'] ?? 30))->toAtomString(),
            'metadata' => ['sandbox' => true, 'amount_minor' => $amountMinor, 'currency' => strtoupper($currency)],
        ];
    }

    public function verifyWebhook(string $payload, array $headers, array $context = []): array
    {
        $timestamp = (int) ($headers['x-agcp-timestamp'] ?? 0);
        $signature = (string) ($headers['x-agcp-signature'] ?? '');
        $secret = (string) ($context['webhook_secret'] ?? '');
        $tolerance = max(30, (int) ($context['tolerance_seconds'] ?? 300));

        if ($timestamp <= 0 || abs(now()->timestamp - $timestamp) > $tolerance) {
            throw ValidationException::withMessages(['webhook' => 'Webhook timestamp is outside the accepted replay window.']);
        }
        if ($secret === '' || $signature === '') {
            throw ValidationException::withMessages(['webhook' => 'Webhook signature is missing.']);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        if (! hash_equals($expected, $provided)) {
            throw ValidationException::withMessages(['webhook' => 'Webhook signature is invalid.']);
        }

        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        foreach (['event_id', 'type', 'reference', 'amount_minor', 'currency'] as $field) {
            if (! array_key_exists($field, $event)) {
                throw ValidationException::withMessages(['webhook' => 'Webhook payload is missing '.$field.'.']);
            }
        }

        return [
            'event_id' => (string) $event['event_id'],
            'type' => (string) $event['type'],
            'reference' => (string) $event['reference'],
            'provider_payment_id' => isset($event['provider_payment_id']) ? (string) $event['provider_payment_id'] : null,
            'amount_minor' => (int) $event['amount_minor'],
            'currency' => strtoupper((string) $event['currency']),
            'occurred_at' => isset($event['occurred_at']) ? (string) $event['occurred_at'] : null,
            'metadata' => is_array($event['metadata'] ?? null) ? $event['metadata'] : [],
        ];
    }

    public function refundPayment(string $providerPaymentId, string $reference, int $amountMinor, string $currency, array $context = []): array
    {
        return [
            'provider_refund_id' => 'sbr_'.strtolower(str_replace('-', '', $reference)),
            'status' => 'completed',
            'metadata' => ['sandbox' => true, 'provider_payment_id' => $providerPaymentId, 'amount_minor' => $amountMinor, 'currency' => strtoupper($currency)],
        ];
    }
}
