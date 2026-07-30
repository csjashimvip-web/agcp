<?php
namespace Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Domain\Enums\PaymentWebhookStatus;

final class PaymentWebhook extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PaymentWebhookStatus::class,
            'payload' => 'encrypted:array',
            'headers' => 'encrypted:array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function providerAccount(): BelongsTo { return $this->belongsTo(PaymentProviderAccount::class, 'provider_account_id'); }
    public function intent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
