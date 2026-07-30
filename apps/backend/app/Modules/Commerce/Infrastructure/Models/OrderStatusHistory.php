<?php
namespace Modules\Commerce\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class OrderStatusHistory extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['metadata'=>'array','created_at'=>'immutable_datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
