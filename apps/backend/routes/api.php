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
use Modules\Suppliers\Http\Controllers\Admin\AdminSupplierController;
use Modules\Suppliers\Http\Controllers\Admin\AdminSupplierOrderController;
use Modules\Suppliers\Http\Controllers\Admin\AdminSupplierRoutingProfileController;
use Modules\Suppliers\Http\Controllers\Admin\AdminSupplierServiceController;
use Modules\Rules\Http\Controllers\PricingQuoteController;
use Modules\Rules\Http\Controllers\Admin\AdminRuleController;
use Modules\Fraud\Http\Controllers\FraudAssessmentController;
use Modules\Fraud\Http\Controllers\Admin\AdminFraudAssessmentController;
use Modules\SaaS\Http\Controllers\TenantConfigurationController;
use Modules\SaaS\Http\Controllers\Admin\AdminSaasController;
use Modules\SaaS\Http\Controllers\Admin\AdminTenantProfileController;
use Modules\SaaS\Http\Controllers\Admin\AdminTenantDomainController;
use Modules\Plugins\Http\Controllers\Admin\AdminPluginController;
use Modules\Analytics\Http\Controllers\Admin\AdminAnalyticsController;
use Modules\Payments\Http\Controllers\PaymentController;
use Modules\Payments\Http\Controllers\PaymentWebhookController;
use Modules\Payments\Http\Controllers\Admin\AdminPaymentController;
use Modules\Notifications\Http\Controllers\NotificationController;
use Modules\Notifications\Http\Controllers\Admin\AdminNotificationController;
use Modules\Integrations\Http\Controllers\Admin\AdminWebhookController;
use Modules\Support\Http\Controllers\SupportTicketController;
use Modules\Support\Http\Controllers\Admin\AdminSupportController;
use Modules\Observability\Http\Controllers\Admin\AdminOperationsController;
use Modules\Reporting\Http\Controllers\InvoiceController;
use Modules\Reporting\Http\Controllers\CustomerTaxProfileController;
use Modules\Reporting\Http\Controllers\Admin\AdminReportingController;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');
    Route::post('/payments/webhooks/{provider}/{accountCode?}', PaymentWebhookController::class)->middleware(['tenant', 'throttle:payment-webhook'])->name('api.v1.payments.webhooks');

    Route::middleware(['tenant', 'throttle:api'])->group(function (): void {
        Route::get('/catalog', [CatalogController::class, 'index'])->name('api.v1.catalog.index');
        Route::get('/catalog/categories', [CatalogController::class, 'categories'])->name('api.v1.catalog.categories');
        Route::get('/catalog/{slug}', [CatalogController::class, 'show'])->name('api.v1.catalog.show');
        Route::get('/tenant/configuration', TenantConfigurationController::class)->name('api.v1.tenant.configuration');
    });

    Route::middleware(['tenant', 'throttle:api'])->get('/platform', fn () => response()->json(['data' => [
        'name' => config('app.name'),
        'version' => config('app.version'),
        'api_version' => 'v1',
        'identity_phase' => 'phase-2',
        'wallet_phase' => 'phase-3',
        'commerce_phase' => 'phase-4',
        'supplier_phase' => 'phase-5',
        'rules_fraud_pricing_phase' => 'phase-6',
        'saas_plugins_phase' => 'phase-7',
        'ai_analytics_phase' => 'phase-8',
        'payments_reconciliation_phase' => 'phase-9',
        'engagement_operations_phase' => 'phase-10',
        'reporting_invoicing_phase' => 'phase-11',
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

        Route::get('/payments/providers', [PaymentController::class, 'providers'])->middleware('permission:payments.view')->name('api.v1.payments.providers');
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view')->name('api.v1.payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->middleware(['permission:payments.create', 'throttle:sensitive'])->name('api.v1.payments.store');
        Route::get('/payments/{paymentIntent}', [PaymentController::class, 'show'])->middleware('permission:payments.view')->name('api.v1.payments.show');
        Route::post('/payments/{paymentIntent}/cancel', [PaymentController::class, 'cancel'])->middleware(['permission:payments.create', 'throttle:sensitive'])->name('api.v1.payments.cancel');
        Route::post('/payments/{paymentIntent}/sandbox-complete', [PaymentController::class, 'simulate'])->middleware(['permission:payments.create', 'throttle:sensitive'])->name('api.v1.payments.sandbox-complete');
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

        Route::get('/pricing/quote', PricingQuoteController::class)->middleware('permission:commerce.catalog.view')->name('api.v1.pricing.quote');
        Route::get('/risk/assessments', [FraudAssessmentController::class, 'index'])->middleware('permission:commerce.orders.view')->name('api.v1.risk.assessments.index');

        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:notifications.view')->name('api.v1.notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:notifications.view')->name('api.v1.notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware(['permission:notifications.view', 'throttle:sensitive'])->name('api.v1.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware(['permission:notifications.view', 'throttle:sensitive'])->name('api.v1.notifications.read');
        Route::get('/notification-preferences', [NotificationController::class, 'preferences'])->middleware('permission:notifications.preferences.manage')->name('api.v1.notification-preferences.index');
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences'])->middleware(['permission:notifications.preferences.manage', 'throttle:sensitive'])->name('api.v1.notification-preferences.update');

        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->middleware('permission:support.tickets.create')->name('api.v1.support.tickets.index');
        Route::post('/support/tickets', [SupportTicketController::class, 'store'])->middleware(['permission:support.tickets.create', 'throttle:sensitive'])->name('api.v1.support.tickets.store');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->middleware('permission:support.tickets.create')->name('api.v1.support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware(['permission:support.tickets.create', 'throttle:sensitive'])->name('api.v1.support.tickets.reply');

        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:reporting.invoices.view')->name('api.v1.invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:reporting.invoices.view')->name('api.v1.invoices.show');
        Route::get('/invoices/{invoice}/document', [InvoiceController::class, 'document'])->middleware('permission:reporting.invoices.view')->name('api.v1.invoices.document');
        Route::get('/tax-profile', [CustomerTaxProfileController::class, 'show'])->middleware('permission:reporting.tax-profile.manage')->name('api.v1.tax-profile.show');
        Route::put('/tax-profile', [CustomerTaxProfileController::class, 'update'])->middleware(['permission:reporting.tax-profile.manage', 'throttle:sensitive'])->name('api.v1.tax-profile.update');
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


        Route::get('/supplier-routing-profile', [AdminSupplierRoutingProfileController::class, 'show'])->middleware('permission:supplier.admin.access')->name('api.v1.admin.supplier-routing-profile.show');
        Route::put('/supplier-routing-profile', [AdminSupplierRoutingProfileController::class, 'update'])->middleware(['permission:supplier.accounts.manage', 'throttle:sensitive'])->name('api.v1.admin.supplier-routing-profile.update');
        Route::get('/suppliers/providers', [AdminSupplierController::class, 'providers'])->middleware('permission:supplier.admin.access')->name('api.v1.admin.suppliers.providers');
        Route::get('/suppliers', [AdminSupplierController::class, 'index'])->middleware('permission:supplier.admin.access')->name('api.v1.admin.suppliers.index');
        Route::post('/suppliers', [AdminSupplierController::class, 'store'])->middleware(['permission:supplier.accounts.manage', 'throttle:sensitive'])->name('api.v1.admin.suppliers.store');
        Route::patch('/suppliers/{supplier}', [AdminSupplierController::class, 'update'])->middleware(['permission:supplier.accounts.manage', 'throttle:sensitive'])->name('api.v1.admin.suppliers.update');
        Route::post('/suppliers/{supplier}/health-check', [AdminSupplierController::class, 'check'])->middleware(['permission:supplier.health.manage', 'throttle:sensitive'])->name('api.v1.admin.suppliers.health-check');
        Route::post('/suppliers/{supplier}/services', [AdminSupplierServiceController::class, 'store'])->middleware(['permission:supplier.services.manage', 'throttle:sensitive'])->name('api.v1.admin.suppliers.services.store');
        Route::patch('/supplier-services/{service}', [AdminSupplierServiceController::class, 'update'])->middleware(['permission:supplier.services.manage', 'throttle:sensitive'])->name('api.v1.admin.supplier-services.update');
        Route::get('/supplier-orders', [AdminSupplierOrderController::class, 'index'])->middleware('permission:supplier.orders.manage')->name('api.v1.admin.supplier-orders.index');
        Route::get('/supplier-orders/{supplierOrder}', [AdminSupplierOrderController::class, 'show'])->middleware('permission:supplier.orders.manage')->name('api.v1.admin.supplier-orders.show');
        Route::post('/supplier-orders/{supplierOrder}/retry', [AdminSupplierOrderController::class, 'retry'])->middleware(['permission:supplier.orders.manage', 'throttle:sensitive'])->name('api.v1.admin.supplier-orders.retry');


        Route::get('/rules', [AdminRuleController::class, 'index'])->middleware('permission:rules.admin.access')->name('api.v1.admin.rules.index');
        Route::post('/rules', [AdminRuleController::class, 'store'])->middleware(['permission:rules.manage', 'throttle:sensitive'])->name('api.v1.admin.rules.store');
        Route::patch('/rules/{rule}', [AdminRuleController::class, 'update'])->middleware(['permission:rules.manage', 'throttle:sensitive'])->name('api.v1.admin.rules.update');
        Route::post('/rules/{rule}/publish', [AdminRuleController::class, 'publish'])->middleware(['permission:rules.manage', 'throttle:sensitive'])->name('api.v1.admin.rules.publish');
        Route::post('/rules/{rule}/pause', [AdminRuleController::class, 'pause'])->middleware(['permission:rules.manage', 'throttle:sensitive'])->name('api.v1.admin.rules.pause');
        Route::get('/fraud/assessments', [AdminFraudAssessmentController::class, 'index'])->middleware('permission:fraud.admin.access')->name('api.v1.admin.fraud.assessments.index');
        Route::post('/fraud/assessments/{assessment}/approve', [AdminFraudAssessmentController::class, 'approve'])->middleware(['permission:fraud.assessments.review', 'throttle:sensitive'])->name('api.v1.admin.fraud.assessments.approve');
        Route::post('/fraud/assessments/{assessment}/reject', [AdminFraudAssessmentController::class, 'reject'])->middleware(['permission:fraud.assessments.review', 'throttle:sensitive'])->name('api.v1.admin.fraud.assessments.reject');


        Route::get('/saas', [AdminSaasController::class, 'index'])->middleware('permission:saas.admin.access')->name('api.v1.admin.saas.index');
        Route::post('/saas/plans', [AdminSaasController::class, 'storePlan'])->middleware(['permission:saas.plans.manage', 'throttle:sensitive'])->name('api.v1.admin.saas.plans.store');
        Route::post('/saas/tenants', [AdminSaasController::class, 'storeTenant'])->middleware(['permission:saas.platform.manage', 'throttle:sensitive'])->name('api.v1.admin.saas.tenants.store');
        Route::put('/saas/tenants/{tenant}/subscription', [AdminSaasController::class, 'updateSubscription'])->middleware(['permission:saas.subscriptions.manage', 'throttle:sensitive'])->name('api.v1.admin.saas.subscriptions.update');
        Route::get('/tenant-profile', [AdminTenantProfileController::class, 'show'])->middleware('permission:saas.tenant.manage')->name('api.v1.admin.tenant-profile.show');
        Route::patch('/tenant-profile', [AdminTenantProfileController::class, 'update'])->middleware(['permission:saas.tenant.manage', 'throttle:sensitive'])->name('api.v1.admin.tenant-profile.update');
        Route::get('/tenant-domains', [AdminTenantDomainController::class, 'index'])->middleware('permission:saas.tenant.manage')->name('api.v1.admin.tenant-domains.index');
        Route::post('/tenant-domains', [AdminTenantDomainController::class, 'store'])->middleware(['permission:saas.tenant.manage', 'feature:custom_domains', 'throttle:sensitive'])->name('api.v1.admin.tenant-domains.store');
        Route::post('/tenant-domains/{domain}/verify', [AdminTenantDomainController::class, 'verify'])->middleware(['permission:saas.tenant.manage', 'feature:custom_domains', 'throttle:sensitive'])->name('api.v1.admin.tenant-domains.verify');
        Route::post('/tenant-domains/{domain}/primary', [AdminTenantDomainController::class, 'primary'])->middleware(['permission:saas.tenant.manage', 'feature:custom_domains', 'throttle:sensitive'])->name('api.v1.admin.tenant-domains.primary');
        Route::get('/plugins', [AdminPluginController::class, 'index'])->middleware('permission:plugins.marketplace.view')->name('api.v1.admin.plugins.index');
        Route::post('/plugins/{plugin}/install', [AdminPluginController::class, 'install'])->middleware(['permission:plugins.manage', 'feature:plugins.marketplace', 'throttle:sensitive'])->name('api.v1.admin.plugins.install');
        Route::patch('/plugin-installations/{installation}', [AdminPluginController::class, 'configure'])->middleware(['permission:plugins.manage', 'feature:plugins.marketplace', 'throttle:sensitive'])->name('api.v1.admin.plugins.configure');
        Route::post('/plugin-installations/{installation}/enable', [AdminPluginController::class, 'enable'])->middleware(['permission:plugins.manage', 'feature:plugins.marketplace', 'throttle:sensitive'])->name('api.v1.admin.plugins.enable');
        Route::post('/plugin-installations/{installation}/disable', [AdminPluginController::class, 'disable'])->middleware(['permission:plugins.manage', 'throttle:sensitive'])->name('api.v1.admin.plugins.disable');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->middleware('permission:payments.admin.access')->name('api.v1.admin.payments.index');
        Route::get('/payments/provider-types', [AdminPaymentController::class, 'providerTypes'])->middleware('permission:payments.providers.manage')->name('api.v1.admin.payments.provider-types');
        Route::post('/payments/providers', [AdminPaymentController::class, 'storeProvider'])->middleware(['permission:payments.providers.manage', 'throttle:sensitive'])->name('api.v1.admin.payments.providers.store');
        Route::patch('/payments/providers/{providerAccount}', [AdminPaymentController::class, 'updateProvider'])->middleware(['permission:payments.providers.manage', 'throttle:sensitive'])->name('api.v1.admin.payments.providers.update');
        Route::post('/payments/providers/{providerAccount}/rotate-webhook-secret', [AdminPaymentController::class, 'rotateWebhookSecret'])->middleware(['permission:payments.providers.manage', 'throttle:sensitive'])->name('api.v1.admin.payments.providers.rotate-secret');
        Route::post('/payments/reconcile', [AdminPaymentController::class, 'reconcile'])->middleware(['permission:payments.reconciliation.manage', 'throttle:sensitive'])->name('api.v1.admin.payments.reconcile');
        Route::post('/payments/intents/{paymentIntent}/refund', [AdminPaymentController::class, 'refund'])->middleware(['permission:payments.refunds.manage', 'throttle:sensitive'])->name('api.v1.admin.payments.refund');

        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->middleware('permission:analytics.admin.access')->name('api.v1.admin.analytics.index');
        Route::post('/analytics/refresh', [AdminAnalyticsController::class, 'refresh'])->middleware(['permission:analytics.refresh', 'throttle:sensitive'])->name('api.v1.admin.analytics.refresh');
        Route::patch('/analytics/insights/{insight}', [AdminAnalyticsController::class, 'updateInsight'])->middleware(['permission:analytics.admin.access', 'throttle:sensitive'])->name('api.v1.admin.analytics.insights.update');

        Route::get('/notifications', [AdminNotificationController::class, 'index'])->middleware('permission:notifications.admin.access')->name('api.v1.admin.notifications.index');
        Route::post('/notifications/templates', [AdminNotificationController::class, 'storeTemplate'])->middleware(['permission:notifications.templates.manage', 'throttle:sensitive'])->name('api.v1.admin.notifications.templates.store');
        Route::post('/notifications/test', [AdminNotificationController::class, 'sendTest'])->middleware(['permission:notifications.admin.access', 'throttle:sensitive'])->name('api.v1.admin.notifications.test');

        Route::get('/webhooks', [AdminWebhookController::class, 'index'])->middleware('permission:webhooks.admin.access')->name('api.v1.admin.webhooks.index');
        Route::post('/webhooks', [AdminWebhookController::class, 'store'])->middleware(['permission:webhooks.manage', 'throttle:sensitive'])->name('api.v1.admin.webhooks.store');
        Route::patch('/webhooks/{endpoint}', [AdminWebhookController::class, 'update'])->middleware(['permission:webhooks.manage', 'throttle:sensitive'])->name('api.v1.admin.webhooks.update');
        Route::post('/webhooks/{endpoint}/rotate-secret', [AdminWebhookController::class, 'rotate'])->middleware(['permission:webhooks.manage', 'throttle:sensitive'])->name('api.v1.admin.webhooks.rotate');
        Route::post('/webhook-deliveries/{delivery}/retry', [AdminWebhookController::class, 'retry'])->middleware(['permission:webhooks.manage', 'throttle:sensitive'])->name('api.v1.admin.webhooks.retry');

        Route::get('/support', [AdminSupportController::class, 'index'])->middleware('permission:support.admin.access')->name('api.v1.admin.support.index');
        Route::get('/support/{ticket}', [AdminSupportController::class, 'show'])->middleware('permission:support.admin.access')->name('api.v1.admin.support.show');
        Route::post('/support/{ticket}/reply', [AdminSupportController::class, 'reply'])->middleware(['permission:support.tickets.manage', 'throttle:sensitive'])->name('api.v1.admin.support.reply');
        Route::post('/support/{ticket}/transition', [AdminSupportController::class, 'transition'])->middleware(['permission:support.tickets.manage', 'throttle:sensitive'])->name('api.v1.admin.support.transition');

        Route::get('/operations', [AdminOperationsController::class, 'index'])->middleware('permission:operations.admin.access')->name('api.v1.admin.operations.index');
        Route::post('/operations/capture', [AdminOperationsController::class, 'capture'])->middleware(['permission:operations.manage', 'throttle:sensitive'])->name('api.v1.admin.operations.capture');
        Route::post('/operations/incidents/{incident}/acknowledge', [AdminOperationsController::class, 'acknowledge'])->middleware(['permission:operations.manage', 'throttle:sensitive'])->name('api.v1.admin.operations.incidents.acknowledge');
        Route::post('/operations/incidents/{incident}/resolve', [AdminOperationsController::class, 'resolve'])->middleware(['permission:operations.manage', 'throttle:sensitive'])->name('api.v1.admin.operations.incidents.resolve');


        Route::get('/reports', [AdminReportingController::class, 'index'])->middleware('permission:reporting.admin.access')->name('api.v1.admin.reports.index');
        Route::post('/reports/invoices/orders/{order}', [AdminReportingController::class, 'generateInvoice'])->middleware(['permission:reporting.invoices.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.invoices.generate');
        Route::post('/reports/invoices/{invoice}/void', [AdminReportingController::class, 'voidInvoice'])->middleware(['permission:reporting.invoices.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.invoices.void');
        Route::get('/reports/invoices/{invoice}/document', [AdminReportingController::class, 'invoiceDocument'])->middleware('permission:reporting.invoices.manage')->name('api.v1.admin.reports.invoices.document');
        Route::put('/reports/tax-profile', [AdminReportingController::class, 'updateTaxProfile'])->middleware(['permission:reporting.tax.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.tax-profile.update');
        Route::post('/reports/tax-rates', [AdminReportingController::class, 'storeTaxRate'])->middleware(['permission:reporting.tax.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.tax-rates.store');
        Route::patch('/reports/tax-rates/{taxRate}', [AdminReportingController::class, 'updateTaxRate'])->middleware(['permission:reporting.tax.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.tax-rates.update');
        Route::post('/reports/exports', [AdminReportingController::class, 'createExport'])->middleware(['permission:reporting.exports.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.exports.store');
        Route::get('/reports/exports/{dataExport}/download', [AdminReportingController::class, 'downloadExport'])->middleware('permission:reporting.exports.manage')->name('api.v1.admin.reports.exports.download');
        Route::post('/reports/schedules', [AdminReportingController::class, 'storeSchedule'])->middleware(['permission:reporting.schedules.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.schedules.store');
        Route::patch('/reports/schedules/{schedule}', [AdminReportingController::class, 'updateSchedule'])->middleware(['permission:reporting.schedules.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.schedules.update');
        Route::post('/reports/schedules/{schedule}/run', [AdminReportingController::class, 'runSchedule'])->middleware(['permission:reporting.schedules.manage', 'throttle:sensitive'])->name('api.v1.admin.reports.schedules.run');
    });
});
