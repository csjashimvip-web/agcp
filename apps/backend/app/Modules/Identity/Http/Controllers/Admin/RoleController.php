<?php

namespace Modules\Identity\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Infrastructure\Models\Permission;
use Modules\Identity\Infrastructure\Models\Role;
use Modules\Tenancy\Application\TenantContext;

class RoleController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->requireId();
        $roles = Role::query()
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->with('permissions:id,slug,name,group')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => $this->serialize($role)),
            'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get(['id', 'slug', 'name', 'group']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->requireId();
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:100', 'regex:/^[a-z0-9.-]+$/',
                Rule::unique('roles', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1', 'max:100'],
            'permissions.*' => ['uuid', 'distinct', Rule::exists('permissions', 'id')],
        ]);

        $role = DB::transaction(function () use ($validated, $tenantId): Role {
            $slug = $validated['slug'] ?? Str::slug($validated['name']);

            abort_if($slug === '', 422, 'A valid role slug is required.');
            abort_if(Role::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists(), 422, 'The role slug is already in use.');

            $role = Role::query()->create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);
            $role->permissions()->sync($validated['permissions']);

            return $role;
        });

        return response()->json(['data' => $this->serialize($role->load('permissions'))], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $tenantId = $this->tenantContext->requireId();
        abort_if($role->tenant_id === null || $role->tenant_id !== $tenantId || $role->is_system, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1', 'max:100'],
            'permissions.*' => ['uuid', 'distinct', Rule::exists('permissions', 'id')],
        ]);

        DB::transaction(function () use ($role, $validated): void {
            $role->update(['name' => $validated['name'], 'description' => $validated['description'] ?? null]);
            $role->permissions()->sync($validated['permissions']);
        });

        return response()->json(['data' => $this->serialize($role->refresh()->load('permissions'))]);
    }

    public function assign(Request $request, User $user, Role $role): JsonResponse
    {
        $tenantId = $this->tenantContext->requireId();
        $this->assertActiveTenantMember($user, $tenantId);
        abort_unless($role->tenant_id === $tenantId, 404);

        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->audit->record(
            action: 'identity.role.assigned',
            subjectType: User::class,
            subjectId: $user->id,
            context: ['role' => $role->slug],
            tenantId: $tenantId,
            actorType: User::class,
            actorId: $request->user()->id,
        );

        return response()->json(['message' => 'Role assigned.']);
    }

    public function revoke(Request $request, User $user, Role $role): JsonResponse
    {
        $tenantId = $this->tenantContext->requireId();
        $this->assertActiveTenantMember($user, $tenantId);
        abort_unless($role->tenant_id === $tenantId, 404);

        if ($role->is_system && $role->slug === 'customer') {
            abort(403, 'The default customer role cannot be revoked.');
        }

        if ($role->is_system && $role->slug === 'tenant-admin') {
            $otherTenantAdmins = User::query()
                ->whereKeyNot($user->id)
                ->where('status', 'active')
                ->whereHas('memberships', fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))
                ->whereHas('roles', fn ($query) => $query->whereKey($role->id))
                ->exists();

            abort_unless($otherTenantAdmins, 422, 'Assign another active tenant administrator before revoking this role.');
        }

        $user->roles()->detach($role->id);
        $this->audit->record(
            action: 'identity.role.revoked',
            subjectType: User::class,
            subjectId: $user->id,
            context: ['role' => $role->slug],
            tenantId: $tenantId,
            actorType: User::class,
            actorId: $request->user()->id,
        );

        return response()->json(['message' => 'Role revoked.']);
    }

    private function assertActiveTenantMember(User $user, string $tenantId): void
    {
        $isMember = $user->memberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 404);
    }

    private function serialize(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'scope' => $role->tenant_id === null ? 'platform' : 'tenant',
            'system' => $role->is_system,
            'permissions' => $role->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'name' => $permission->name,
                'group' => $permission->group,
            ])->values(),
        ];
    }
}
