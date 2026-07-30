<?php
namespace Modules\Analytics\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Analytics\Domain\Enums\CustomerSegmentCode;
final class CustomerSegment extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['segment_code'=>CustomerSegmentCode::class,'score'=>'integer','recency_days'=>'integer','frequency_orders'=>'integer','monetary_minor'=>'integer','average_order_minor'=>'integer','last_order_at'=>'immutable_datetime','signals'=>'array','calculated_at'=>'immutable_datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
