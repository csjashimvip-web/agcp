<?php
namespace Modules\Wallet\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WalletHold extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
}
