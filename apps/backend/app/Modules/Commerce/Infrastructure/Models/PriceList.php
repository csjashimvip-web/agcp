<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class PriceList extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['starts_at'=>'immutable_datetime','ends_at'=>'immutable_datetime','metadata'=>'array']; }
    public function prices(): HasMany { return $this->hasMany(CatalogPrice::class); }
}
