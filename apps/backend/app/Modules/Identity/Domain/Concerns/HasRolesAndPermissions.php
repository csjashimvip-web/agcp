<?php

namespace Modules\Identity\Domain\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Identity\Infrastructure\Models\Role;

trait HasRolesAndPermissions
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function hasRole(string $slug, ?string $tenantId = null): bool
    {
        return $this->roles()
            ->where('roles.slug', $slug)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('roles.tenant_id');
                if ($tenantId !== null) {
                    $query->orWhere('roles.tenant_id', $tenantId);
                }
            })
            ->exists();
    }

    public function hasPermission(string $permission, ?string $tenantId = null): bool
    {
        return $this->roles()
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('roles.tenant_id');
                if ($tenantId !== null) {
                    $query->orWhere('roles.tenant_id', $tenantId);
                }
            })
            ->whereHas('permissions', fn ($query) => $query->where('permissions.slug', $permission))
            ->exists();
    }

    public function isPlatformAdministrator(): bool
    {
        return $this->hasPermission('platform.admin.access');
    }
}
