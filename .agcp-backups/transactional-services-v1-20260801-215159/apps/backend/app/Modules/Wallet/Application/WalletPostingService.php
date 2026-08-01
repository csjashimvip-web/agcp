<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class WalletPostingService
{
    public function __construct(
        private readonly LedgerBalanceValidator $validator,
    ) {
    }

    public function debitWallet(
        Wallet $wallet,
        int $amountMinor,
        string $idempotencyKey,
        string $referenceType,
        string $referenceId,
        ?string $description = null,
    ): LedgerTransaction {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use (
            $wallet,
            $amountMinor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $description,
        ): LedgerTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $existing = LedgerTransaction::query()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($wallet->available_balance_minor < $amountMinor) {
                throw new InvalidArgumentException('Insufficient wallet balance.');
            }

            $walletAccount = LedgerAccount::query()
                ->lockForUpdate()
                ->findOrFail($wallet->ledger_account_id);

            $commerceAccount = LedgerAccount::query()
                ->lockForUpdate()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('code', 'commerce-clearing')
                ->where('currency', $wallet->currency)
                ->firstOrFail();

            $entries = [
                ['direction' => 'debit', 'amount_minor' => $amountMinor],
                ['direction' => 'credit', 'amount_minor' => $amountMinor],
            ];

            $this->validator->assertBalanced($entries);

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $walletAccount->id,
                'direction' => 'debit',
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $commerceAccount->id,
                'direction' => 'credit',
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
            ]);

            $wallet->decrement('available_balance_minor', $amountMinor);
            $walletAccount->decrement('balance_minor', $amountMinor);
            $commerceAccount->increment('balance_minor', $amountMinor);

            return $transaction->fresh();
        }, 3);
    }
}