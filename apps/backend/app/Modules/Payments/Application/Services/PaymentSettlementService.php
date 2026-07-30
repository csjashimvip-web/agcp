<?php
namespace Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Payments\Domain\Enums\PaymentAttemptStatus;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Payments\Domain\Events\PaymentCaptured;
use Modules\Payments\Infrastructure\Models\PaymentIntent;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Application\Services\LedgerService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Infrastructure\Models\DepositRequest;

final class PaymentSettlementService
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string,mixed> $event */
    public function capture(PaymentIntent $intent, array $event): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $event): PaymentIntent {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [PaymentIntentStatus::Completed, PaymentIntentStatus::PartiallyRefunded, PaymentIntentStatus::Refunded], true)) {
                return $locked->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit.ledgerTransaction', 'feeLedgerTransaction']);
            }
            if (! in_array($locked->status, [PaymentIntentStatus::Created, PaymentIntentStatus::Pending, PaymentIntentStatus::Processing], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment intent cannot be captured from its current state.']);
            }
            if ((int) $event['amount_minor'] !== (int) $locked->total_minor || strtoupper((string) $event['currency']) !== $locked->currency) {
                throw ValidationException::withMessages(['payment' => 'Verified gateway amount or currency does not match the payment intent.']);
            }

            $providerPaymentId = (string) ($event['provider_payment_id'] ?? $locked->provider_payment_id ?? '');
            if ($locked->provider_payment_id && $providerPaymentId !== '' && $locked->provider_payment_id !== $providerPaymentId) {
                throw ValidationException::withMessages(['payment' => 'Provider payment identifier does not match the payment intent.']);
            }
            $providerCode = (string) $locked->providerAccount()->value('provider');

            $deposit = DepositRequest::query()->firstOrCreate(
                ['payment_intent_id' => $locked->id],
                [
                    'tenant_id' => $locked->tenant_id,
                    'user_id' => $locked->user_id,
                    'wallet_id' => $locked->wallet_id,
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'method' => 'payment_gateway',
                    'status' => DepositStatus::Pending,
                    'external_reference' => $providerPaymentId,
                    'idempotency_key_hash' => hash('sha256', 'payment-deposit:'.$locked->id),
                    'request_hash' => hash('sha256', $locked->id.':'.$locked->amount_minor.':'.$locked->currency),
                    'customer_note' => 'Automatic deposit created from verified payment '.$locked->reference.'.',
                    'submitted_at' => now(),
                ],
            );

            $settled = $this->deposits->settleAutomated(
                $deposit,
                $providerCode,
                $providerPaymentId,
                'payment-settlement:'.$locked->id,
            );

            $feeTransactionId = $locked->fee_ledger_transaction_id;
            if ((int) $locked->fee_minor > 0 && ! $feeTransactionId) {
                $clearing = $this->wallets->systemAccount(
                    $locked->tenant_id,
                    $locked->currency,
                    'asset:gateway-clearing:'.$providerCode,
                    strtoupper($providerCode).' gateway clearing asset',
                    AccountType::Asset,
                    LedgerDirection::Debit,
                );
                $revenue = $this->wallets->systemAccount(
                    $locked->tenant_id,
                    $locked->currency,
                    'revenue:payment-fees:'.$providerCode,
                    strtoupper($providerCode).' payment fee revenue',
                    AccountType::Revenue,
                    LedgerDirection::Credit,
                );
                $feeTransaction = $this->ledger->post(
                    tenantId: $locked->tenant_id,
                    eventType: 'payment.fee.collected',
                    description: 'Payment fee collected for '.$locked->reference,
                    entries: [
                        ['account_id' => $clearing->id, 'direction' => LedgerDirection::Debit, 'amount_minor' => $locked->fee_minor],
                        ['account_id' => $revenue->id, 'direction' => LedgerDirection::Credit, 'amount_minor' => $locked->fee_minor],
                    ],
                    referenceType: PaymentIntent::class,
                    referenceId: $locked->id,
                    idempotencyKey: 'payment-fee:'.$locked->id,
                    metadata: ['provider' => $providerCode, 'provider_payment_id' => $providerPaymentId],
                );
                $feeTransactionId = $feeTransaction->id;
            }

            $locked->forceFill([
                'provider_payment_id' => $providerPaymentId ?: $locked->provider_payment_id,
                'fee_ledger_transaction_id' => $feeTransactionId,
                'status' => PaymentIntentStatus::Completed,
                'completed_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();
            $locked->attempts()->latest('attempt_number')->first()?->forceFill([
                'status' => PaymentAttemptStatus::Completed,
                'finished_at' => now(),
            ])->save();

            $payload = [
                'payment_intent_id' => $locked->id,
                'reference' => $locked->reference,
                'provider_payment_id' => $locked->provider_payment_id,
                'deposit_id' => $settled->id,
                'ledger_transaction_id' => $settled->ledger_transaction_id,
                'fee_ledger_transaction_id' => $feeTransactionId,
                'wallet_id' => $locked->wallet_id,
                'amount_minor' => $locked->amount_minor,
                'fee_minor' => $locked->fee_minor,
                'total_minor' => $locked->total_minor,
                'currency' => $locked->currency,
            ];
            $this->outbox->record(new PaymentCaptured($payload), $locked->tenant_id, ['verified_webhook' => true]);
            $this->audit->record('payments.intent.captured', PaymentIntent::class, $locked->id, $payload, [], $locked->tenant_id);

            return $locked->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit.ledgerTransaction', 'feeLedgerTransaction']);
        }, 5);
    }

    /** @param array<string,mixed> $event */
    public function fail(PaymentIntent $intent, array $event): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $event): PaymentIntent {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->terminal()) return $locked;
            $locked->forceFill([
                'status' => PaymentIntentStatus::Failed,
                'failure_code' => (string) ($event['metadata']['failure_code'] ?? 'provider_failed'),
                'failure_message' => (string) ($event['metadata']['failure_message'] ?? 'The payment provider reported a failure.'),
                'failed_at' => now(),
            ])->save();
            $locked->attempts()->latest('attempt_number')->first()?->forceFill([
                'status' => PaymentAttemptStatus::Failed,
                'error_code' => $locked->failure_code,
                'error_message' => $locked->failure_message,
                'finished_at' => now(),
            ])->save();
            $this->audit->record('payments.intent.failed', PaymentIntent::class, $locked->id, ['failure_code' => $locked->failure_code], [], $locked->tenant_id);
            return $locked->fresh(['providerAccount', 'wallet.account', 'attempts', 'deposit']);
        }, 5);
    }
}
