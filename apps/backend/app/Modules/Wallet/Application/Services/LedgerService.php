<?php
namespace Modules\Wallet\Application\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Infrastructure\Models\LedgerAccount;
use Modules\Wallet\Infrastructure\Models\LedgerEntry;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
final class LedgerService
{
    /**
     * @param array<int,array{account_id:string,direction:LedgerDirection,amount_minor:int,metadata?:array}> $entries
     */
    public function post(
        string $tenantId,
        string $eventType,
        string $description,
        array $entries,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): LedgerTransaction {
        if (count($entries) < 2) {
            throw ValidationException::withMessages(['entries' => 'A balanced ledger transaction needs at least two entries.']);
        }

        return DB::transaction(function () use ($tenantId, $eventType, $description, $entries, $referenceType, $referenceId, $idempotencyKey, $metadata): LedgerTransaction {
            $accountIds = collect($entries)->pluck('account_id')->unique()->sort()->values();
            $accounts = LedgerAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $accountIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($accounts->count() !== $accountIds->count()) {
                throw ValidationException::withMessages(['entries' => 'One or more ledger accounts are unavailable.']);
            }

            $currency = null;
            $debits = 0;
            $credits = 0;
            foreach ($entries as $entry) {
                $account = $accounts->get($entry['account_id']);
                if ($account->status !== 'active') {
                    throw ValidationException::withMessages(['entries' => 'A ledger account is inactive.']);
                }
                if ($entry['amount_minor'] <= 0) {
                    throw ValidationException::withMessages(['entries' => 'Ledger amounts must be positive.']);
                }
                $currency ??= $account->currency;
                if ($currency !== $account->currency) {
                    throw ValidationException::withMessages(['entries' => 'Cross-currency journal entries are not allowed.']);
                }
                if ($entry['direction'] === LedgerDirection::Debit) $debits += $entry['amount_minor'];
                else $credits += $entry['amount_minor'];
            }
            if ($debits !== $credits) {
                throw ValidationException::withMessages(['entries' => 'Ledger debits and credits must be equal.']);
            }

            $transaction = LedgerTransaction::query()->create([
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'posted',
                'idempotency_key_hash' => $idempotencyKey ? hash('sha256', $idempotencyKey) : null,
                'description' => $description,
                'metadata' => $metadata,
                'posted_at' => now(),
            ]);

            foreach (array_values($entries) as $index => $entry) {
                /** @var LedgerAccount $account */
                $account = $accounts->get($entry['account_id']);
                $increasesNormalBalance = $entry['direction'] === $account->normal_balance;
                $newBalance = (int) $account->balance_minor + ($increasesNormalBalance ? $entry['amount_minor'] : -$entry['amount_minor']);
                if ($newBalance < 0 && ($account->metadata['allow_negative'] ?? false) !== true) {
                    throw ValidationException::withMessages(['balance' => 'This transaction would create a negative account balance.']);
                }
                $account->forceFill(['balance_minor' => $newBalance])->save();
                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'sequence' => $index + 1,
                    'direction' => $entry['direction'],
                    'amount_minor' => $entry['amount_minor'],
                    'currency' => $account->currency,
                    'balance_after_minor' => $newBalance,
                    'metadata' => $entry['metadata'] ?? null,
                ]);
            }

            return $transaction->load('entries.account');
        }, 5);
    }
}
