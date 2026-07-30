<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class CartItem extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['quantity'=>'integer','unit_price_minor'=>'integer','configuration'=>'array']; }
    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id'); }
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
}
