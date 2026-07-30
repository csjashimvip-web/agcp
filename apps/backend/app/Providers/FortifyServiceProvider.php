<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;
use Modules\Identity\Application\Actions\CreateNewUser;
use Modules\Identity\Application\Actions\ResetUserPassword;
use Modules\Identity\Application\Actions\UpdateUserPassword;
use Modules\Identity\Application\Actions\UpdateUserProfileInformation;
use Modules\Tenancy\Application\TenantContext;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input('email')));
            $user = User::query()->where('email', $email)->first();

            if ($user === null || $user->status !== 'active' || $user->locked_until?->isFuture()) {
                return null;
            }

            if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null;
            }

            $tenantId = app(TenantContext::class)->id();

            return $user->canAccessTenant($tenantId) ? $user : null;
        });

        Passkeys::authorizeLoginUsing(function (Request $request, $user): bool {
            if (! $user instanceof User || $user->status !== 'active' || $user->locked_until?->isFuture()) {
                return false;
            }

            return $user->canAccessTenant(app(TenantContext::class)->id());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->session()->get('login.id', $request->ip())));

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = rtrim((string) env('PASSWORD_RESET_URL', config('app.url').'/reset-password'), '/');

            return $base.'?token='.urlencode($token).'&email='.urlencode($user->email);
        });
    }
}
