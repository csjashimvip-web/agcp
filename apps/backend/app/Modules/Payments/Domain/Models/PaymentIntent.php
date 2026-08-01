<?php

namespace App\Modules\Payments\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentIntent extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'wallet_id',
        'payment_provider_id',
        'intent_uuid',
        'idempotency_key',
        'provider_reference',
        'status',
        'amount_minor',
        'provider_fee_minor',
        'currency',
        'deposit_id',
        'metadata',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'provider_fee_minor' => 'integer',
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}