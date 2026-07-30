<?php

namespace Modules\Reliability\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Reliability\Application\Services\EnvironmentReadinessService;

final class RunReleaseCheck extends Command
{
    protected $signature = 'reliability:check {--persist : Store the result in release_checks} {--fail-on-warning : Return failure when warnings exist}';
    protected $description = 'Evaluate production readiness, dependencies, scheduler heartbeat and backup posture.';

    public function handle(EnvironmentReadinessService $service): int
    {
        $report = $service->evaluate();
        foreach ($report['checks'] as $check) {
            $this->line(sprintf('[%s] %s — %s', strtoupper($check['status']), $check['key'], $check['message']));
        }
        if ($this->option('persist')) {
            $service->persist();
        }
        $this->newLine();
        $this->info('Readiness status: '.strtoupper($report['status']));
        if ($report['status'] === 'failed' || ($this->option('fail-on-warning') && $report['status'] === 'warning')) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
