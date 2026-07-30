<?php
namespace Modules\Reporting\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class CustomerTaxProfile extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['address'=>'array','tax_exempt'=>'boolean','metadata'=>'array'];}
    public function user():BelongsTo{return $this->belongsTo(User::class);}
}
