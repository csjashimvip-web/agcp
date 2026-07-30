<?php
namespace Modules\Reporting\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Infrastructure\Models\OrderItem;
final class InvoiceLine extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['sequence'=>'integer','quantity'=>'integer','unit_price_minor'=>'integer','net_minor'=>'integer','tax_rate_basis_points'=>'integer','tax_minor'=>'integer','gross_minor'=>'integer','tax_snapshot'=>'array','metadata'=>'array'];}
    public function invoice():BelongsTo{return $this->belongsTo(Invoice::class);}
    public function orderItem():BelongsTo{return $this->belongsTo(OrderItem::class);}
    public function taxRate():BelongsTo{return $this->belongsTo(TaxRate::class);}
}
