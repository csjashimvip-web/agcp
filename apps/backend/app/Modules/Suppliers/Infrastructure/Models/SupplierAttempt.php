<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'routing_score' => 'float',
            'latency_ms' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function supplierOrder(): BelongsTo { return $this->belongsTo(SupplierOrder::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(SupplierAccount::class, 'supplier_account_id'); }
    public function service(): BelongsTo { return $this->belongsTo(SupplierService::class, 'supplier_service_id'); }
}
