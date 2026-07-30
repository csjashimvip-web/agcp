<?php
namespace Modules\Wallet\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Wallet\Domain\Enums\WalletType;
class Wallet extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['type' => WalletType::class, 'limits' => 'array']; }
    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'ledger_account_id'); }
    public function holds(): HasMany { return $this->hasMany(WalletHold::class); }
    public function deposits(): HasMany { return $this->hasMany(DepositRequest::class); }
    public function activeHoldMinor(): int
    {
        return (int) $this->holds()->where('status', 'active')->sum('amount_minor');
    }
    public function availableBalanceMinor(): int
    {
        return (int) $this->account->balance_minor - $this->activeHoldMinor();
    }
}
