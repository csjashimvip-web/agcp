<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Suppliers\Domain\Enums\SupplierAccountStatus;

final class SupplierAccount extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SupplierAccountStatus::class,
            'priority' => 'integer',
            'timeout_seconds' => 'integer',
            'max_retries' => 'integer',
            'country_codes' => 'array',
            'credentials' => 'encrypted:array',
            'health_score' => 'float',
            'success_rate' => 'float',
            'average_latency_ms' => 'integer',
            'total_requests' => 'integer',
            'successful_requests' => 'integer',
            'failed_requests' => 'integer',
            'consecutive_failures' => 'integer',
            'last_checked_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
            'disabled_until' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(SupplierService::class);
    }

    public function available(): bool
    {
        return $this->status !== SupplierAccountStatus::Disabled
            && ($this->disabled_until === null || $this->disabled_until->isPast());
    }
}
