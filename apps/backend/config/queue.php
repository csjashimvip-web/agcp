<?php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 120, 'after_commit' => true],
        'redis' => ['driver' => 'redis', 'connection' => 'queue', 'queue' => env('REDIS_QUEUE', 'default'), 'retry_after' => 120, 'block_for' => 5, 'after_commit' => true],
    ],
    'batching' => ['database' => env('DB_CONNECTION', 'mysql'), 'table' => 'job_batches'],
    'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'mysql'), 'table' => 'failed_jobs'],
];
