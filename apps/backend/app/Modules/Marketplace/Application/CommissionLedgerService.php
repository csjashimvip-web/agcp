<?php

namespace App\Modules\Marketplace\Application;

use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CommissionLedgerService
{
    public function __construct(
        private readonly LedgerBalanceValidator $validator,
    ) {
    }

    public function ensureAccrualPosted(int $accrualId): LedgerTransaction
    {
        return DB::transaction(function () use ($accrualId): LedgerTransaction {
            $accrual = DB::table('commission_accruals')
                ->where('id', $accrualId)
                ->lockForUpdate()
                ->first();

            if (! $accrual) {
                throw new RuntimeException('Commission accrual was not found.');
            }

            if ($accrual->accrual_ledger_transaction_id) {
                return LedgerTransaction::query()
                    ->findOrFail($accrual->accrual_ledger_transaction_id);
            }

            $expense = $this->account(
                (int) $accrual->tenant_id,
                'marketplace-commission-expense',
                'Marketplace Commission Expense',
                'expense',
                (string) $accrual->currency,
            );

            $payable = $this->account(
                (int) $accrual->tenant_id,
                'marketplace-commission-payable',
                'Marketplace Commission Payable',
                'liability',
                (string) $accrual->currency,
            );

            $amount = (int) $accrual->amount_minor;

            $this->validator->assertBalanced([
                ['direction' => 'debit', 'amount_minor' => $amount],
                ['direction' => 'credit', 'amount_minor' => $amount],
            ]);

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $accrual->tenant_id,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => 'commission:accrual:'.$accrual->id,
                'reference_type' => 'commission_accrual',
                'reference_id' => (string) $accrual->id,
                'description' => 'Marketplace commission accrual',
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $expense->id,
                'direction' => 'debit',
                'amount_minor' => $amount,
                'currency' => $accrual->currency,
                'metadata' => ['commission_accrual_id' => $accrual->id],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $payable->id,
                'direction' => 'credit',
                'amount_minor' => $amount,
                'currency' => $accrual->currency,
                'metadata' => ['commission_accrual_id' => $accrual->id],
            ]);

            $expense->increment('balance_minor', $amount);
            $payable->increment('balance_minor', $amount);

            DB::table('commission_accruals')
                ->where('id', $accrual->id)
                ->update([
                    'accrual_ledger_transaction_id' => $transaction->id,
                    'updated_at' => now(),
                ]);

            return $transaction->fresh();
        }, 3);
    }

    public function payableAccount(
        int $tenantId,
        string $currency,
    ): LedgerAccount {
        return $this->account(
            $tenantId,
            'marketplace-commission-payable',
            'Marketplace Commission Payable',
            'liability',
            $currency,
        );
    }

    private function account(
        int $tenantId,
        string $code,
        string $name,
        string $type,
        string $currency,
    ): LedgerAccount {
        return LedgerAccount::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
                'currency' => strtoupper($currency),
            ],
            [
                'name' => $name,
                'type' => $type,
                'status' => 'active',
                'balance_minor' => 0,
            ],
        );
    }
}