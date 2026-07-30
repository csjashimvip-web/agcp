<?php
namespace Modules\Reporting\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class TaxRate extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['rate_basis_points'=>'integer','price_inclusive'=>'boolean','valid_from'=>'immutable_datetime','valid_until'=>'immutable_datetime','metadata'=>'array'];}
    public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
}
