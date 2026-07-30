<?php

namespace Modules\Reliability\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Reliability\Infrastructure\Models\SystemBackup;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseBackupService
{
    public function __construct(private readonly BackupCipher $cipher) {}

    public function create(?User $actor = null): SystemBackup
    {
        if (! config('reliability.backup.enabled')) {
            throw new RuntimeException('Automated backups are disabled.');
        }

        $diskName = (string) config('reliability.backup.disk', 'local');
        $diskConfig = (array) config("filesystems.disks.{$diskName}", []);
        if (($diskConfig['driver'] ?? null) !== 'local') {
            throw new RuntimeException('Phase 12 database backups currently require a private local filesystem disk.');
        }

        $backup = SystemBackup::query()->create([
            'requested_by' => $actor?->id,
            'type' => 'database',
            'status' => 'running',
            'storage_disk' => $diskName,
            'encrypted' => true,
            'started_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('reliability.backup.retention_days', 14))),
            'metadata' => [
                'database' => (string) config('database.connections.mysql.database'),
                'engine' => 'mysql',
                'compression' => 'gzip',
                'cipher' => 'xchacha20poly1305-secretstream',
            ],
        ]);

        $directory = trim((string) config('reliability.backup.directory', 'backups/database'), '/');
        $filename = sprintf('agcp-%s-%s.sql.gz.enc', now()->format('Ymd-His'), $backup->id);
        $relativePath = $directory.'/'.$filename;
        $disk = Storage::disk($diskName);
        $disk->makeDirectory($directory);
        $finalPath = $disk->path($relativePath);
        $tempDirectory = storage_path('app/private/backups/tmp');
        if (! is_dir($tempDirectory) && ! mkdir($tempDirectory, 0700, true) && ! is_dir($tempDirectory)) {
            throw new RuntimeException('Unable to create the private backup temporary directory.');
        }

        $rawPath = $tempDirectory.'/'.$backup->id.'.sql';
        $gzipPath = $tempDirectory.'/'.$backup->id.'.sql.gz';

        try {
            $this->dumpDatabase($rawPath);
            $this->gzip($rawPath, $gzipPath);
            $this->cipher->encrypt($gzipPath, $finalPath);

            $checksum = hash_file('sha256', $finalPath);
            $size = filesize($finalPath);
            if ($checksum === false || $size === false || $size < 1) {
                throw new RuntimeException('Backup artifact metadata could not be calculated.');
            }

            $backup->forceFill([
                'status' => 'completed',
                'storage_path' => $relativePath,
                'checksum_sha256' => $checksum,
                'file_size' => $size,
                'completed_at' => now(),
            ])->save();

            return $backup->fresh();
        } catch (Throwable $exception) {
            @unlink($finalPath);
            $backup->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'completed_at' => now(),
            ])->save();
            throw $exception;
        } finally {
            @unlink($rawPath);
            @unlink($gzipPath);
        }
    }

    private function dumpDatabase(string $destination): void
    {
        $connection = (array) config('database.connections.mysql');
        $arguments = [
            (string) config('reliability.backup.mysqldump_binary', 'mysqldump'),
            '--host='.(string) ($connection['host'] ?? 'mysql'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) ($connection['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--events',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--no-tablespaces',
            (string) ($connection['database'] ?? ''),
        ];

        $command = implode(' ', array_map('escapeshellarg', $arguments)).' > '.escapeshellarg($destination);
        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(max(60, (int) config('reliability.backup.command_timeout_seconds', 1800)));
        $process->mustRun();

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('mysqldump completed without producing a database backup.');
        }
    }

    private function gzip(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');
        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open gzip backup streams.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read SQL dump for compression.');
                }
                if ($chunk !== '' && gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Unable to write compressed backup archive.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }
}
