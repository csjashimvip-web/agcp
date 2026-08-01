<?php

namespace App\Modules\Wallet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class LedgerTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'transaction_uuid',
        'idempotency_key',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'posted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}