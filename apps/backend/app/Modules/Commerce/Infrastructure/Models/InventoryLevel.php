<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class InventoryLevel extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['on_hand'=>'integer','reserved'=>'integer','safety_stock'=>'integer']; }
    public function location(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'inventory_location_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id'); }
    public function reservations(): HasMany { return $this->hasMany(InventoryReservation::class); }
    public function available(): int { return max(0, (int)$this->on_hand - (int)$this->reserved - (int)$this->safety_stock); }
}
