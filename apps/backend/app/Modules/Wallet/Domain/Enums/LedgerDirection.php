<?php
namespace Modules\Wallet\Domain\Enums;
enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
