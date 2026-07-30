<?php
namespace Modules\Payments\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Payments\Domain\Enums\PaymentIntentStatus;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
use Modules\Wallet\Infrastructure\Models\Wallet;

final class PaymentIntent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'total_minor' => 'integer',
            'status' => PaymentIntentStatus::class,
            'metadata' => 'array',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function providerAccount(): BelongsTo { return $this->belongsTo(PaymentProviderAccount::class, 'provider_account_id'); }
    public function attempts(): HasMany { return $this->hasMany(PaymentAttempt::class); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); }
    public function feeLedgerTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class, 'fee_ledger_transaction_id'); }
    public function deposit(): HasOne { return $this->hasOne(DepositRequest::class); }
}
