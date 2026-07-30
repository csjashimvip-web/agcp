<?php

namespace Modules\Identity\Application\Actions;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')->ignore($user->id)],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'bn'])],
            'timezone' => ['sometimes', 'timezone:all'],
        ])->validateWithBag('updateProfileInformation');

        $email = Str::lower(trim((string) $input['email']));

        if ($email !== $user->email && $user instanceof MustVerifyEmail) {
            $user->forceFill([
                'name' => trim((string) $input['name']),
                'email' => $email,
                'email_verified_at' => null,
                'locale' => $input['locale'] ?? $user->locale,
                'timezone' => $input['timezone'] ?? $user->timezone,
            ])->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill([
            'name' => trim((string) $input['name']),
            'email' => $email,
            'locale' => $input['locale'] ?? $user->locale,
            'timezone' => $input['timezone'] ?? $user->timezone,
        ])->save();
    }
}
