<?php
namespace Modules\Tenancy\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;
class Tenant extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['settings'=>'array','activated_at'=>'immutable_datetime']; }
    public function domains(): HasMany { return $this->hasMany(TenantDomain::class); }
    public function subscriptions(): HasMany { return $this->hasMany(TenantSubscription::class); }
    public function currentSubscription(): HasOne { return $this->hasOne(TenantSubscription::class)->latestOfMany('started_at'); }
    public function branding(): HasOne { return $this->hasOne(TenantBrandingProfile::class); }
}
