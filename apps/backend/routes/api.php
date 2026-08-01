<?php

use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/platform', [PlatformController::class, 'show']);

    // Authentication/tenant authorization middleware will be attached
    // when the Identity workstream is activated.
    Route::post('/checkout', CheckoutController::class);
});