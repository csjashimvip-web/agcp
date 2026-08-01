<?php

use App\Modules\Catalog\Http\Controllers\AdminProductController;
use App\Modules\Catalog\Http\Controllers\StorefrontController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Gateway\Http\Controllers\AdminResellerApiClientController;
use App\Modules\Gateway\Http\Controllers\ResellerApiController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Orders\Http\Controllers\AdminOrderActionController;
use App\Modules\Payments\Http\Controllers\PaymentWebhookController;
use App\Modules\Platform\Http\Controllers\AdminOverviewController;
use App\Modules\Platform\Http\Controllers\AdminResourceController;
use App\Modules\Platform\Http\Controllers\CustomerAccountController;
use App\Modules\Platform\Http\Controllers\PlatformController;
use App\Modules\Reliability\Http\Controllers\AdminOperationsHealthController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierOperationsController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierRoutingController;
use App\Modules\Supplier\Http\Controllers\AdminSupplierSyncController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use App\Modules\Wallet\Http\Controllers\AdminDepositController;
use App\Modules\Analytics\Http\Controllers\AdminAnalyticsController;
use App\Modules\Marketplace\Http\Controllers\AdminMarketplaceController;
use App\Modules\Pricing\Http\Controllers\AdminPricingController;
use App\Modules\Fraud\Http\Controllers\AdminFraudController;
use App\Modules\Marketplace\Http\Controllers\AdminCommissionController;
use App\Modules\Pricing\Http\Controllers\AdminPricingRuleController;
use App\Modules\Reliability\Http\Controllers\AdminAuditExplorerController;
use App\Modules\Support\Http\Controllers\AdminSupportController;
use App\Modules\Support\Http\Controllers\CustomerSupportController;
use App\Modules\Wallet\Http\Controllers\AdminPayoutController;
use App\Modules\Wallet\Http\Controllers\CustomerPayoutController;
use App\Modules\Automation\Http\Controllers\AdminAutomationController;
use App\Modules\Gateway\Http\Controllers\DeveloperPortalController;
use App\Modules\Licensing\Http\Controllers\AdminLicenseController;
use App\Modules\Notifications\Http\Controllers\AdminNotificationChannelController;
use App\Modules\Plugins\Http\Controllers\AdminPluginController;
use App\Modules\SaaS\Http\Controllers\AdminSaaSController;
use App\Modules\Platform\Http\Controllers\AdminDataOperationsController;
use App\Modules\Platform\Http\Controllers\AdminDeploymentController;
use App\Modules\Platform\Http\Controllers\PrivacyController;
use App\Modules\Reliability\Http\Controllers\AdminReliabilityController;
use App\Modules\Reliability\Http\Controllers\PublicReadinessController;
use App\Modules\Gateway\Http\Controllers\AdminWebhookController;
use App\Modules\Mobile\Http\Controllers\MobileBffController;
use App\Modules\Notifications\Http\Controllers\AdminEmailProviderController;
use App\Modules\Reliability\Http\Controllers\AdminReleaseCandidateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/platform', [PlatformController::class, 'show']);

    Route::get(
        '/storefront/{tenantSlug}/catalog',
        [StorefrontController::class, 'catalog']
    );

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

        Route::prefix('customer')->group(function (): void {
            Route::get('/wallets', [CustomerAccountController::class, 'wallets']);
            Route::get('/orders', [CustomerAccountController::class, 'orders']);
            Route::get('/deposits', [CustomerAccountController::class, 'deposits']);
            Route::post(
                '/deposits',
                [CustomerAccountController::class, 'requestDeposit']
            );
        });

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

            Route::get(
                '/reseller-api-clients',
                [AdminResellerApiClientController::class, 'index']
            )->middleware('agcp.permission:gateway.manage');

            Route::post(
                '/reseller-api-clients',
                [AdminResellerApiClientController::class, 'store']
            )->middleware('agcp.permission:gateway.manage');

            Route::post(
                '/reseller-api-clients/{clientId}/revoke',
                [AdminResellerApiClientController::class, 'revoke']
            )
                ->whereNumber('clientId')
                ->middleware('agcp.permission:gateway.manage');

            Route::get(
                '/pricing',
                [AdminPricingController::class, 'index']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/pricing/tiers',
                [AdminPricingController::class, 'createTier']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/pricing/tiers/assign',
                [AdminPricingController::class, 'assignTier']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/pricing/tiers/{tierId}/prices',
                [AdminPricingController::class, 'setTierPrice']
            )
                ->whereNumber('tierId')
                ->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/pricing/coupons',
                [AdminPricingController::class, 'createCoupon']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/pricing/tax-rules',
                [AdminPricingController::class, 'createTaxRule']
            )->middleware('agcp.permission:catalog.manage');

            Route::get(
                '/marketplace',
                [AdminMarketplaceController::class, 'index']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/marketplace/sellers',
                [AdminMarketplaceController::class, 'createSeller']
            )->middleware('agcp.permission:catalog.manage');

            Route::post(
                '/marketplace/listings',
                [AdminMarketplaceController::class, 'createListing']
            )->middleware('agcp.permission:catalog.manage');

            Route::get(
                '/analytics',
                AdminAnalyticsController::class
            )->middleware('agcp.permission:platform.architecture.view');
        });
    });
});

