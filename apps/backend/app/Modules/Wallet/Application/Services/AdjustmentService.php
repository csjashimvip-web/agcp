<?php
namespace Modules\Wallet\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\AdjustmentStatus;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Domain\Events\WalletAdjusted;
use Modules\Wallet\Infrastructure\Models\WalletAdjustment;
final class AdjustmentService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(WalletAdjustment $adjustment, User $reviewer, ?string $idempotencyKey): WalletAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reviewer, $idempotencyKey): WalletAdjustment {
            $locked = WalletAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === AdjustmentStatus::Approved && $idempotencyKey !== null) {
                $expectedHash = hash('sha256', $idempotencyKey);
                if ($locked->ledgerTransaction?->idempotency_key_hash === $expectedHash) {
                    return $locked->fresh(['wallet.account', 'requester', 'reviewer', 'ledgerTransaction']);
                }
            }
            if ($locked->status !== AdjustmentStatus::Pending) {
                throw ValidationException::withMessages(['adjustment' => 'Only pending adjustments can be approved.']);
            }
            if ($locked->requested_by === $reviewer->id) {
                throw ValidationException::withMessages(['adjustment' => 'The requester cannot approve the same adjustment.']);
            }
            $wallet = $locked->wallet()->with('account')->firstOrFail();
            $control = $this->wallets->systemAccount(
                $locked->tenant_id,
                $locked->currency,
                'equity:manual-adjustments',
                'Manual adjustment control',
                AccountType::Equity,
                LedgerDirection::Credit,
            );
            $walletDirection = $locked->direction === LedgerDirection::Credit ? LedgerDirection::Credit : LedgerDirection::Debit;
            $controlDirection = $walletDirection->opposite();
            $transaction = $this->ledger->post(
                tenantId: $locked->tenant_id,
                eventType: 'wallet.adjustment.approved',
                description: 'Approved wallet adjustment '.$locked->id,
                entries: [
                    ['account_id' => $wallet->ledger_account_id, 'direction' => $walletDirection, 'amount_minor' => $locked->amount_minor],
                    ['account_id' => $control->id, 'direction' => $controlDirection, 'amount_minor' => $locked->amount_minor],
                ],
                referenceType: WalletAdjustment::class,
                referenceId: $locked->id,
                idempotencyKey: $idempotencyKey,
                metadata: ['reason' => $locked->reason],
            );
            $locked->forceFill([
                'status' => AdjustmentStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'ledger_transaction_id' => $transaction->id,
            ])->save();
            $payload = ['adjustment_id' => $locked->id, 'wallet_id' => $wallet->id, 'amount_minor' => $locked->amount_minor, 'currency' => $locked->currency, 'direction' => $locked->direction->value, 'ledger_transaction_id' => $transaction->id];
            $this->outbox->record(new WalletAdjusted($payload), $locked->tenant_id, ['reviewed_by' => $reviewer->id]);
            $this->audit->record('wallet.adjustment.approved', WalletAdjustment::class, $locked->id, $payload, [], $locked->tenant_id, User::class, $reviewer->id);
            return $locked->fresh(['wallet.account', 'requester', 'reviewer']);
        }, 5);
    }
}
