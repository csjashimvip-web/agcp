<?php
namespace Modules\Reporting\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Reporting\Domain\Enums\InvoiceStatus;
final class Invoice extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['status'=>InvoiceStatus::class,'subtotal_minor'=>'integer','discount_minor'=>'integer','surcharge_minor'=>'integer','tax_minor'=>'integer','total_minor'=>'integer','amount_paid_minor'=>'integer','amount_due_minor'=>'integer','seller_snapshot'=>'array','buyer_snapshot'=>'array','tax_snapshot'=>'array','metadata'=>'array','issued_at'=>'immutable_datetime','due_at'=>'immutable_datetime','paid_at'=>'immutable_datetime','voided_at'=>'immutable_datetime'];}
    public function order():BelongsTo{return $this->belongsTo(Order::class);}
    public function user():BelongsTo{return $this->belongsTo(User::class);}
    public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
    public function lines():HasMany{return $this->hasMany(InvoiceLine::class)->orderBy('sequence');}
    public function events():HasMany{return $this->hasMany(InvoiceEvent::class)->orderBy('created_at');}
}
