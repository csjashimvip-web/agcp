<?php
namespace Modules\Wallet\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
class LedgerTransaction extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'posted_at' => 'immutable_datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted ledger transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger transactions cannot be deleted.'));
    }
    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }
}
