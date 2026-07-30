<?php
namespace Modules\Fraud\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class FraudSignal extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['score'=>'integer','evidence'=>'array']; }
    public function assessment(): BelongsTo { return $this->belongsTo(FraudRiskAssessment::class,'assessment_id'); }
}
