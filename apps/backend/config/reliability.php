<?php

return [
    'backup' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'disk' => env('BACKUP_DISK', 'local'),
        'directory' => trim((string) env('BACKUP_DIRECTORY', 'backups/database'), '/'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'daily_time' => env('BACKUP_DAILY_TIME', '01:30'),
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 36),
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
        'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'command_timeout_seconds' => (int) env('BACKUP_COMMAND_TIMEOUT_SECONDS', 1800),
        'chunk_bytes' => (int) env('BACKUP_ENCRYPTION_CHUNK_BYTES', 1048576),
    ],
    'heartbeat_ttl_minutes' => (int) env('RUNTIME_HEARTBEAT_TTL_MINUTES', 3),
];
