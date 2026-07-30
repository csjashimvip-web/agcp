<?php
namespace Modules\Payments\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Payments\Domain\Enums\ReconciliationRunStatus;

final class PaymentReconciliationRun extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReconciliationRunStatus::class,
            'checked_count' => 'integer',
            'mismatch_count' => 'integer',
            'resolved_count' => 'integer',
            'summary' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function providerAccount(): BelongsTo { return $this->belongsTo(PaymentProviderAccount::class, 'provider_account_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function items(): HasMany { return $this->hasMany(PaymentReconciliationItem::class, 'reconciliation_run_id'); }
}
