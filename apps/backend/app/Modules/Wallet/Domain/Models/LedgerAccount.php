<?php

namespace App\Modules\Wallet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class LedgerAccount extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'currency',
        'status',
        'balance_minor',
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
        ];
    }
}