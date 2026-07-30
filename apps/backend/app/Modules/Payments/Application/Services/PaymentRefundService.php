<?php
namespace Modules\Payments\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Payments\Domain\Contracts\RefundablePaymentProvider;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Payments\Domain\Enums\PaymentRefundStatus;
use Modules\Payments\Domain\Events\PaymentRefunded;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Payments\Infrastructure\Models\PaymentRefund;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Application\Services\LedgerService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Infrastructure\Models\WalletHold;
use Throwable;

final class PaymentRefundService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
    ) {}

    public function request(PaymentIntent $intent, User $requester, int $amountMinor, string $reason, string $idempotencyKey): PaymentRefund
    {
        if ($amountMinor <= 0) throw ValidationException::withMessages(['amount' => 'Refund amount must be positive.']);
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode(['intent_id' => $intent->id, 'amount_minor' => $amountMinor, 'reason' => $reason], JSON_THROW_ON_ERROR));

        $existing = PaymentRefund::query()->where([
            'tenant_id' => $intent->tenant_id,
            'requested_by' => $requester->id,
            'idempotency_key_hash' => $keyHash,
        ])->first();
        if ($existing) {
            if ($existing->request_hash !== $requestHash) throw ValidationException::withMessages(['idempotency_key' => 'This Idempotency-Key was already used with a different refund request.']);
            return $existing->load(['intent', 'ledgerTransaction', 'walletHold']);
        }

        $refund = DB::transaction(function () use ($intent, $requester, $amountMinor, $reason, $keyHash, $requestHash): PaymentRefund {
            /** @var PaymentIntent $lockedIntent */
            $lockedIntent = PaymentIntent::query()->with(['providerAccount', 'wallet.account'])->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedIntent->status, [PaymentIntentStatus::Completed, PaymentIntentStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['refund' => 'Only a settled payment can be refunded.']);
            }
            $reserved = (int) $lockedIntent->refunds()->whereIn('status', [
                PaymentRefundStatus::Requested->value,
                PaymentRefundStatus::Processing->value,
                PaymentRefundStatus::Completed->value,
            ])->sum('amount_minor');
            if ($reserved + $amountMinor > $lockedIntent->amount_minor) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the remaining credited payment amount.']);
            }
            if ($lockedIntent->wallet->availableBalanceMinor() < $amountMinor) {
                throw ValidationException::withMessages(['wallet' => 'The customer wallet no longer has enough available balance for this external refund.']);
            }

            $refund = PaymentRefund::query()->create([
                'tenant_id' => $lockedIntent->tenant_id,
                'payment_intent_id' => $lockedIntent->id,
                'requested_by' => $requester->id,
                'reference' => 'REF-'.strtoupper((string) Str::ulid()),
                'amount_minor' => $amountMinor,
                'currency' => $lockedIntent->currency,
                'status' => PaymentRefundStatus::Processing,
                'reason' => $reason,
                'idempotency_key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'requested_at' => now(),
            ]);
            $hold = WalletHold::query()->create([
                'tenant_id' => $lockedIntent->tenant_id,
                'wallet_id' => $lockedIntent->wallet_id,
                'reference_type' => PaymentRefund::class,
                'reference_id' => $refund->id,
                'amount_minor' => $amountMinor,
                'status' => 'active',
                'reason' => 'Reserved for external payment refund '.$refund->reference,
                'expires_at' => now()->addHours(24),
            ]);
            $refund->forceFill(['wallet_hold_id' => $hold->id])->save();
            return $refund->load(['intent.providerAccount', 'intent.wallet.account', 'walletHold']);
        }, 5);

        $providerConfirmed = false;
        $result = [];
        try {
            $provider = $this->providers->get($refund->intent->providerAccount->provider);
            if (! $provider instanceof RefundablePaymentProvider) {
                throw ValidationException::withMessages(['provider' => 'This payment provider does not support automated refunds.']);
            }
            $result = $provider->refundPayment(
                (string) $refund->intent->provider_payment_id,
                $refund->reference,
                $amountMinor,
                $refund->currency,
                ['credentials' => $refund->intent->providerAccount->credentials ?? [], 'mode' => $refund->intent->providerAccount->mode],
            );
            if (($result['status'] ?? null) !== 'completed') {
                throw ValidationException::withMessages(['provider' => 'The provider did not confirm the refund.']);
            }
            $providerConfirmed = true;
            $refund->forceFill(['provider_refund_id' => $result['provider_refund_id'], 'metadata' => $result['metadata'] ?? []])->save();

            return DB::transaction(function () use ($refund, $requester, $result, $amountMinor): PaymentRefund {
                /** @var PaymentRefund $lockedRefund */
                $lockedRefund = PaymentRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                /** @var PaymentIntent $lockedIntent */
                $lockedIntent = PaymentIntent::query()->with(['providerAccount', 'wallet.account'])->whereKey($lockedRefund->payment_intent_id)->lockForUpdate()->firstOrFail();
                $hold = WalletHold::query()->whereKey($lockedRefund->wallet_hold_id)->lockForUpdate()->firstOrFail();
                if ($hold->status !== 'active') throw ValidationException::withMessages(['refund' => 'The refund balance reservation is no longer active.']);
                if ((int) $lockedIntent->wallet->account->balance_minor < $amountMinor) {
                    throw ValidationException::withMessages(['wallet' => 'Wallet balance changed before the refund could settle.']);
                }

                $providerCode = $lockedIntent->providerAccount->provider;
                $clearing = $this->wallets->systemAccount(
                    $lockedIntent->tenant_id,
                    $lockedIntent->currency,
                    'asset:gateway-clearing:'.$providerCode,
                    strtoupper($providerCode).' gateway clearing asset',
                    AccountType::Asset,
                    LedgerDirection::Debit,
                );
                $transaction = $this->ledger->post(
                    tenantId: $lockedIntent->tenant_id,
                    eventType: 'payment.refund.completed',
                    description: 'External payment refund '.$lockedRefund->reference,
                    entries: [
                        ['account_id' => $lockedIntent->wallet->ledger_account_id, 'direction' => LedgerDirection::Debit, 'amount_minor' => $amountMinor],
                        ['account_id' => $clearing->id, 'direction' => LedgerDirection::Credit, 'amount_minor' => $amountMinor],
                    ],
                    referenceType: PaymentRefund::class,
                    referenceId: $lockedRefund->id,
                    idempotencyKey: 'payment-refund:'.$lockedRefund->id,
                    metadata: ['payment_intent_id' => $lockedIntent->id, 'provider_refund_id' => $result['provider_refund_id']],
                );
                $hold->forceFill(['status' => 'released', 'released_at' => now()])->save();
                $lockedRefund->forceFill([
                    'status' => PaymentRefundStatus::Completed,
                    'ledger_transaction_id' => $transaction->id,
                    'failure_message' => null,
                    'completed_at' => now(),
                ])->save();
                $refunded = (int) $lockedIntent->refunds()->where('status', PaymentRefundStatus::Completed->value)->sum('amount_minor');
                $lockedIntent->forceFill(['status' => $refunded >= $lockedIntent->amount_minor ? PaymentIntentStatus::Refunded : PaymentIntentStatus::PartiallyRefunded])->save();

                $payload = [
                    'payment_refund_id' => $lockedRefund->id,
                    'payment_intent_id' => $lockedIntent->id,
                    'provider_refund_id' => $lockedRefund->provider_refund_id,
                    'amount_minor' => $amountMinor,
                    'currency' => $lockedIntent->currency,
                    'ledger_transaction_id' => $transaction->id,
                ];
                $this->outbox->record(new PaymentRefunded($payload), $lockedIntent->tenant_id, ['requested_by' => $requester->id]);
                $this->audit->record('payments.refund.completed', PaymentRefund::class, $lockedRefund->id, $payload, [], $lockedIntent->tenant_id, User::class, $requester->id);
                return $lockedRefund->fresh(['intent', 'ledgerTransaction', 'requester', 'walletHold']);
            }, 5);
        } catch (Throwable $e) {
            DB::transaction(function () use ($refund, $e, $providerConfirmed): void {
                $lockedRefund = PaymentRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                if ($providerConfirmed) {
                    $lockedRefund->forceFill([
                        'status' => PaymentRefundStatus::Processing,
                        'failure_message' => 'Provider refund confirmed; ledger settlement requires reconciliation: '.mb_substr($e->getMessage(), 0, 1500),
                    ])->save();
                    return;
                }
                $lockedRefund->forceFill([
                    'status' => PaymentRefundStatus::Failed,
                    'failure_message' => mb_substr($e->getMessage(), 0, 2000),
                    'failed_at' => now(),
                ])->save();
                if ($lockedRefund->wallet_hold_id) {
                    WalletHold::query()->whereKey($lockedRefund->wallet_hold_id)->where('status', 'active')->update(['status' => 'released', 'released_at' => now()]);
                }
            }, 5);
            throw $e;
        }
    }
}
