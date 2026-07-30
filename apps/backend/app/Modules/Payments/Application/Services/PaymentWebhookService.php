<?php
namespace Modules\Payments\Application\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Payments\Domain\Enums\PaymentWebhookStatus;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Payments\Infrastructure\Models\PaymentWebhook;
use Throwable;

final class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentSettlementService $settlements,
    ) {}

    /** @param array<string,mixed> $headers */
    public function ingest(PaymentProviderAccount $account, string $payload, array $headers): PaymentWebhook
    {
        $normalized = $this->normalizeHeaders($headers);
        $provider = $this->providers->get($account->provider);
        try {
            $event = $provider->verifyWebhook($payload, $normalized, [
                'webhook_secret' => $account->webhook_secret,
                'credentials' => $account->credentials ?? [],
                'mode' => $account->mode,
                'tolerance_seconds' => (int) config('payments.webhook_tolerance_seconds', 300),
            ]);
        } catch (Throwable $e) {
            PaymentWebhook::query()->create([
                'tenant_id' => $account->tenant_id,
                'provider_account_id' => $account->id,
                'external_event_id' => 'rejected-'.Str::uuid(),
                'event_type' => 'unknown',
                'status' => PaymentWebhookStatus::Rejected,
                'signature_hash' => isset($normalized['x-agcp-signature']) ? hash('sha256', $normalized['x-agcp-signature']) : null,
                'payload_hash' => hash('sha256', $payload),
                'payload' => $this->safePayload($payload),
                'headers' => $this->redactedHeaders($normalized),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
            throw $e;
        }

        $existing = PaymentWebhook::query()->where([
            'provider_account_id' => $account->id,
            'external_event_id' => $event['event_id'],
        ])->first();
        if ($existing) {
            if ($existing->payload_hash !== hash('sha256', $payload)) {
                throw ValidationException::withMessages(['webhook' => 'The provider reused an event ID with a different payload.']);
            }
            return $existing;
        }

        $intent = PaymentIntent::query()->where([
            'tenant_id' => $account->tenant_id,
            'provider_account_id' => $account->id,
            'reference' => $event['reference'],
        ])->first();

        try {
            $webhook = PaymentWebhook::query()->create([
                'tenant_id' => $account->tenant_id,
                'provider_account_id' => $account->id,
                'payment_intent_id' => $intent?->id,
                'external_event_id' => $event['event_id'],
                'event_type' => $event['type'],
                'status' => PaymentWebhookStatus::Received,
                'signature_hash' => isset($normalized['x-agcp-signature']) ? hash('sha256', $normalized['x-agcp-signature']) : null,
                'payload_hash' => hash('sha256', $payload),
                'payload' => $event,
                'headers' => $this->redactedHeaders($normalized),
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            $webhook = PaymentWebhook::query()->where([
                'provider_account_id' => $account->id,
                'external_event_id' => $event['event_id'],
            ])->first();
            if (! $webhook) throw $e;
            if ($webhook->payload_hash !== hash('sha256', $payload)) {
                throw ValidationException::withMessages(['webhook' => 'The provider reused an event ID with a different payload.']);
            }
            return $webhook;
        }

        try {
            if (! $intent) {
                throw ValidationException::withMessages(['payment' => 'No payment intent matches the verified webhook reference.']);
            }
            match ($event['type']) {
                'payment.captured', 'payment.completed' => $this->settlements->capture($intent, $event),
                'payment.failed' => $this->settlements->fail($intent, $event),
                'payment.cancelled' => $intent->status->terminal() ? $intent : $this->cancelFromProvider($intent),
                default => throw ValidationException::withMessages(['webhook' => 'Unsupported payment webhook event type.']),
            };
            $webhook->forceFill(['status' => PaymentWebhookStatus::Processed, 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            $webhook->forceFill([
                'status' => PaymentWebhookStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
            ])->save();
            throw $e;
        }

        return $webhook->fresh(['intent', 'providerAccount']);
    }

    public function simulateCapture(PaymentIntent $intent): PaymentWebhook
    {
        $intent->loadMissing('providerAccount');
        if ($intent->providerAccount->provider !== 'sandbox' || ! app()->environment(['local', 'testing'])) {
            abort(404);
        }
        $timestamp = now()->timestamp;
        $payload = json_encode([
            'event_id' => 'evt_'.Str::uuid(),
            'type' => 'payment.captured',
            'reference' => $intent->reference,
            'provider_payment_id' => $intent->provider_payment_id,
            'amount_minor' => $intent->total_minor,
            'currency' => $intent->currency,
            'occurred_at' => now()->toAtomString(),
            'metadata' => ['sandbox' => true],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $intent->providerAccount->webhook_secret);
        return $this->ingest($intent->providerAccount, $payload, [
            'X-AGCP-Timestamp' => (string) $timestamp,
            'X-AGCP-Signature' => 'sha256='.$signature,
            'Content-Type' => 'application/json',
        ]);
    }

    private function cancelFromProvider(PaymentIntent $intent): PaymentIntent
    {
        $intent->forceFill(['status' => PaymentIntentStatus::Cancelled, 'cancelled_at' => now()])->save();
        return $intent;
    }

    /** @param array<string,mixed> $headers @return array<string,string> */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = is_array($value) ? implode(',', array_map('strval', $value)) : (string) $value;
        }
        return $normalized;
    }

    /** @return array<string,mixed> */
    private function safePayload(string $payload): array
    {
        try { return json_decode($payload, true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable) { return ['raw_base64' => base64_encode($payload)]; }
    }

    /** @param array<string,string> $headers @return array<string,string> */
    private function redactedHeaders(array $headers): array
    {
        foreach (['authorization', 'cookie', 'x-api-key', 'x-agcp-signature'] as $secret) {
            if (isset($headers[$secret])) $headers[$secret] = '[redacted]';
        }
        return $headers;
    }
}
