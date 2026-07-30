<?php

namespace Modules\Identity\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Tenancy\Application\TenantContext;

class UserController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'locked'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $this->tenantContext->id();
        $users = User::query()
            ->whereHas('memberships', fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'data' => UserResource::collection($users->getCollection()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): UserResource
    {
        abort_unless($this->isActiveTenantMember($user), 404);

        return new UserResource($user);
    }

    public function updateStatus(Request $request, User $user): UserResource
    {
        abort_unless($this->isActiveTenantMember($user), 404);
        abort_if($user->is($request->user()), 422, 'You cannot change your own account status.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'locked'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $old = $user->status;
        $user->forceFill([
            'status' => $validated['status'],
            'locked_until' => $validated['status'] === 'locked' ? now()->addDay() : null,
        ])->save();

        if ($validated['status'] !== 'active') {
            $user->tokens()->delete();
            $user->authSessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }

        $this->audit->record(
            action: 'identity.user.status_changed',
            subjectType: User::class,
            subjectId: $user->id,
            context: ['reason' => $validated['reason']],
            changes: ['status' => ['old' => $old, 'new' => $validated['status']]],
            tenantId: $this->tenantContext->id(),
            actorType: User::class,
            actorId: $request->user()->id,
        );

        return new UserResource($user->refresh());
    }

    private function isActiveTenantMember(User $user): bool
    {
        return $user->memberships()
            ->where('tenant_id', $this->tenantContext->requireId())
            ->where('status', 'active')
            ->exists();
    }
}
