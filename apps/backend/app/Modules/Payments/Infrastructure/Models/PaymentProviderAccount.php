<?php
namespace Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentProviderAccount extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'currencies' => 'array',
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'metadata' => 'array',
            'minimum_amount_minor' => 'integer',
            'maximum_amount_minor' => 'integer',
            'fee_basis_points' => 'integer',
            'fee_fixed_minor' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function intents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'provider_account_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class, 'provider_account_id');
    }
}
