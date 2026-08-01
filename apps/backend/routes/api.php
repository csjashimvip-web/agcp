<?php

use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Payments\Http\Controllers\PaymentWebhookController;
use App\Modules\Platform\Http\Controllers\AdminOverviewController;
use App\Modules\Platform\Http\Controllers\AdminResourceController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/platform', [PlatformController::class, 'show']);

    Route::middleware('web')->prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::get('/tenants', [TenantController::class, 'index'])
        ->middleware('auth:sanctum');

    Route::post('/payments/webhooks/{providerId}', PaymentWebhookController::class)
        ->whereNumber('providerId');

    Route::middleware(['auth:sanctum', 'agcp.tenant'])->group(function (): void {
        Route::post('/checkout', CheckoutController::class);

        Route::prefix('admin')->group(function (): void {
            Route::get('/overview', AdminOverviewController::class)
                ->middleware('agcp.permission:platform.architecture.view');

            Route::get('/products', [AdminResourceController::class, 'products'])
                ->middleware('agcp.permission:catalog.manage');

            Route::get('/orders', [AdminResourceController::class, 'orders'])
                ->middleware('agcp.permission:orders.manage');

            Route::get('/wallets', [AdminResourceController::class, 'wallets'])
                ->middleware('agcp.permission:wallet.view');

            Route::get('/suppliers', [AdminResourceController::class, 'suppliers'])
                ->middleware('agcp.permission:supplier.manage');
        });
    });
});