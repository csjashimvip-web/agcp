<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
final class Cart extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['expires_at'=>'immutable_datetime','metadata'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(CartItem::class); }
    public function subtotalMinor(): int { return (int)$this->items->sum(fn(CartItem $item): int => $item->unit_price_minor * $item->quantity); }
}
