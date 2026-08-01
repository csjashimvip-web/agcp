<?php

namespace App\Modules\Payments\Application;

use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PaymentSettlementService
{
    public function creditWallet(
        Wallet $wallet,
        int $amountMinor,
        string $idempotencyKey,
        string $referenceType,
        string $referenceId,
    ): LedgerTransaction {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $amountMinor, $idempotencyKey, $referenceType, $referenceId): LedgerTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $existing = LedgerTransaction::query()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $walletAccount = LedgerAccount::query()->lockForUpdate()->findOrFail($wallet->ledger_account_id);

            $fundingAccount = LedgerAccount::query()
                ->lockForUpdate()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('code', 'payment-clearing')
                ->where('currency', $wallet->currency)
                ->firstOrFail();

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $fundingAccount->id,
                'direction' => 'debit',
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $walletAccount->id,
                'direction' => 'credit',
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
            ]);

            $fundingAccount->decrement('balance_minor', $amountMinor);
            $walletAccount->increment('balance_minor', $amountMinor);
            $wallet->increment('available_balance_minor', $amountMinor);

            return $transaction->fresh();
        }, 3);
    }
}