<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class CatalogPrice extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['amount_minor'=>'integer','compare_at_minor'=>'integer','min_quantity'=>'integer','max_quantity'=>'integer','metadata'=>'array']; }
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id'); }
}