Route::prefix('reseller/v1')
    ->middleware([
        'agcp.reseller',
        'throttle:reseller-api',
        'agcp.api_log',
    ])
    ->group(function (): void {
        Route::get('/services', [ResellerApiController::class, 'services'])
            ->middleware('agcp.api_ability:services:read');

        Route::get('/balance', [ResellerApiController::class, 'balance'])
            ->middleware('agcp.api_ability:wallet:read');

        Route::post('/orders', [ResellerApiController::class, 'placeOrder'])
            ->middleware('agcp.api_ability:orders:create');

        Route::get('/orders', [ResellerApiController::class, 'orders'])
            ->middleware('agcp.api_ability:orders:read');

        Route::get('/orders/{orderId}', [ResellerApiController::class, 'order'])
            ->whereNumber('orderId')
            ->middleware('agcp.api_ability:orders:read');
    });

// AGCP FINANCIAL RISK SUPPORT V1
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'agcp.tenant'])
    ->group(function (): void {
        Route::prefix('customer')->group(function (): void {
            Route::get('/payouts', [CustomerPayoutController::class, 'index']);
            Route::post('/payouts', [CustomerPayoutController::class, 'store']);

            Route::get('/support', [CustomerSupportController::class, 'index']);
            Route::post('/support', [CustomerSupportController::class, 'store']);
            Route::post(
                '/support/{ticketId}/messages',
                [CustomerSupportController::class, 'reply']
            )->whereNumber('ticketId');
        });

        Route::prefix('admin')->group(function (): void {
            Route::get(
                '/commissions',
                [AdminCommissionController::class, 'index']
            )->middleware('agcp.permission:marketplace.manage');

            Route::post(
                '/commissions/sellers/{sellerId}/settle',
                [AdminCommissionController::class, 'settle']
            )
                ->whereNumber('sellerId')
                ->middleware('agcp.permission:payouts.manage');

            Route::get(
                '/payouts',
                [AdminPayoutController::class, 'index']
            )->middleware('agcp.permission:payouts.manage');

            Route::post(
                '/payouts/{payoutId}/approve',
                [AdminPayoutController::class, 'approve']
            )
                ->whereNumber('payoutId')
                ->middleware('agcp.permission:payouts.manage');

            Route::post(
                '/payouts/{payoutId}/reject',
                [AdminPayoutController::class, 'reject']
            )
                ->whereNumber('payoutId')
                ->middleware('agcp.permission:payouts.manage');

            Route::post(
                '/payouts/{payoutId}/paid',
                [AdminPayoutController::class, 'paid']
            )
                ->whereNumber('payoutId')
                ->middleware('agcp.permission:payouts.manage');

            Route::get(
                '/pricing-rules',
                [AdminPricingRuleController::class, 'index']
            )->middleware('agcp.permission:pricing.manage');

            Route::post(
                '/pricing-rules',
                [AdminPricingRuleController::class, 'store']
            )->middleware('agcp.permission:pricing.manage');

            Route::get(
                '/fraud',
                [AdminFraudController::class, 'index']
            )->middleware('agcp.permission:fraud.manage');

            Route::post(
                '/fraud/rules',
                [AdminFraudController::class, 'storeRule']
            )->middleware('agcp.permission:fraud.manage');

            Route::get(
                '/support',
                [AdminSupportController::class, 'index']
            )->middleware('agcp.permission:support.manage');

            Route::post(
                '/support/{ticketId}/reply',
                [AdminSupportController::class, 'reply']
            )
                ->whereNumber('ticketId')
                ->middleware('agcp.permission:support.manage');

            Route::patch(
                '/support/{ticketId}',
                [AdminSupportController::class, 'update']
            )
                ->whereNumber('ticketId')
                ->middleware('agcp.permission:support.manage');

            Route::get(
                '/audit',
                AdminAuditExplorerController::class
            )->middleware('agcp.permission:reliability.audit.view');
        });
    });

