<?php

namespace App\Modules\Orders\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'sku',
        'name',
        'quantity',
        'unit_price_minor',
        'unit_cost_minor',
        'line_total_minor',
        'service_input',
        'fulfillment_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'unit_cost_minor' => 'integer',
            'line_total_minor' => 'integer',
            'service_input' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}