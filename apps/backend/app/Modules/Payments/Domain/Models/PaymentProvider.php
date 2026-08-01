<?php

namespace App\Modules\Payments\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentProvider extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'driver',
        'status',
        'credentials_encrypted',
        'secret_payload',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}