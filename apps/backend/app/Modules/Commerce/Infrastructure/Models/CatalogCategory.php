<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class CatalogCategory extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function items(): HasMany { return $this->hasMany(CatalogItem::class, 'category_id'); }
}
