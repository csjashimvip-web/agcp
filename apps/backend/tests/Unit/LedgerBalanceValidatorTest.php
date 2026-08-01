<?php

namespace Tests\Unit;

use App\Modules\Wallet\Domain\LedgerBalanceValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LedgerBalanceValidatorTest extends TestCase
{
    public function test_it_accepts_a_balanced_double_entry_transaction(): void
    {
        $validator = new LedgerBalanceValidator();

        $validator->assertBalanced([
            ['direction' => 'debit', 'amount_minor' => 12500],
            ['direction' => 'credit', 'amount_minor' => 12500],
        ]);

        $this->assertTrue(true);
    }

    public function test_it_rejects_an_unbalanced_ledger_transaction(): void
    {
        $validator = new LedgerBalanceValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ledger transaction is not balanced.');

        $validator->assertBalanced([
            ['direction' => 'debit', 'amount_minor' => 12500],
            ['direction' => 'credit', 'amount_minor' => 12000],
        ]);
    }
}