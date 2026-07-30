<?php
namespace Modules\Rules\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
final class PriceQuote extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['quantity'=>'integer','base_amount_minor'=>'integer','adjustment_minor'=>'integer','final_amount_minor'=>'integer','matched_rule_ids'=>'array','breakdown'=>'array','expires_at'=>'immutable_datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function variant(): BelongsTo { return $this->belongsTo(CatalogVariant::class,'catalog_variant_id'); }
}
