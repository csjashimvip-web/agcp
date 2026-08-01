<?php

namespace App\Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    protected $fillable = [
        'tenant_id',
        'sku',
        'name',
        'slug',
        'type',
        'description',
        'status',
        'currency',
        'price_minor',
        'cost_minor',
        'service_schema',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'cost_minor' => 'integer',
            'service_schema' => 'array',
            'metadata' => 'array',
        ];
    }
}