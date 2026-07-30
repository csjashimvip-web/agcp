<?php
return [
    'review_score' => (int) env('FRAUD_REVIEW_SCORE', 60),
    'block_score' => (int) env('FRAUD_BLOCK_SCORE', 80),
    'high_value_minor' => (int) env('FRAUD_HIGH_VALUE_MINOR', 50000),
    'critical_value_minor' => (int) env('FRAUD_CRITICAL_VALUE_MINOR', 200000),
    'quote_ttl_seconds' => (int) env('DYNAMIC_PRICE_QUOTE_TTL', 300),
];
