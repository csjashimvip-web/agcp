<?php
namespace Modules\Payments\Application\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Payments\Domain\Enums\PaymentAttemptStatus;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Payments\Domain\Events\PaymentIntentCreated;
use Modules\Payments\Infrastructure\Models\PaymentAttempt;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Infrastructure\Models\Wallet;
use Throwable;

final class PaymentIntentService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
    ) {}

    public function create(
        User $user,
        string $tenantId,
        string $walletId,
        string $providerCode,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
    ): PaymentIntent {
        $currency = strtoupper($currency);
        $providerAccount = PaymentProviderAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $providerCode)
            ->where('status', 'active')
            ->orderBy('priority')
            ->firstOrFail();

        $wallet = Wallet::query()->with('account')->whereKey($walletId)->where([
            'tenant_id' => $tenantId,
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'status' => 'active',
        ])->firstOrFail();

        if ($wallet->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => 'The selected wallet uses a different currency.']);
        }
        if (! in_array($currency, array_map('strtoupper', $providerAccount->currencies ?? []), true)) {
            throw ValidationException::withMessages(['currency' => 'This payment provider does not support the selected currency.']);
        }
        if ($amountMinor < $providerAccount->minimum_amount_minor || $amountMinor > $providerAccount->maximum_amount_minor) {
            throw ValidationException::withMessages(['amount' => 'The amount is outside this provider account limits.']);
        }

        $idempotencyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode([
            'wallet_id' => $wallet->id,
            'provider_account_id' => $providerAccount->id,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ], JSON_THROW_ON_ERROR));

        $existing = PaymentIntent::query()->where([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'idempotency_key_hash' => $idempotencyHash,
        ])->first();
        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                throw ValidationException::withMessages(['idempotency_key' => 'This Idempotency-Key was already used with a different payment request.']);
            }
            return $existing->load(['providerAccount', 'wallet.account', 'attempts', 'deposit']);
        }

        $feeMinor = $this->calculateFee($providerAccount, $amountMinor);
        try {
            $intent = DB::transaction(function () use ($user, $tenantId, $wallet, $providerAccount, $amountMinor, $feeMinor, $currency, $idempotencyHash, $requestHash): PaymentIntent {
                $intent = PaymentIntent::query()->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'provider_account_id' => $providerAccount->id,
                    'reference' => 'PAY-'.strtoupper((string) Str::ulid()),
                    'amount_minor' => $amountMinor,
                    'fee_minor' => $feeMinor,
                    'total_minor' => $amountMinor + $feeMinor,
                    'currency' => $currency,
                    'status' => PaymentIntentStatus::Created,
                    'idempotency_key_hash' => $idempotencyHash,
                    'request_hash' => $requestHash,
                    'metadata' => ['credit_amount_minor' => $amountMinor],
                ]);
                PaymentAttempt::query()->create([
                    'payment_intent_id' => $intent->id,
                    'attempt_number' => 1,
                    'status' => PaymentAttemptStatus::Initiated,
                    'request_payload' => ['reference' => $intent->reference, 'total_minor' => $intent->total_minor, 'currency' => $currency],
                    'started_at' => now(),
                ]);
                return $intent;
            }, 5);
        } catch (QueryException $e) {
            $intent = PaymentIntent::query()->where([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'idempotency_key_hash' => $idempotencyHash,
            ])->first();
            if (! $intent) throw $e;
            if ($intent->request_hash !== $requestHash) {
                throw ValidationException::withMessages(['idempotency_key' => 'This Idempotency-Key was already used with a different payment request.']);
            }
            return $intent->load(['providerAccount', 'wallet.account', 'attempts', 'deposit']);
        }

        $attempt = $intent->attempts()->firstOrFail();
        try {
            $provider = $this->providers->get($providerAccount->provider);
            $result = $provider->createPayment($intent->reference, $intent->total_minor, $currency, [
                'credentials' => $providerAccount->credentials ?? [],
                'mode' => $providerAccount->mode,
                'expiry_minutes' => config('payments.intent_expiry_minutes', 30),
            ]);
            DB::transaction(function () use ($intent, $attempt, $result): void {
                $intent->forceFill([
                    'provider_payment_id' => $result['provider_payment_id'],
                    'checkout_url' => $result['checkout_url'] ?? null,
                    'status' => PaymentIntentStatus::Pending,
                    'expires_at' => isset($result['expires_at']) ? $result['expires_at'] : now()->addMinutes((int) config('payments.intent_expiry_minutes', 30)),
                    'metadata' => array_merge($intent->metadata ?? [], $result['metadata'] ?? []),
                ])->save();
                $attempt->forceFill([
                    'status' => PaymentAttemptStatus::Pending,
                    'response_payload' => $result,
                    'finished_at' => now(),
                ])->save();
            }, 5);
        } catch (Throwable $e) {
            DB::transaction(function () use ($intent, $attempt, $e): void {
                $intent->forceFill([
                    'status' => PaymentIntentStatus::Failed,
                    'failure_code' => 'provider_initialization_failed',
                    'failure_message' => mb_substr($e->getMessage(), 0, 2000),
                    'failed_at' => now(),
                ])->save();
                $attempt->forceFill([
                    'status' => PaymentAttemptStatus::Failed,
                    'error_code' => 'provider_initialization_failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    'finished_at' => now(),
                ])->save();
            }, 5);
            throw $e;
        }

        $payload = [
            'payment_intent_id' => $intent->id,
            'reference' => $intent->reference,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'provider' => $providerAccount->provider,
            'provider_account_id' => $providerAccount->id,
            'amount_minor' => $amountMinor,
            'fee_minor' => $feeMinor,
            'total_minor' => $amountMinor + $feeMinor,
            'currency' => $currency,
        ];
        $this->outbox->record(new PaymentIntentCreated($payload), $tenantId, ['user_id' => $user->id]);
        $this->audit->record('payments.intent.created', PaymentIntent::class, $intent->id, $payload, [], $tenantId, User::class, $user->id);

        return $intent->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit']);
    }

    public function cancel(PaymentIntent $intent, User $user): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $user): PaymentIntent {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $user->id, 404);
            if (! in_array($locked->status, [PaymentIntentStatus::Created, PaymentIntentStatus::Pending], true)) {
                throw ValidationException::withMessages(['payment' => 'Only an unpaid payment intent can be cancelled.']);
            }
            $locked->forceFill(['status' => PaymentIntentStatus::Cancelled, 'cancelled_at' => now()])->save();
            $this->audit->record('payments.intent.cancelled', PaymentIntent::class, $locked->id, [], [], $locked->tenant_id, User::class, $user->id);
            return $locked->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit']);
        }, 5);
    }

    private function calculateFee(PaymentProviderAccount $account, int $amountMinor): int
    {
        $percentage = intdiv(($amountMinor * (int) $account->fee_basis_points) + 9999, 10000);
        return $percentage + (int) $account->fee_fixed_minor;
    }
}
