<?php

namespace App\Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SupplierService extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'external_service_id',
        'external_name',
        'cost_minor',
        'currency',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'metadata' => 'array',
        ];
    }
}