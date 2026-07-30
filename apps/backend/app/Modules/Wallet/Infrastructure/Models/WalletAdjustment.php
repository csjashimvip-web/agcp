<?php
namespace Modules\Wallet\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wallet\Domain\Enums\AdjustmentStatus;
use Modules\Wallet\Domain\Enums\LedgerDirection;
class WalletAdjustment extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'amount_minor' => 'integer',
            'status' => AdjustmentStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class); }
}
