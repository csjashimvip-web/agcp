<?php

namespace App\Modules\Wallet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class Wallet extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'ledger_account_id',
        'currency',
        'status',
        'available_balance_minor',
        'held_balance_minor',
    ];

    protected function casts(): array
    {
        return [
            'available_balance_minor' => 'integer',
            'held_balance_minor' => 'integer',
        ];
    }
}