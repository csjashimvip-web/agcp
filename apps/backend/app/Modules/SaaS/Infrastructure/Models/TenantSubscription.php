<?php
namespace Modules\SaaS\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SaaS\Domain\Enums\SubscriptionStatus;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class TenantSubscription extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class, 'started_at' => 'immutable_datetime', 'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime', 'current_period_end' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean', 'canceled_at' => 'immutable_datetime', 'metadata' => 'array',
        ];
    }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
}
