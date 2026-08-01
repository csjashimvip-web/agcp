<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Payments\Application\PaymentSettlementService;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApproveDepositService
{
    public function __construct(
        private readonly PaymentSettlementService $settlement,
    ) {
    }

    public function approve(int $tenantId, int $depositId): object
    {
        return DB::transaction(function () use ($tenantId, $depositId): object {
            $deposit = DB::table('deposits')
                ->where('tenant_id', $tenantId)
                ->where('id', $depositId)
                ->lockForUpdate()
                ->first();

            if (! $deposit) {
                throw new RuntimeException('Deposit not found.');
            }

            if ($deposit->status === 'approved') {
                return $deposit;
            }

            if ($deposit->status !== 'pending') {
                throw new RuntimeException('Only pending deposits can be approved.');
            }

            $wallet = Wallet::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($deposit->wallet_id);

            $ledger = $this->settlement->creditWallet(
                wallet: $wallet,
                amountMinor: (int) $deposit->amount_minor,
                idempotencyKey: 'deposit:approve:'.$deposit->id,
                referenceType: 'deposit',
                referenceId: (string) $deposit->id,
            );

            DB::table('deposits')->where('id', $deposit->id)->update([
                'status' => 'approved',
                'ledger_transaction_id' => $ledger->id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('deposits')->where('id', $deposit->id)->first();
        }, 3);
    }
}