<?php
return [
    'provider' => env('ANALYTICS_PROVIDER', 'deterministic'),
    'window_days' => (int) env('ANALYTICS_WINDOW_DAYS', 30),
    'forecast_horizon_days' => (int) env('ANALYTICS_FORECAST_HORIZON_DAYS', 14),
    'refresh_time' => env('ANALYTICS_REFRESH_TIME', '02:10'),
];
