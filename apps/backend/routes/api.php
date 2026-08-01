<?php

use App\Modules\Catalog\Http\Controllers\AdminProductController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Orders\Http\Controllers\AdminOrderActionController;
use App\Modules\Payments\Http\Controllers\PaymentWebhookController;
use App\Modules\Platform\Http\Controllers\AdminOverviewController;
use App\Modules\Platform\Http\Controllers\AdminResourceController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use App\Modules\Reliability\Http\Controllers\AdminOperationsHealthController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierOperationsController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierRoutingController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierSyncController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use App\Modules\Wallet\Http\Controllers\AdminDepositController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/platform', [PlatformController::class, 'show']);

    Route::middleware('web')->prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/me', [AuthController::class, 'me'])
            ->middleware('auth:sanctum');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/tenants', [TenantController::class, 'index']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post(
            '/notifications/{notificationId}/read',
            [NotificationController::class, 'read']
        )->whereNumber('notificationId');
    });

    Route::post(
        '/payments/webhooks/{providerId}',
        PaymentWebhookController::class
    )->whereNumber('providerId');

    Route::middleware(['auth:sanctum', 'agcp.tenant'])->group(function (): void {
        Route::post('/checkout', CheckoutController::class);

        Route::prefix('admin')->group(function (): void {
            Route::get('/overview', AdminOverviewController::class)
                ->middleware('agcp.permission:platform.architecture.view');

            Route::get('/operations/health', AdminOperationsHealthController::class)
                ->middleware('agcp.permission:reliability.audit.view');

            Route::get('/products', [AdminResourceController::class, 'products'])
                ->middleware('agcp.permission:catalog.manage');
            Route::post('/products', [AdminProductController::class, 'store'])
                ->middleware('agcp.permission:catalog.manage');
            Route::patch(
                '/products/{productId}',
                [AdminProductController::class, 'update']
            )
                ->whereNumber('productId')
                ->middleware('agcp.permission:catalog.manage');

            Route::get('/orders', [AdminResourceController::class, 'orders'])
                ->middleware('agcp.permission:orders.manage');
            Route::post(
                '/orders/{orderId}/cancel',
                [AdminOrderActionController::class, 'cancel']
            )
                ->whereNumber('orderId')
                ->middleware('agcp.permission:orders.manage');
            Route::post(
                '/orders/{orderId}/retry',
                [AdminOrderActionController::class, 'retry']
            )
                ->whereNumber('orderId')
                ->middleware('agcp.permission:orders.manage');

            Route::get('/wallets', [AdminResourceController::class, 'wallets'])
                ->middleware('agcp.permission:wallet.view');

            Route::get('/deposits', [AdminDepositController::class, 'index'])
                ->middleware('agcp.permission:wallet.view');
            Route::post(
                '/deposits/{depositId}/approve',
                [AdminDepositController::class, 'approve']
            )
                ->whereNumber('depositId')
                ->middleware('agcp.permission:wallet.manage');

            Route::get(
                '/suppliers',
                [AdminResourceController::class, 'suppliers']
            )->middleware('agcp.permission:supplier.manage');

            Route::post(
                '/suppliers',
                [AdminSupplierController::class, 'store']
            )->middleware('agcp.permission:supplier.manage');

            Route::patch(
                '/suppliers/{supplierId}',
                [AdminSupplierController::class, 'update']
            )
                ->whereNumber('supplierId')
                ->middleware('agcp.permission:supplier.manage');

            Route::post(
                '/suppliers/{supplierId}/test',
                [AdminSupplierController::class, 'testConnection']
            )
                ->whereNumber('supplierId')
                ->middleware('agcp.permission:supplier.manage');

            Route::post(
                '/suppliers/{supplierId}/sync',
                [AdminSupplierSyncController::class, 'sync']
            )
                ->whereNumber('supplierId')
                ->middleware('agcp.permission:supplier.manage');

            Route::get(
                '/suppliers/{supplierId}/inbox',
                [AdminSupplierSyncController::class, 'inbox']
            )
                ->whereNumber('supplierId')
                ->middleware('agcp.permission:supplier.manage');

            Route::get(
                '/supplier-inbox',
                [AdminSupplierOperationsController::class, 'inbox']
            )->middleware('agcp.permission:supplier.manage');

            Route::post(
                '/supplier-inbox/{inboxId}/map',
                [AdminSupplierRoutingController::class, 'map']
            )
                ->whereNumber('inboxId')
                ->middleware('agcp.permission:supplier.manage');

            Route::get(
                '/supplier-routing',
                [AdminSupplierOperationsController::class, 'routing']
            )->middleware('agcp.permission:supplier.manage');

            Route::patch(
                '/supplier-routing/{routeId}',
                [AdminSupplierOperationsController::class, 'updateRoute']
            )
                ->whereNumber('routeId')
                ->middleware('agcp.permission:supplier.manage');

            Route::get(
                '/supplier-orders',
                [AdminSupplierOperationsController::class, 'orders']
            )->middleware('agcp.permission:supplier.manage');

            Route::post(
                '/supplier-orders/{supplierOrderId}/reconcile',
                [AdminSupplierOperationsController::class, 'reconcile']
            )
                ->whereNumber('supplierOrderId')
                ->middleware('agcp.permission:supplier.manage');
        });
    });
});