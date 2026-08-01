<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Application\PaymentSettlementService;
use App\Modules\Payments\Domain\Models\PaymentIntent;
use App\Modules\Payments\Domain\Models\PaymentProvider;
use App\Modules\Payments\Infrastructure\GenericHmacPaymentProvider;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentWebhookController
{
    public function __invoke(
        Request $request,
        int $providerId,
        PaymentSettlementService $settlement,
    ): JsonResponse {
        $provider = PaymentProvider::query()
            ->whereKey($providerId)
            ->where('status', 'active')
            ->firstOrFail();

        $secretData = $provider->secret_payload
            ? json_decode(Crypt::decryptString($provider->secret_payload), true)
            : null;

        if (! is_array($secretData)) {
            throw new HttpException(503, 'Payment provider secret is not configured.');
        }

        $driver = match ($provider->driver) {
            'generic-hmac' => new GenericHmacPaymentProvider((string) ($secretData['webhook_secret'] ?? '')),
            default => throw new HttpException(501, 'Payment provider driver is not implemented.'),
        };

        $raw = $request->getContent();
        $verified = $driver->verifyWebhook($raw, $request->headers->all());

        if (! ($verified['valid'] ?? false)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return DB::transaction(function () use ($provider, $verified, $settlement): JsonResponse {
            $eventId = (string) $verified['event_id'];
            $payload = $verified['payload'] ?? [];

            $alreadyProcessed = DB::table('payment_events')
                ->where('payment_provider_id', $provider->id)
                ->where('provider_event_id', $eventId)
                ->exists();

            if ($alreadyProcessed) {
                return response()->json(['data' => ['duplicate' => true]]);
            }

            $providerReference = (string) ($payload['provider_reference'] ?? '');

            $intent = PaymentIntent::query()
                ->where('payment_provider_id', $provider->id)
                ->where('provider_reference', $providerReference)
                ->first();

            $paymentEventId = DB::table('payment_events')->insertGetId([
                'payment_provider_id' => $provider->id,
                'payment_intent_id' => $intent?->id,
                'provider_event_id' => $eventId,
                'event_type' => (string) $verified['event_type'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($intent && in_array((string) ($payload['status'] ?? ''), ['paid', 'completed'], true)) {
                $wallet = Wallet::query()->findOrFail($intent->wallet_id);

                $settlement->creditWallet(
                    wallet: $wallet,
                    amountMinor: $intent->amount_minor,
                    idempotencyKey: "payment:event:{$provider->id}:{$eventId}",
                    referenceType: 'payment_intent',
                    referenceId: (string) $intent->id,
                );

                $intent->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),
                ])->save();
            }

            DB::table('payment_events')
                ->where('id', $paymentEventId)
                ->update([
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json(['data' => ['processed' => true]]);
        }, 3);
    }
}