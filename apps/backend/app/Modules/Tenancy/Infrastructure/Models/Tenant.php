<?php
namespace Modules\Tenancy\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Tenant extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['settings'=>'array','activated_at'=>'immutable_datetime']; }
    public function domains(): HasMany { return $this->hasMany(TenantDomain::class); }
}
