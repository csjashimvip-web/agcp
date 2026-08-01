<?php

namespace App\Modules\Orders\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'wallet_id',
        'order_uuid',
        'order_number',
        'status',
        'currency',
        'subtotal_minor',
        'discount_minor',
        'surcharge_minor',
        'total_minor',
        'ledger_transaction_id',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'surcharge_minor' => 'integer',
            'total_minor' => 'integer',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}