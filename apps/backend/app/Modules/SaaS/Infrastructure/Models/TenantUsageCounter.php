<?php
namespace Modules\SaaS\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TenantUsageCounter extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return ['period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime', 'quantity' => 'integer', 'limit_snapshot' => 'integer', 'metadata' => 'array'];
    }
}
