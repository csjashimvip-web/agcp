<?php

namespace App\Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SupplierOrder extends Model
{
    protected $fillable = [
        'tenant_id',
        'order_item_id',
        'supplier_id',
        'supplier_service_id',
        'supplier_order_uuid',
        'submission_key',
        'external_order_id',
        'status',
        'attempt',
        'cost_minor',
        'currency',
        'failure_reason',
        'request_payload',
        'response_payload',
        'submitted_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'cost_minor' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}