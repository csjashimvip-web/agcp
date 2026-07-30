<?php

return ['providers' => [
    Modules\Shared\Infrastructure\SharedServiceProvider::class,
    Modules\Tenancy\Infrastructure\TenancyServiceProvider::class,
    Modules\Audit\Infrastructure\AuditServiceProvider::class,
    Modules\Identity\Infrastructure\IdentityServiceProvider::class,
    Modules\Wallet\Infrastructure\WalletServiceProvider::class,
    Modules\Commerce\Infrastructure\CommerceServiceProvider::class,
    Modules\Suppliers\Infrastructure\SupplierServiceProvider::class,
    Modules\Rules\Infrastructure\RulesServiceProvider::class,
    Modules\Fraud\Infrastructure\FraudServiceProvider::class,
    Modules\SaaS\Infrastructure\SaaSServiceProvider::class,
    Modules\Plugins\Infrastructure\PluginsServiceProvider::class,
]];
