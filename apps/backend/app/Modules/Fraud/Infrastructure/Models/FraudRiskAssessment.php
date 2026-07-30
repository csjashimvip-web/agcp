<?php
namespace Modules\Fraud\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Fraud\Domain\Enums\FraudDecision;
use Modules\Fraud\Domain\Enums\RiskLevel;
final class FraudRiskAssessment extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['score'=>'integer','level'=>RiskLevel::class,'decision'=>FraudDecision::class,'context'=>'array','reviewed_at'=>'immutable_datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
    public function signals(): HasMany { return $this->hasMany(FraudSignal::class,'assessment_id'); }
}
