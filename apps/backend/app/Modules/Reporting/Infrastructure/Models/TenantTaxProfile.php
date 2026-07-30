<?php
namespace Modules\Reporting\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class TenantTaxProfile extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['next_invoice_sequence'=>'integer','address'=>'array'];}
    public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
}