// AGCP SAAS LICENSING PLUGINS AUTOMATION V1
Route::get(
    '/developer/openapi.json',
    [DeveloperPortalController::class, 'openApi']
);

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'agcp.tenant'])
    ->group(function (): void {
        Route::prefix('admin')->group(function (): void {
            Route::get(
                '/saas',
                [AdminSaaSController::class, 'index']
            )->middleware('agcp.permission:saas.manage');

            Route::post(
                '/saas/plans',
                [AdminSaaSController::class, 'createPlan']
            )->middleware('agcp.permission:saas.manage');

            Route::post(
                '/saas/subscription',
                [AdminSaaSController::class, 'subscribe']
            )->middleware('agcp.permission:saas.manage');

            Route::get(
                '/licensing',
                [AdminLicenseController::class, 'index']
            )->middleware('agcp.permission:licensing.manage');

            Route::post(
                '/licensing/licenses',
                [AdminLicenseController::class, 'issue']
            )->middleware('agcp.permission:licensing.manage');

            Route::post(
                '/licensing/licenses/{licenseId}/revoke',
                [AdminLicenseController::class, 'revoke']
            )
                ->whereNumber('licenseId')
                ->middleware('agcp.permission:licensing.manage');

            Route::get(
                '/plugins',
                [AdminPluginController::class, 'index']
            )->middleware('agcp.permission:plugins.manage');

            Route::post(
                '/plugins/manifests',
                [AdminPluginController::class, 'registerManifest']
            )->middleware('agcp.permission:plugins.manage');

            Route::post(
                '/plugins/{manifestId}/enable',
                [AdminPluginController::class, 'enable']
            )
                ->whereNumber('manifestId')
                ->middleware('agcp.permission:plugins.manage');

            Route::post(
                '/plugins/{manifestId}/disable',
                [AdminPluginController::class, 'disable']
            )
                ->whereNumber('manifestId')
                ->middleware('agcp.permission:plugins.manage');

            Route::get(
                '/automation',
                [AdminAutomationController::class, 'index']
            )->middleware('agcp.permission:automation.manage');

            Route::post(
                '/automation/rules',
                [AdminAutomationController::class, 'store']
            )->middleware('agcp.permission:automation.manage');

            Route::post(
                '/automation/simulate',
                [AdminAutomationController::class, 'simulate']
            )->middleware('agcp.permission:automation.manage');

            Route::get(
                '/notification-channels',
                [AdminNotificationChannelController::class, 'index']
            )->middleware('agcp.permission:notifications.channels.manage');

            Route::post(
                '/notification-channels',
                [AdminNotificationChannelController::class, 'store']
            )->middleware('agcp.permission:notifications.channels.manage');
        });
    });

