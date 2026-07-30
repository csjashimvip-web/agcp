<?php
namespace Modules\Wallet\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Domain\Events\DepositApproved;
use Modules\Wallet\Infrastructure\Models\DepositRequest;

final class DepositService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(DepositRequest $deposit, User $reviewer, ?string $note, ?string $idempotencyKey): DepositRequest
    {
        return DB::transaction(function () use ($deposit, $reviewer, $note, $idempotencyKey): DepositRequest {
            /** @var DepositRequest $locked */
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DepositStatus::Approved && $idempotencyKey !== null) {
                $expectedHash = hash('sha256', $idempotencyKey);
                if ($locked->ledgerTransaction?->idempotency_key_hash === $expectedHash) {
                    return $locked->fresh(['wallet.account', 'reviewer', 'ledgerTransaction']);
                }
            }
            if ($locked->status !== DepositStatus::Pending) {
                throw ValidationException::withMessages(['deposit' => 'Only pending deposits can be approved.']);
            }
            $wallet = $locked->wallet()->with('account')->firstOrFail();
            $clearing = $this->wallets->systemAccount(
                $locked->tenant_id,
                $locked->currency,
                'asset:deposit-clearing',
                'Deposit clearing asset',
                AccountType::Asset,
                LedgerDirection::Debit,
            );
            $transaction = $this->ledger->post(
                tenantId: $locked->tenant_id,
                eventType: 'deposit.approved',
                description: 'Approved customer deposit '.$locked->id,
                entries: [
                    ['account_id' => $clearing->id, 'direction' => LedgerDirection::Debit, 'amount_minor' => $locked->amount_minor],
                    ['account_id' => $wallet->ledger_account_id, 'direction' => LedgerDirection::Credit, 'amount_minor' => $locked->amount_minor],
                ],
                referenceType: DepositRequest::class,
                referenceId: $locked->id,
                idempotencyKey: $idempotencyKey,
                metadata: ['method' => $locked->method, 'external_reference' => $locked->external_reference],
            );
            $locked->forceFill([
                'status' => DepositStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
                'ledger_transaction_id' => $transaction->id,
            ])->save();

            $payload = [
                'deposit_id' => $locked->id,
                'wallet_id' => $wallet->id,
                'user_id' => $locked->user_id,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'ledger_transaction_id' => $transaction->id,
            ];
            $this->outbox->record(new DepositApproved($payload), $locked->tenant_id, ['reviewed_by' => $reviewer->id]);
            $this->audit->record('wallet.deposit.approved', DepositRequest::class, $locked->id, $payload, [], $locked->tenant_id, User::class, $reviewer->id);
            return $locked->fresh(['wallet.account', 'reviewer']);
        }, 5);
    }

    public function settleAutomated(DepositRequest $deposit, string $provider, string $externalReference, string $idempotencyKey): DepositRequest
    {
        return DB::transaction(function () use ($deposit, $provider, $externalReference, $idempotencyKey): DepositRequest {
            /** @var DepositRequest $locked */
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DepositStatus::Approved) {
                return $locked->fresh(['wallet.account', 'ledgerTransaction']);
            }
            if ($locked->status !== DepositStatus::Pending) {
                throw ValidationException::withMessages(['deposit' => 'Only pending gateway deposits can be settled.']);
            }

            $wallet = $locked->wallet()->with('account')->firstOrFail();
            $clearing = $this->wallets->systemAccount(
                $locked->tenant_id,
                $locked->currency,
                'asset:gateway-clearing:'.$provider,
                strtoupper($provider).' gateway clearing asset',
                AccountType::Asset,
                LedgerDirection::Debit,
            );
            $transaction = $this->ledger->post(
                tenantId: $locked->tenant_id,
                eventType: 'deposit.gateway_settled',
                description: 'Settled verified payment deposit '.$locked->id,
                entries: [
                    ['account_id' => $clearing->id, 'direction' => LedgerDirection::Debit, 'amount_minor' => $locked->amount_minor],
                    ['account_id' => $wallet->ledger_account_id, 'direction' => LedgerDirection::Credit, 'amount_minor' => $locked->amount_minor],
                ],
                referenceType: DepositRequest::class,
                referenceId: $locked->id,
                idempotencyKey: $idempotencyKey,
                metadata: ['method' => 'payment_gateway', 'provider' => $provider, 'external_reference' => $externalReference],
            );
            $locked->forceFill([
                'status' => DepositStatus::Approved,
                'external_reference' => $externalReference,
                'reviewed_by' => null,
                'reviewed_at' => now(),
                'admin_note' => 'Automatically settled after a verified '.$provider.' webhook.',
                'ledger_transaction_id' => $transaction->id,
            ])->save();

            $payload = [
                'deposit_id' => $locked->id,
                'payment_intent_id' => $locked->payment_intent_id,
                'wallet_id' => $wallet->id,
                'user_id' => $locked->user_id,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'provider' => $provider,
                'ledger_transaction_id' => $transaction->id,
            ];
            $this->outbox->record(new DepositApproved($payload), $locked->tenant_id, ['automated' => true, 'provider' => $provider]);
            $this->audit->record('wallet.deposit.gateway_settled', DepositRequest::class, $locked->id, $payload, [], $locked->tenant_id);
            return $locked->fresh(['wallet.account', 'ledgerTransaction']);
        }, 5);
    }

    public function reject(DepositRequest $deposit, User $reviewer, string $note): DepositRequest
    {
        return DB::transaction(function () use ($deposit, $reviewer, $note): DepositRequest {
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DepositStatus::Pending) {
                throw ValidationException::withMessages(['deposit' => 'Only pending deposits can be rejected.']);
            }
            $locked->forceFill(['status' => DepositStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'admin_note' => $note])->save();
            $this->audit->record('wallet.deposit.rejected', DepositRequest::class, $locked->id, ['reason' => $note], [], $locked->tenant_id, User::class, $reviewer->id);
            return $locked->fresh(['wallet.account', 'reviewer']);
        }, 5);
    }
}
