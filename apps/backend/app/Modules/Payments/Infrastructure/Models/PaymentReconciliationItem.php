<?php
namespace Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentReconciliationItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'actual_amount_minor' => 'integer',
            'evidence' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function run(): BelongsTo { return $this->belongsTo(PaymentReconciliationRun::class, 'reconciliation_run_id'); }
    public function intent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
