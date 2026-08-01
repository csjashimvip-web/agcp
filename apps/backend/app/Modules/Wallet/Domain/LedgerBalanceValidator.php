<?php

namespace App\Modules\Wallet\Domain;

use InvalidArgumentException;

final class LedgerBalanceValidator
{
    /**
     * @param array<int, array{direction:string,amount_minor:int}> $entries
     */
    public function assertBalanced(array $entries): void
    {
        if (count($entries) < 2) {
            throw new InvalidArgumentException('A ledger transaction requires at least two entries.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($entries as $entry) {
            $amount = (int) ($entry['amount_minor'] ?? 0);
            $direction = $entry['direction'] ?? null;

            if ($amount <= 0) {
                throw new InvalidArgumentException('Ledger entry amount must be a positive integer minor-unit value.');
            }

            if ($direction === 'debit') {
                $debits += $amount;
                continue;
            }

            if ($direction === 'credit') {
                $credits += $amount;
                continue;
            }

            throw new InvalidArgumentException('Ledger entry direction must be debit or credit.');
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException('Ledger transaction is not balanced.');
        }
    }
}