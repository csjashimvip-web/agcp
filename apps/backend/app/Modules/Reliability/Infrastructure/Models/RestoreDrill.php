<?php

namespace Modules\Reliability\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RestoreDrill extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checksum_verified' => 'boolean',
            'decryption_verified' => 'boolean',
            'archive_verified' => 'boolean',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(SystemBackup::class, 'system_backup_id');
    }
}
