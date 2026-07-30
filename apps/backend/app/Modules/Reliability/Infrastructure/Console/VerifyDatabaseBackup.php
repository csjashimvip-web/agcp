<?php

namespace Modules\Reliability\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Reliability\Application\Services\BackupVerificationService;
use Modules\Reliability\Infrastructure\Models\SystemBackup;

final class VerifyDatabaseBackup extends Command
{
    protected $signature = 'reliability:verify-backup {backup? : Backup UUID} {--latest : Verify the latest completed backup}';
    protected $description = 'Verify backup checksum, authenticated decryption and gzip archive integrity without restoring production data.';

    public function handle(BackupVerificationService $service): int
    {
        $id = (string) $this->argument('backup');
        $backup = $id !== ''
            ? SystemBackup::query()->findOrFail($id)
            : SystemBackup::query()->where('status', 'completed')->latest('completed_at')->firstOrFail();
        $drill = $service->verify($backup);
        $this->info("Restore drill {$drill->id} passed for backup {$backup->id}.");
        return self::SUCCESS;
    }
}
