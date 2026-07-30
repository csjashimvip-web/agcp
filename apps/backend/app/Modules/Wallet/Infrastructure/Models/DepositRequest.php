<?php
namespace Modules\Wallet\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wallet\Domain\Enums\DepositStatus;
class DepositRequest extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => DepositStatus::class,
            'admin_note' => 'encrypted',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function paymentIntent(): BelongsTo { return $this->belongsTo(\Modules\Payments\Infrastructure\Models\PaymentIntent::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class); }
}
