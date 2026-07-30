<?php

namespace Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Application\TenantContext;

class ApiTokenController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toAtomString(),
            'expires_at' => $token->expires_at?->toAtomString(),
            'created_at' => $token->created_at?->toAtomString(),
        ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $available = $request->user()->roles()
            ->where(function ($query): void {
                $query->whereNull('roles.tenant_id');
                if ($this->tenantContext->id() !== null) {
                    $query->orWhere('roles.tenant_id', $this->tenantContext->id());
                }
            })
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'current_password' => ['required', 'current_password:web'],
            'abilities' => ['sometimes', 'array', 'max:50'],
            'abilities.*' => ['string', Rule::in($available)],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $abilities = $validated['abilities'] ?? array_values(array_intersect($available, ['profile.read', 'profile.update']));
        $expiresAt = now()->addDays((int) ($validated['expires_in_days'] ?? 30));
        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $expiresAt->toAtomString(),
                'token' => $token->plainTextToken,
            ],
            'message' => 'Copy this token now. It will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();
        abort_if($deleted === 0, 404);

        return response()->json(['message' => 'API token revoked.']);
    }
}
