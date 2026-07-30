<?php

namespace Modules\Reliability\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class RuntimeHeartbeat extends Model
{
    protected $primaryKey = 'component';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
