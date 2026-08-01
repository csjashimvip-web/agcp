<?php

namespace App\Modules\Marketplace\Application;

use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CommissionSettlementService
{
    public function __construct(
        private readonly CommissionLedgerService $ledger,
        private readonly LedgerBalanceValidator $validator,
    ) {
    }

    public function settleSeller(
        int $tenantId,
        int $sellerId,
        string $currency,
    ): object {
        return DB::transaction(function () use (
            $tenantId,
            $sellerId,
            $currency,
        ): object {
            $seller = DB::table('marketplace_sellers')
                ->where('tenant_id', $tenantId)
                ->where('id', $sellerId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                throw new RuntimeException('Marketplace seller was not found.');
            }

            $currency = strtoupper($currency);

            $accruals = DB::table('commission_accruals')
                ->where('tenant_id', $tenantId)
                ->where('marketplace_seller_id', $sellerId)
                ->where('currency', $currency)
                ->where('status', 'accrued')
                ->whereNull('commission_settlement_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($accruals->isEmpty()) {
                throw new RuntimeException(
                    'No unsettled commission accruals are available.'
                );
            }

            $wallet = Wallet::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $seller->user_id)
                ->where('currency', $currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException(
                    "Seller does not have an active {$currency} wallet."
                );
            }

            foreach ($accruals as $accrual) {
                if (! $accrual->accrual_ledger_transaction_id) {
                    $this->ledger->ensureAccrualPosted((int) $accrual->id);
                }
            }

            $amount = (int) $accruals->sum('amount_minor');

            $settlementId = DB::table('commission_settlements')->insertGetId([
                'tenant_id' => $tenantId,
                'marketplace_seller_id' => $sellerId,
                'beneficiary_user_id' => $seller->user_id,
                'wallet_id' => $wallet->id,
                'settlement_uuid' => (string) Str::uuid(),
                'amount_minor' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'metadata' => json_encode([
                    'accrual_ids' => $accruals->pluck('id')->values()->all(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payable = $this->ledger->payableAccount($tenantId, $currency);

            $this->validator->assertBalanced([
                ['direction' => 'debit', 'amount_minor' => $amount],
                ['direction' => 'credit', 'amount_minor' => $amount],
            ]);

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $tenantId,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => 'commission:settlement:'.$settlementId,
                'reference_type' => 'commission_settlement',
                'reference_id' => (string) $settlementId,
                'description' => 'Marketplace commission settlement',
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $payable->id,
                'direction' => 'debit',
                'amount_minor' => $amount,
                'currency' => $currency,
                'metadata' => ['commission_settlement_id' => $settlementId],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $wallet->ledger_account_id,
                'direction' => 'credit',
                'amount_minor' => $amount,
                'currency' => $currency,
                'metadata' => ['commission_settlement_id' => $settlementId],
            ]);

            $payable->decrement('balance_minor', $amount);

            DB::table('ledger_accounts')
                ->where('id', $wallet->ledger_account_id)
                ->increment('balance_minor', $amount);

            $wallet->increment('available_balance_minor', $amount);

            DB::table('commission_settlements')
                ->where('id', $settlementId)
                ->update([
                    'ledger_transaction_id' => $transaction->id,
                    'status' => 'settled',
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('commission_accruals')
                ->whereIn('id', $accruals->pluck('id'))
                ->update([
                    'status' => 'settled',
                    'settled_at' => now(),
                    'commission_settlement_id' => $settlementId,
                    'updated_at' => now(),
                ]);

            return DB::table('commission_settlements')
                ->where('id', $settlementId)
                ->first();
        }, 3);
    }
}