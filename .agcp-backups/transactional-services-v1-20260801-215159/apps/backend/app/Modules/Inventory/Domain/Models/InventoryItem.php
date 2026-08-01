<?php

namespace App\Modules\Inventory\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class InventoryItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'on_hand',
        'reserved',
        'reorder_level',
        'track_inventory',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'reorder_level' => 'integer',
            'track_inventory' => 'boolean',
        ];
    }
}