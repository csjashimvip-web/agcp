<?php
namespace Modules\Tenancy\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TenantDomain extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['verified'=>'boolean','verified_at'=>'immutable_datetime']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
