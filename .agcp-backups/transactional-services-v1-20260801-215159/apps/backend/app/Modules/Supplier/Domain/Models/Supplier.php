<?php

namespace App\Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class Supplier extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'driver',
        'status',
        'priority',
        'timeout_seconds',
        'max_retries',
        'credentials_encrypted',
        'settings',
        'last_healthcheck_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'timeout_seconds' => 'integer',
            'max_retries' => 'integer',
            'credentials_encrypted' => 'array',
            'settings' => 'array',
            'last_healthcheck_at' => 'datetime',
        ];
    }
}