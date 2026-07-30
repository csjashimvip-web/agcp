<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
final class OrderItem extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['quantity'=>'integer','unit_price_minor'=>'integer','total_minor'=>'integer','configuration'=>'array','metadata'=>'array']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id'); }
    public function supplierOrder(): HasOne { return $this->hasOne(SupplierOrder::class); }
}
