<?php
namespace Modules\Payments\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Domain\Enums\PaymentRefundStatus;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
use Modules\Wallet\Infrastructure\Models\WalletHold;

final class PaymentRefund extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PaymentRefundStatus::class,
            'metadata' => 'array',
            'requested_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function intent(): BelongsTo { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class); }
    public function walletHold(): BelongsTo { return $this->belongsTo(WalletHold::class); }
}
