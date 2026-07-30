<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Domain\Enums\CatalogItemType;
final class CatalogItem extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'inventory_tracking' => 'boolean',
            'allow_backorder' => 'boolean',
            'service_schema' => 'array',
            'metadata' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
    public function category(): BelongsTo { return $this->belongsTo(CatalogCategory::class, 'category_id'); }
    public function variants(): HasMany { return $this->hasMany(CatalogVariant::class); }
}
