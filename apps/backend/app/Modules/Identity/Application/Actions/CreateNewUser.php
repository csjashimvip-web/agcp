<?php

namespace Modules\Identity\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Infrastructure\Models\Role;
use Modules\Identity\Infrastructure\Models\TenantMembership;
use Modules\Tenancy\Application\TenantContext;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        $tenantId = $this->tenantContext->requireId();

        return DB::transaction(function () use ($input, $tenantId): User {
            $user = User::query()->create([
                'name' => trim((string) $input['name']),
                'email' => Str::lower(trim((string) $input['email'])),
                'password' => $input['password'],
                'status' => 'active',
                'locale' => 'en',
                'timezone' => 'UTC',
                'password_changed_at' => now(),
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $customerRole = Role::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', 'customer')
                ->firstOrFail();

            $user->roles()->syncWithoutDetaching([$customerRole->id]);

            $this->audit->record(
                action: 'identity.user.registered',
                subjectType: User::class,
                subjectId: $user->id,
                context: ['email' => $user->email],
                tenantId: $tenantId,
                actorType: User::class,
                actorId: $user->id,
            );

            return $user;
        });
    }
}
