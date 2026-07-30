<?php
namespace Modules\Analytics\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
final class SupplierRecommendation extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['score'=>'float','confidence'=>'float','candidates'=>'array','generated_at'=>'immutable_datetime','expires_at'=>'immutable_datetime']; }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class,'catalog_variant_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(SupplierAccount::class,'recommended_supplier_account_id'); }
}
