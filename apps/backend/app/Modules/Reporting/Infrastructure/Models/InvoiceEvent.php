<?php
namespace Modules\Reporting\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class InvoiceEvent extends Model
{
    use HasUuids;
    public const UPDATED_AT=null;
    protected $guarded=[];
    protected function casts():array{return ['data'=>'array','created_at'=>'immutable_datetime'];}
    public function invoice():BelongsTo{return $this->belongsTo(Invoice::class);}
    public function actor():BelongsTo{return $this->belongsTo(User::class,'actor_id');}
}
