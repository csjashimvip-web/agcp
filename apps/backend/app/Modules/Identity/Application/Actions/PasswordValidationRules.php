<?php

namespace Modules\Identity\Application\Actions;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    protected function passwordRules(bool $confirmed = true): array
    {
        $rules = ['required', 'string', Password::min(12)->mixedCase()->letters()->numbers()->symbols()];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
