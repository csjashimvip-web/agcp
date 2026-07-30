<?php
return [
    'default_plan' => env('TENANT_DEFAULT_PLAN', 'enterprise'),
    'usage_period' => env('SAAS_USAGE_PERIOD', 'monthly'),
    'domain_verification_mode' => env('DOMAIN_VERIFICATION_MODE', 'manual'),
];
