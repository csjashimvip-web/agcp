<?php

use App\Http\Middleware\ApplyApiSecurityHeaders;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })
    ->create();
