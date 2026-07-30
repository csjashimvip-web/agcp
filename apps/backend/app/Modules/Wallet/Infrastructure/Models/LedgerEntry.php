<?php
namespace Modules\Wallet\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Wallet\Domain\Enums\LedgerDirection;
class LedgerEntry extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries cannot be deleted.'));
    }
    public function transaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id'); }
    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'ledger_account_id'); }
}
