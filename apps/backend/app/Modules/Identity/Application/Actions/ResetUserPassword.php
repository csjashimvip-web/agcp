<?php

namespace Modules\Identity\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
            'remember_token' => null,
        ])->save();

        $user->tokens()->delete();
        $user->authSessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
