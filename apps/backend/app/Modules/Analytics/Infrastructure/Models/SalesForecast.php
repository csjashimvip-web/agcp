<?php
namespace Modules\Analytics\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class SalesForecast extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['horizon_days'=>'integer','basis_start'=>'immutable_date','basis_end'=>'immutable_date','predicted_revenue_minor'=>'integer','confidence'=>'float','trend_percent'=>'float','points'=>'array','generated_at'=>'immutable_datetime','expires_at'=>'immutable_datetime']; }
}
