<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Domain\Concerns\HasRolesAndPermissions;
use Modules\Identity\Infrastructure\Models\AuthSession;
use Modules\Identity\Infrastructure\Models\TenantMembership;
use Modules\Identity\Infrastructure\Models\UserDevice;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRolesAndPermissions;
    use HasUuids;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'locale',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password_changed_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class);
    }

    public function canAccessTenant(?string $tenantId): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->isPlatformAdministrator()) {
            return true;
        }

        if ($tenantId === null) {
            return false;
        }

        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
    }
}