// AGCP ENTERPRISE HARDENING V1
Route::get(
    '/v1/platform/readiness',
    PublicReadinessController::class
);

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'agcp.tenant'])
    ->group(function (): void {
        Route::prefix('customer')->group(function (): void {
            Route::get(
                '/privacy',
                [PrivacyController::class, 'customerIndex']
            );

            Route::post(
                '/privacy',
                [PrivacyController::class, 'customerStore']
            );
        });

        Route::prefix('admin')->group(function (): void {
            Route::get(
                '/reliability',
                [AdminReliabilityController::class, 'index']
            )->middleware('agcp.permission:reliability.manage');

            Route::post(
                '/reliability/slos',
                [AdminReliabilityController::class, 'createSlo']
            )->middleware('agcp.permission:reliability.manage');

            Route::get(
                '/privacy',
                [PrivacyController::class, 'adminIndex']
            )->middleware('agcp.permission:privacy.manage');

            Route::patch(
                '/privacy/{requestId}',
                [PrivacyController::class, 'adminReview']
            )
                ->whereNumber('requestId')
                ->middleware('agcp.permission:privacy.manage');

            Route::get(
                '/data-ops',
                [AdminDataOperationsController::class, 'index']
            )->middleware('agcp.permission:data.manage');

            Route::post(
                '/data-ops/exports',
                [AdminDataOperationsController::class, 'export']
            )->middleware('agcp.permission:data.manage');

            Route::post(
                '/data-ops/imports/catalog',
                [AdminDataOperationsController::class, 'importCatalog']
            )->middleware('agcp.permission:data.manage');

            Route::post(
                '/data-ops/retention',
                [AdminDataOperationsController::class, 'saveRetention']
            )->middleware('agcp.permission:data.manage');

            Route::get(
                '/deployments',
                [AdminDeploymentController::class, 'index']
            )->middleware('agcp.permission:deployment.manage');

            Route::post(
                '/deployments',
                [AdminDeploymentController::class, 'record']
            )->middleware('agcp.permission:deployment.manage');
        });
    });

// AGCP MOBILE WEBHOOK EMAIL RC V1
Route::prefix('mobile/v1')
    ->middleware(['auth:sanctum', 'agcp.tenant'])
    ->group(function (): void {
        Route::get(
            '/bootstrap',
            [MobileBffController::class, 'bootstrap']
        );

        Route::get(
            '/catalog',
            [MobileBffController::class, 'catalog']
        );

        Route::get(
            '/orders',
            [MobileBffController::class, 'orders']
        );

        Route::get(
            '/notifications',
            [MobileBffController::class, 'notifications']
        );

        Route::post(
            '/devices',
            [MobileBffController::class, 'registerDevice']
        );
    });

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'agcp.tenant'])
    ->group(function (): void {
        Route::prefix('admin')->group(function (): void {
            Route::get(
                '/webhooks',
                [AdminWebhookController::class, 'index']
            )->middleware('agcp.permission:webhooks.manage');

            Route::post(
                '/webhooks',
                [AdminWebhookController::class, 'store']
            )->middleware('agcp.permission:webhooks.manage');

            Route::post(
                '/webhooks/{subscriptionId}/toggle-delivery',
                [AdminWebhookController::class, 'toggle']
            )
                ->whereNumber('subscriptionId')
                ->middleware('agcp.permission:webhooks.manage');

            Route::get(
                '/email-providers',
                [AdminEmailProviderController::class, 'index']
            )->middleware('agcp.permission:email.manage');

            Route::post(
                '/email-providers',
                [AdminEmailProviderController::class, 'store']
            )->middleware('agcp.permission:email.manage');

            Route::get(
                '/release-candidate',
                [AdminReleaseCandidateController::class, 'index']
            )->middleware('agcp.permission:release.audit');

            Route::post(
                '/release-candidate/performance',
                [AdminReleaseCandidateController::class, 'performance']
            )->middleware('agcp.permission:performance.manage');

            Route::post(
                '/release-candidate/audit',
                [AdminReleaseCandidateController::class, 'audit']
            )->middleware('agcp.permission:release.audit');
        });
    });
