<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\Admin\AdminCatalogController;
use Modules\Commerce\Http\Controllers\Admin\AdminInventoryController;
use Modules\Commerce\Http\Controllers\Admin\AdminOrderController;
use Modules\Commerce\Http\Controllers\Admin\AdminPricingController;
use Modules\Commerce\Http\Controllers\CartController;
use Modules\Commerce\Http\Controllers\CatalogController;
use Modules\Commerce\Http\Controllers\CheckoutController;
use Modules\Commerce\Http\Controllers\OrderController;
use Modules\Identity\Http\Controllers\Admin\RoleController;
use Modules\Identity\Http\Controllers\Admin\UserController as AdminUserController;
use Modules\Identity\Http\Controllers\Auth\ApiTokenController;
use Modules\Identity\Http\Controllers\Auth\CurrentUserController;
use Modules\Identity\Http\Controllers\Auth\DeviceController;
use Modules\Identity\Http\Controllers\Auth\PasskeyController;
use Modules\Identity\Http\Controllers\Auth\SessionController;
use Modules\Wallet\Http\Controllers\Admin\AdminDepositController;
use Modules\Wallet\Http\Controllers\Admin\AdminWalletController;
use Modules\Wallet\Http\Controllers\Admin\WalletAdjustmentController;
use Modules\Wallet\Http\Controllers\DepositController;
use Modules\Wallet\Http\Controllers\WalletController;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::middleware(['tenant', 'throttle:api'])->group(function (): void {
        Route::get('/catalog', [CatalogController::class, 'index'])->name('api.v1.catalog.index');
        Route::get('/catalog/categories', [CatalogController::class, 'categories'])->name('api.v1.catalog.categories');
        Route::get('/catalog/{slug}', [CatalogController::class, 'show'])->name('api.v1.catalog.show');
    });

    Route::middleware(['tenant', 'throttle:api'])->get('/platform', fn () => response()->json(['data' => [
        'name' => config('app.name'),
        'version' => config('app.version'),
        'api_version' => 'v1',
        'identity_phase' => 'phase-2',
        'wallet_phase' => 'phase-3',
        'commerce_phase' => 'phase-4',
    ]]))->name('api.v1.platform');

    Route::prefix('auth')->middleware(['tenant', 'auth:sanctum', 'account.active', 'auth.session', 'throttle:api'])->group(function (): void {
        Route::get('/me', CurrentUserController::class)->name('api.v1.auth.me');

        Route::get('/sessions', [SessionController::class, 'index'])->name('api.v1.auth.sessions.index');
        Route::delete('/sessions/others', [SessionController::class, 'destroyOthers'])->middleware('throttle:sensitive')->name('api.v1.auth.sessions.others');
        Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->middleware('throttle:sensitive')->name('api.v1.auth.sessions.destroy');

        Route::get('/passkeys', [PasskeyController::class, 'index'])->name('api.v1.auth.passkeys.index');
        Route::delete('/passkeys/{passkey}', [PasskeyController::class, 'destroy'])->middleware(['password.confirm', 'throttle:sensitive'])->name('api.v1.auth.passkeys.destroy');

        Route::get('/devices', [DeviceController::class, 'index'])->name('api.v1.auth.devices.index');
        Route::post('/devices/{device}/trust', [DeviceController::class, 'trust'])->middleware(['password.confirm', 'throttle:sensitive'])->name('api.v1.auth.devices.trust');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->middleware('throttle:sensitive')->name('api.v1.auth.devices.destroy');

        Route::get('/tokens', [ApiTokenController::class, 'index'])->middleware('permission:api.tokens.manage')->name('api.v1.auth.tokens.index');
        Route::post('/tokens', [ApiTokenController::class, 'store'])->middleware(['permission:api.tokens.manage', 'throttle:sensitive'])->name('api.v1.auth.tokens.store');
        Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy'])->middleware(['permission:api.tokens.manage', 'throttle:sensitive'])->name('api.v1.auth.tokens.destroy');
    });

    Route::middleware(['tenant', 'auth:sanctum', 'account.active', 'auth.session', 'verified', 'permission:wallet.view', 'throttle:api'])->group(function (): void {
        Route::get('/wallets', [WalletController::class, 'index'])->name('api.v1.wallets.index');
        Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->name('api.v1.wallets.show');
        Route::get('/wallets/{wallet}/transactions', [WalletController::class, 'transactions'])->name('api.v1.wallets.transactions');

        Route::get('/deposits', [DepositController::class, 'index'])->name('api.v1.deposits.index');
        Route::post('/deposits', [DepositController::class, 'store'])->middleware(['permission:wallet.deposit.create', 'throttle:sensitive'])->name('api.v1.deposits.store');
        Route::get('/deposits/{deposit}', [DepositController::class, 'show'])->name('api.v1.deposits.show');
        Route::post('/deposits/{deposit}/cancel', [DepositController::class, 'cancel'])->middleware('throttle:sensitive')->name('api.v1.deposits.cancel');
    });

    Route::middleware(['tenant', 'auth:sanctum', 'account.active', 'auth.session', 'verified', 'throttle:api'])->group(function (): void {
        Route::get('/cart', [CartController::class, 'show'])->middleware('permission:commerce.cart.manage')->name('api.v1.cart.show');
        Route::post('/cart/items', [CartController::class, 'store'])->middleware(['permission:commerce.cart.manage', 'throttle:sensitive'])->name('api.v1.cart.items.store');
        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->middleware(['permission:commerce.cart.manage', 'throttle:sensitive'])->name('api.v1.cart.items.update');
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->middleware(['permission:commerce.cart.manage', 'throttle:sensitive'])->name('api.v1.cart.items.destroy');
        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(['permission:commerce.checkout', 'throttle:sensitive'])->name('api.v1.checkout.store');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:commerce.orders.view')->name('api.v1.orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:commerce.orders.view')->name('api.v1.orders.show');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware(['permission:commerce.orders.view', 'throttle:sensitive'])->name('api.v1.orders.cancel');
    });

    Route::prefix('admin')->middleware([
        'tenant', 'auth:sanctum', 'account.active', 'auth.session', 'verified', 'admin.2fa',
        'permission:identity.admin.access', 'throttle:api',
    ])->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index'])->name('api.v1.admin.users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('api.v1.admin.users.show');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->middleware('permission:identity.users.manage')->name('api.v1.admin.users.status');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:identity.roles.manage')->name('api.v1.admin.roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->middleware(['permission:identity.roles.manage', 'throttle:sensitive'])->name('api.v1.admin.roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware(['permission:identity.roles.manage', 'throttle:sensitive'])->name('api.v1.admin.roles.update');
        Route::post('/users/{user}/roles/{role}', [RoleController::class, 'assign'])->middleware(['permission:identity.roles.manage', 'throttle:sensitive'])->name('api.v1.admin.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'revoke'])->middleware(['permission:identity.roles.manage', 'throttle:sensitive'])->name('api.v1.admin.roles.revoke');

        Route::get('/wallets', [AdminWalletController::class, 'index'])->middleware('permission:wallet.admin.access')->name('api.v1.admin.wallets.index');
        Route::get('/wallets/{wallet}', [AdminWalletController::class, 'show'])->middleware('permission:wallet.admin.access')->name('api.v1.admin.wallets.show');
        Route::get('/ledger/transactions', [AdminWalletController::class, 'ledger'])->middleware('permission:wallet.ledger.view')->name('api.v1.admin.ledger.index');

        Route::get('/deposits', [AdminDepositController::class, 'index'])->middleware('permission:wallet.deposits.review')->name('api.v1.admin.deposits.index');
        Route::get('/deposits/{deposit}', [AdminDepositController::class, 'show'])->middleware('permission:wallet.deposits.review')->name('api.v1.admin.deposits.show');
        Route::post('/deposits/{deposit}/approve', [AdminDepositController::class, 'approve'])->middleware(['permission:wallet.deposits.review', 'throttle:sensitive'])->name('api.v1.admin.deposits.approve');
        Route::post('/deposits/{deposit}/reject', [AdminDepositController::class, 'reject'])->middleware(['permission:wallet.deposits.review', 'throttle:sensitive'])->name('api.v1.admin.deposits.reject');

        Route::get('/wallet-adjustments', [WalletAdjustmentController::class, 'index'])->middleware('permission:wallet.adjustments.request')->name('api.v1.admin.wallet-adjustments.index');
        Route::post('/wallet-adjustments', [WalletAdjustmentController::class, 'store'])->middleware(['permission:wallet.adjustments.request', 'throttle:sensitive'])->name('api.v1.admin.wallet-adjustments.store');
        Route::post('/wallet-adjustments/{adjustment}/approve', [WalletAdjustmentController::class, 'approve'])->middleware(['permission:wallet.adjustments.approve', 'throttle:sensitive'])->name('api.v1.admin.wallet-adjustments.approve');
        Route::post('/wallet-adjustments/{adjustment}/reject', [WalletAdjustmentController::class, 'reject'])->middleware(['permission:wallet.adjustments.approve', 'throttle:sensitive'])->name('api.v1.admin.wallet-adjustments.reject');


        Route::get('/commerce/categories', [AdminCatalogController::class, 'categories'])->middleware('permission:commerce.catalog.manage')->name('api.v1.admin.commerce.categories');
        Route::post('/commerce/categories', [AdminCatalogController::class, 'storeCategory'])->middleware(['permission:commerce.catalog.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.categories.store');
        Route::patch('/commerce/categories/{category}', [AdminCatalogController::class, 'updateCategory'])->middleware(['permission:commerce.catalog.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.categories.update');
        Route::get('/commerce/items', [AdminCatalogController::class, 'items'])->middleware('permission:commerce.admin.access')->name('api.v1.admin.commerce.items');
        Route::post('/commerce/items', [AdminCatalogController::class, 'storeItem'])->middleware(['permission:commerce.catalog.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.items.store');
        Route::patch('/commerce/items/{item}', [AdminCatalogController::class, 'updateItem'])->middleware(['permission:commerce.catalog.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.items.update');
        Route::get('/commerce/pricing', [AdminPricingController::class, 'index'])->middleware('permission:commerce.pricing.manage')->name('api.v1.admin.commerce.pricing');
        Route::post('/commerce/pricing', [AdminPricingController::class, 'upsert'])->middleware(['permission:commerce.pricing.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.pricing.upsert');
        Route::get('/commerce/inventory', [AdminInventoryController::class, 'index'])->middleware('permission:commerce.inventory.manage')->name('api.v1.admin.commerce.inventory');
        Route::post('/commerce/inventory', [AdminInventoryController::class, 'upsert'])->middleware(['permission:commerce.inventory.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.inventory.upsert');
        Route::get('/commerce/orders', [AdminOrderController::class, 'index'])->middleware('permission:commerce.orders.manage')->name('api.v1.admin.commerce.orders');
        Route::get('/commerce/orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:commerce.orders.manage')->name('api.v1.admin.commerce.orders.show');
        Route::post('/commerce/orders/{order}/transition', [AdminOrderController::class, 'transition'])->middleware(['permission:commerce.orders.manage', 'throttle:sensitive'])->name('api.v1.admin.commerce.orders.transition');
    });
});
