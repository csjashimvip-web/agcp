<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenancy\Application\TenantContext;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenantId = app(TenantContext::class)->id();
        $roles = $this->roles()
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('roles.tenant_id');
                if ($tenantId !== null) {
                    $query->orWhere('roles.tenant_id', $tenantId);
                }
            })
            ->with('permissions:id,slug,name,group')
            ->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'passkeys_enabled' => $this->passkeys()->exists(),
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'scope' => $role->tenant_id === null ? 'platform' : 'tenant',
            ])->values(),
            'permissions' => $roles->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values(),
            'last_login_at' => $this->last_login_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
