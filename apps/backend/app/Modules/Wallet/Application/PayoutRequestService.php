<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Models\LedgerTransaction;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PayoutRequestService
{
    public function __construct(
        private readonly LedgerBalanceValidator $validator,
    ) {
    }

    public function request(
        int $tenantId,
        int $userId,
        int $walletId,
        int $amountMinor,
        string $method,
        string $destinationLabel,
        array $destination,
    ): object {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Payout amount must be positive.');
        }

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $walletId,
            $amountMinor,
            $method,
            $destinationLabel,
            $destination,
        ): object {
            $wallet = Wallet::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('id', $walletId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException('Wallet was not found.');
            }

            if ((int) $wallet->available_balance_minor < $amountMinor) {
                throw new RuntimeException('Insufficient available balance.');
            }

            $wallet->decrement('available_balance_minor', $amountMinor);
            $wallet->increment('held_balance_minor', $amountMinor);

            $holdId = DB::table('wallet_holds')->insertGetId([
                'wallet_id' => $wallet->id,
                'hold_uuid' => (string) Str::uuid(),
                'amount_minor' => $amountMinor,
                'reason' => 'payout_request',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payoutId = DB::table('payout_requests')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'wallet_hold_id' => $holdId,
                'payout_uuid' => (string) Str::uuid(),
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
                'method' => $method,
                'destination_label' => $destinationLabel,
                'destination_payload' => Crypt::encryptString(
                    json_encode($destination, JSON_THROW_ON_ERROR)
                ),
                'status' => 'pending_review',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->safeRow($payoutId);
        }, 3);
    }

    public function approve(
        int $tenantId,
        int $payoutId,
        ?string $note = null,
    ): object {
        return DB::transaction(function () use (
            $tenantId,
            $payoutId,
            $note,
        ): object {
            $payout = DB::table('payout_requests')
                ->where('tenant_id', $tenantId)
                ->where('id', $payoutId)
                ->lockForUpdate()
                ->first();

            if (! $payout) {
                throw new RuntimeException('Payout request was not found.');
            }

            if ($payout->status === 'approved') {
                return $this->safeRow($payoutId);
            }

            if ($payout->status !== 'pending_review') {
                throw new RuntimeException(
                    'Only pending payout requests can be approved.'
                );
            }

            DB::table('payout_requests')
                ->where('id', $payoutId)
                ->update([
                    'status' => 'approved',
                    'review_note' => $note,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->safeRow($payoutId);
        }, 3);
    }

    public function reject(
        int $tenantId,
        int $payoutId,
        string $note,
    ): object {
        return DB::transaction(function () use (
            $tenantId,
            $payoutId,
            $note,
        ): object {
            $payout = DB::table('payout_requests')
                ->where('tenant_id', $tenantId)
                ->where('id', $payoutId)
                ->lockForUpdate()
                ->first();

            if (! $payout) {
                throw new RuntimeException('Payout request was not found.');
            }

            if ($payout->status === 'rejected') {
                return $this->safeRow($payoutId);
            }

            if (! in_array(
                $payout->status,
                ['pending_review', 'approved'],
                true
            )) {
                throw new RuntimeException(
                    'This payout request cannot be rejected.'
                );
            }

            $wallet = Wallet::query()
                ->where('id', $payout->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $payout->amount_minor;

            if ((int) $wallet->held_balance_minor < $amount) {
                throw new RuntimeException('Wallet hold balance is inconsistent.');
            }

            $wallet->decrement('held_balance_minor', $amount);
            $wallet->increment('available_balance_minor', $amount);

            DB::table('wallet_holds')
                ->where('id', $payout->wallet_hold_id)
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('payout_requests')
                ->where('id', $payoutId)
                ->update([
                    'status' => 'rejected',
                    'review_note' => $note,
                    'rejected_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->safeRow($payoutId);
        }, 3);
    }

    public function markPaid(
        int $tenantId,
        int $payoutId,
        ?string $note = null,
    ): object {
        return DB::transaction(function () use (
            $tenantId,
            $payoutId,
            $note,
        ): object {
            $payout = DB::table('payout_requests')
                ->where('tenant_id', $tenantId)
                ->where('id', $payoutId)
                ->lockForUpdate()
                ->first();

            if (! $payout) {
                throw new RuntimeException('Payout request was not found.');
            }

            if ($payout->status === 'paid') {
                return $this->safeRow($payoutId);
            }

            if ($payout->status !== 'approved') {
                throw new RuntimeException(
                    'Payout must be approved before it can be marked paid.'
                );
            }

            $wallet = Wallet::query()
                ->where('id', $payout->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $payout->amount_minor;

            if ((int) $wallet->held_balance_minor < $amount) {
                throw new RuntimeException('Wallet hold balance is inconsistent.');
            }

            $walletAccount = LedgerAccount::query()
                ->where('id', $wallet->ledger_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $clearing = LedgerAccount::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => 'payout-clearing',
                    'currency' => $payout->currency,
                ],
                [
                    'name' => 'Payout Clearing',
                    'type' => 'asset',
                    'status' => 'active',
                    'balance_minor' => 0,
                ],
            );

            $this->validator->assertBalanced([
                ['direction' => 'debit', 'amount_minor' => $amount],
                ['direction' => 'credit', 'amount_minor' => $amount],
            ]);

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $tenantId,
                'transaction_uuid' => (string) Str::uuid(),
                'idempotency_key' => 'payout:paid:'.$payout->id,
                'reference_type' => 'payout_request',
                'reference_id' => (string) $payout->id,
                'description' => 'Externally confirmed payout',
                'status' => 'posted',
                'posted_at' => now(),
                'metadata' => ['method' => $payout->method],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $walletAccount->id,
                'direction' => 'debit',
                'amount_minor' => $amount,
                'currency' => $payout->currency,
                'metadata' => ['payout_request_id' => $payout->id],
            ]);

            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'ledger_account_id' => $clearing->id,
                'direction' => 'credit',
                'amount_minor' => $amount,
                'currency' => $payout->currency,
                'metadata' => ['payout_request_id' => $payout->id],
            ]);

            $wallet->decrement('held_balance_minor', $amount);
            $walletAccount->decrement('balance_minor', $amount);
            $clearing->increment('balance_minor', $amount);

            DB::table('wallet_holds')
                ->where('id', $payout->wallet_hold_id)
                ->update([
                    'status' => 'captured',
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('payout_requests')
                ->where('id', $payoutId)
                ->update([
                    'ledger_transaction_id' => $transaction->id,
                    'status' => 'paid',
                    'review_note' => $note ?? $payout->review_note,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->safeRow($payoutId);
        }, 3);
    }

    private function safeRow(int $payoutId): object
    {
        return DB::table('payout_requests')
            ->where('id', $payoutId)
            ->first([
                'id',
                'tenant_id',
                'user_id',
                'wallet_id',
                'payout_uuid',
                'amount_minor',
                'currency',
                'method',
                'destination_label',
                'status',
                'review_note',
                'approved_at',
                'rejected_at',
                'paid_at',
                'created_at',
                'updated_at',
            ]);
    }
}