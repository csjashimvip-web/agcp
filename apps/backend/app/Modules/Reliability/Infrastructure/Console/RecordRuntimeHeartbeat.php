<?php

namespace Modules\Reliability\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Reliability\Infrastructure\Models\RuntimeHeartbeat;

final class RecordRuntimeHeartbeat extends Command
{
    protected $signature = 'reliability:heartbeat {component=scheduler}';
    protected $description = 'Record a runtime component heartbeat for readiness checks.';

    public function handle(): int
    {
        $component = (string) $this->argument('component');
        RuntimeHeartbeat::query()->updateOrCreate(['component' => $component], [
            'status' => 'healthy',
            'metadata' => ['hostname' => gethostname() ?: 'unknown', 'pid' => getmypid()],
            'last_seen_at' => now(),
        ]);
        return self::SUCCESS;
    }
}
