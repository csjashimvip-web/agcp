<?php

use App\Http\Middleware\ApplyApiSecurityHeaders;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Identity\Http\Middleware\EnsureAccountActive;
use Modules\Identity\Http\Middleware\EnsureAdminTwoFactor;
use Modules\Identity\Http\Middleware\RequirePermission;
use Modules\Identity\Http\Middleware\TrackAuthenticatedSession;
use Modules\SaaS\Http\Middleware\RequireTenantFeature;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Prevent Laravel from looking for an undefined named "login" route.
        $middleware->redirectGuestsTo('/login');

        $middleware->append(AssignCorrelationId::class);
        $middleware->append(ApplyApiSecurityHeaders::class);

        $middleware->alias([
            'tenant' => ResolveTenantContext::class,
            'account.active' => EnsureAccountActive::class,
            'permission' => RequirePermission::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
            'auth.session' => TrackAuthenticatedSession::class,
            'feature' => RequireTenantFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        // Every unauthenticated API request must return JSON 401.
        $exceptions->render(
            function (AuthenticationException $exception, Request $request) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                        'code' => 'AUTHENTICATION_REQUIRED',
                    ], 401);
                }

                return null;
            }
        );
    })
    ->create();