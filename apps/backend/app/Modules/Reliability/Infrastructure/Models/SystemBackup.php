<?php

namespace Modules\Reliability\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SystemBackup extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function restoreDrills(): HasMany
    {
        return $this->hasMany(RestoreDrill::class);
    }
}
