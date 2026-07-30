<?php
namespace Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Domain\Enums\PaymentAttemptStatus;

final class PaymentAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => PaymentAttemptStatus::class,
            'request_payload' => 'encrypted:array',
            'response_payload' => 'encrypted:array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function intent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
