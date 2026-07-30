<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class InventoryReservation extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['quantity'=>'integer','expires_at'=>'immutable_datetime','released_at'=>'immutable_datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function inventoryLevel(): BelongsTo { return $this->belongsTo(InventoryLevel::class); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id'); }
}
