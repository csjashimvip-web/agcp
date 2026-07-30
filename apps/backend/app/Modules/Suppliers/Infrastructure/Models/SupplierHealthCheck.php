<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierHealthCheck extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'latency_ms' => 'integer',
            'response_payload' => 'array',
            'checked_at' => 'immutable_datetime',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(SupplierAccount::class, 'supplier_account_id'); }
}
