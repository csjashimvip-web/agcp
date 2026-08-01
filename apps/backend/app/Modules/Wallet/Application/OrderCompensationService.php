<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class OrderCompensationService
{
    public function __construct(
        private readonly LedgerBalanceValidator $validator,
    ) {
    }

    public function refundWalletOrder(
        Order $order,
        string $reason,
    ): LedgerTransaction {
        if (! $order->wallet_id) {
            throw new RuntimeException('Order has no wallet to compensate.');
        }

        if ($order->total_minor <= 0) {
            throw new RuntimeException('Order has no refundable amount.');
        }

        return DB::transaction(function () use ($order, $reason): LedgerTransaction {
            $existingCompensation = DB::table('financial_compensations')
                ->where('order_id', $order->id)
                ->where('type', 'wallet_order_refund')
                ->first();

            if ($existingCompensation && $existingCompensation->ledger_transaction_id) {
                return LedgerTransaction::query()
                    ->findOrFail($existingCompensation->ledger_transaction_id);
            }

            $wallet = Wallet::query()
                ->lockForUpdate()
                ->findOrFail($order->wallet_id);

            $walletAccount = LedgerAccount::query()
                ->lockForUpdate()
                ->findOrFail($wallet->ledger_account_id);

            $commerceAccount = LedgerAccount::query()
                ->lockForUpdate()
                ->where('tenant_id', $order->tenant_id)
                ->where('code', 'commerce-clearing')
                ->where('currency', $order->currency)
                ->firstOrFail();

            $amount = (int) $order->total_minor;

            $this->validator->assertBalanced([
                ['direction' => 'debit', 'amount_minor' => $amount],
                ['direction' => 'credit', 'amount_minor' => $amount],
            ]);

            $idempotencyKey = 'order:cancel:'.$order->id;

            $existingLedger = LedgerTransaction::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingLedger) {
                return $existingLedger;
            }

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $order->tenant_id,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'reference_type' => 'order_compensation',
                'reference_id' => (string) $order->id,
                'description' => 'Wallet compensation for '.$order->order_number,
                'status' => 'posted',
                'posted_at' => now(),
                'metadata' => [
                    'reason' => $reason,
                ],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $commerceAccount->id,
                'direction' => 'debit',
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'metadata' => ['order_id' => $order->id],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $walletAccount->id,
                'direction' => 'credit',
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'metadata' => ['order_id' => $order->id],
            ]);

            $commerceAccount->decrement('balance_minor', $amount);
            $walletAccount->increment('balance_minor', $amount);
            $wallet->increment('available_balance_minor', $amount);

            DB::table('financial_compensations')->insert([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'wallet_id' => $wallet->id,
                'ledger_transaction_id' => $transaction->id,
                'compensation_uuid' => (string) Str::uuid(),
                'type' => 'wallet_order_refund',
                'status' => 'completed',
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'reason' => $reason,
                'metadata' => json_encode([
                    'order_number' => $order->order_number,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transaction;
        }, 3);
    }
}