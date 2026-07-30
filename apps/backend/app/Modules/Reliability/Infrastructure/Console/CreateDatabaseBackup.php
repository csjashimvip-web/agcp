<?php

namespace Modules\Reliability\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Reliability\Application\Services\DatabaseBackupService;

final class CreateDatabaseBackup extends Command
{
    protected $signature = 'reliability:backup';
    protected $description = 'Create a compressed and authenticated encrypted MySQL backup.';

    public function handle(DatabaseBackupService $service): int
    {
        $backup = $service->create();
        $this->info("Backup {$backup->id} completed ({$backup->file_size} bytes).");
        return self::SUCCESS;
    }
}
