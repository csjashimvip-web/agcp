<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class CatalogVariant extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['attributes' => 'array', 'metadata' => 'array', 'is_default' => 'boolean']; }
    public function item(): BelongsTo { return $this->belongsTo(CatalogItem::class, 'catalog_item_id'); }
    public function prices(): HasMany { return $this->hasMany(CatalogPrice::class); }
    public function inventoryLevels(): HasMany { return $this->hasMany(InventoryLevel::class); }
}
