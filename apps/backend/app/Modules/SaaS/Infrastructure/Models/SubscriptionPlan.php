<?php
namespace Modules\SaaS\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionPlan extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'price_monthly_minor' => 'integer', 'price_yearly_minor' => 'integer', 'trial_days' => 'integer',
            'features' => 'array', 'limits' => 'array', 'is_public' => 'boolean', 'sort_order' => 'integer',
        ];
    }
    public function subscriptions(): HasMany { return $this->hasMany(TenantSubscription::class); }
}
