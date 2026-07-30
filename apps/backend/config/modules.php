<?php

return ['providers' => [
    Modules\Shared\Infrastructure\SharedServiceProvider::class,
    Modules\Tenancy\Infrastructure\TenancyServiceProvider::class,
    Modules\Audit\Infrastructure\AuditServiceProvider::class,
    Modules\Identity\Infrastructure\IdentityServiceProvider::class,
    Modules\Wallet\Infrastructure\WalletServiceProvider::class,
    Modules\Payments\Infrastructure\PaymentsServiceProvider::class,
    Modules\Commerce\Infrastructure\CommerceServiceProvider::class,
    Modules\Suppliers\Infrastructure\SupplierServiceProvider::class,
    Modules\Rules\Infrastructure\RulesServiceProvider::class,
    Modules\Fraud\Infrastructure\FraudServiceProvider::class,
    Modules\SaaS\Infrastructure\SaaSServiceProvider::class,
    Modules\Plugins\Infrastructure\PluginsServiceProvider::class,
    Modules\Analytics\Infrastructure\AnalyticsServiceProvider::class,
    Modules\Notifications\Infrastructure\NotificationsServiceProvider::class,
    Modules\Integrations\Infrastructure\IntegrationsServiceProvider::class,
    Modules\Support\Infrastructure\SupportServiceProvider::class,
    Modules\Observability\Infrastructure\ObservabilityServiceProvider::class,
    Modules\Reporting\Infrastructure\ReportingServiceProvider::class,
]];
