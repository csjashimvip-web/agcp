<?php

namespace Modules\Reliability\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Reliability\Infrastructure\Models\SystemBackup;

final class PurgeExpiredBackups extends Command
{
    protected $signature = 'reliability:purge-backups {--limit=100}';
    protected $description = 'Delete expired private backup artifacts while preserving audit metadata.';

    public function handle(): int
    {
        $count = 0;
        SystemBackup::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereIn('status', ['completed', 'failed', 'expired'])
            ->oldest('expires_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (SystemBackup $backup) use (&$count): void {
                if ($backup->storage_path) {
                    Storage::disk($backup->storage_disk)->delete($backup->storage_path);
                }
                $backup->forceFill(['status' => 'expired', 'storage_path' => null])->save();
                $count++;
            });
        $this->info("Expired {$count} backup artifacts.");
        return self::SUCCESS;
    }
}
