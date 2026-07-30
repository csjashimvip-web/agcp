<?php

namespace Modules\Reliability\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Reliability\Infrastructure\Models\RestoreDrill;
use Modules\Reliability\Infrastructure\Models\SystemBackup;
use RuntimeException;
use Throwable;

final class BackupVerificationService
{
    public function __construct(private readonly BackupCipher $cipher) {}

    public function verify(SystemBackup $backup, ?User $actor = null): RestoreDrill
    {
        $drill = RestoreDrill::query()->create([
            'system_backup_id' => $backup->id,
            'initiated_by' => $actor?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $temporary = storage_path('app/private/backups/tmp/'.$drill->id.'.verified.sql.gz');
        if (! is_dir(dirname($temporary))) {
            mkdir(dirname($temporary), 0700, true);
        }

        try {
            if ($backup->status !== 'completed' || ! $backup->storage_path || ! $backup->checksum_sha256) {
                throw new RuntimeException('Only completed backups can be verified.');
            }

            $disk = Storage::disk($backup->storage_disk);
            if (! $disk->exists($backup->storage_path)) {
                throw new RuntimeException('Backup artifact is missing from private storage.');
            }

            $source = $disk->path($backup->storage_path);
            $actualChecksum = hash_file('sha256', $source);
            if (! is_string($actualChecksum) || ! hash_equals($backup->checksum_sha256, $actualChecksum)) {
                throw new RuntimeException('Backup checksum verification failed.');
            }
            $drill->checksum_verified = true;
            $drill->save();

            $this->cipher->decrypt($source, $temporary);
            $drill->decryption_verified = true;
            $drill->save();

            [$bytes, $signature] = $this->inspectGzip($temporary);
            if (! preg_match('/(-- MySQL dump|-- Host:|CREATE TABLE|SET @|SET NAMES)/i', $signature)) {
                throw new RuntimeException('Decrypted archive does not look like a MySQL logical backup.');
            }

            $drill->forceFill([
                'status' => 'passed',
                'archive_verified' => true,
                'inspected_bytes' => $bytes,
                'completed_at' => now(),
                'metadata' => ['verification' => 'checksum+authenticated-decryption+full-gzip-read'],
            ])->save();
            $backup->forceFill(['verified_at' => now()])->save();

            return $drill->fresh();
        } catch (Throwable $exception) {
            $drill->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'completed_at' => now(),
            ])->save();
            throw $exception;
        } finally {
            @unlink($temporary);
        }
    }

    /** @return array{0:int,1:string} */
    private function inspectGzip(string $path): array
    {
        $stream = gzopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open decrypted gzip backup.');
        }

        $bytes = 0;
        $signature = '';
        try {
            while (! gzeof($stream)) {
                $chunk = gzread($stream, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Compressed backup integrity verification failed.');
                }
                $bytes += strlen($chunk);
                if (strlen($signature) < 8192) {
                    $signature .= substr($chunk, 0, 8192 - strlen($signature));
                }
            }
        } finally {
            gzclose($stream);
        }

        if ($bytes < 1) {
            throw new RuntimeException('Decrypted backup archive is empty.');
        }

        return [$bytes, $signature];
    }
}
