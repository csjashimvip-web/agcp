<?php
namespace Modules\Wallet\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
class LedgerAccount extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'normal_balance' => LedgerDirection::class,
            'balance_minor' => 'integer',
            'metadata' => 'array',
        ];
    }
    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
}
