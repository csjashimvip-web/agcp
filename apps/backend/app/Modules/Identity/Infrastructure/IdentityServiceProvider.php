<?php

namespace Modules\Identity\Infrastructure;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Infrastructure\Listeners\RecordSuccessfulLogin;
use Modules\Identity\Infrastructure\Listeners\RecordSuccessfulLogout;
use Modules\Tenancy\Application\TenantContext;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $tenantId = app(TenantContext::class)->id();

            return $user->hasPermission($ability, $tenantId) ? true : null;
        });

        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(Logout::class, RecordSuccessfulLogout::class);
    }
}
