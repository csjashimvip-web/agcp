<?php

namespace Modules\Commerce\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusHistory extends Model
{
    use HasUuids;

    /**
     * The commerce migration intentionally created this append-only table
     * using a singular history name. Declare it explicitly instead of
     * relying on Eloquent's plural table-name convention.
     */
    protected $table = 'order_status_history';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
