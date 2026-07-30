<?php

return [
    'intent_expiry_minutes' => (int) env('PAYMENT_INTENT_EXPIRY_MINUTES', 30),
    'webhook_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TOLERANCE_SECONDS', 300),
    'reconciliation_window_days' => (int) env('PAYMENT_RECONCILIATION_WINDOW_DAYS', 30),
    'reconciliation_time' => env('PAYMENT_RECONCILIATION_TIME', '03:10'),
    'sandbox_webhook_secret' => env('SANDBOX_PAYMENT_WEBHOOK_SECRET', 'local-sandbox-payment-secret-change-me'),
];
