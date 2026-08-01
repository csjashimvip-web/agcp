<?php

namespace App\Modules\Wallet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class LedgerEntry extends Model
{
    protected $fillable = [
        'ledger_transaction_id',
        'ledger_account_id',
        'direction',
        'amount_minor',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }
}