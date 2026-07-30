<?php
namespace Modules\Commerce\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class InventoryLocation extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function levels(): HasMany { return $this->hasMany(InventoryLevel::class); }
}
