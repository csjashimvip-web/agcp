<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Reliability\Application\Services\BackupCipher;
use Modules\Reliability\Application\Services\BackupVerificationService;
use Modules\Reliability\Infrastructure\Models\RestoreDrill;
use Modules\Reliability\Infrastructure\Models\SystemBackup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('reliability.backup.encryption_key', base64_encode(random_bytes(32)));
    config()->set('reliability.backup.chunk_bytes', 65536);
    Storage::fake('local');
});

it('round trips an authenticated encrypted backup stream', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'agcp-source-');
    $encrypted = tempnam(sys_get_temp_dir(), 'agcp-encrypted-');
    $decrypted = tempnam(sys_get_temp_dir(), 'agcp-decrypted-');
    $payload = str_repeat("-- MySQL dump\nCREATE TABLE example (id bigint);\n", 5000);
    file_put_contents($source, $payload);

    $cipher = app(BackupCipher::class);
    $cipher->encrypt($source, $encrypted);
    $cipher->decrypt($encrypted, $decrypted);

    expect(file_get_contents($decrypted))->toBe($payload)
        ->and(hash_file('sha256', $encrypted))->not->toBe(hash_file('sha256', $source));

    @unlink($source); @unlink($encrypted); @unlink($decrypted);
});

it('records a passing non destructive restore drill', function (): void {
    $disk = Storage::disk('local');
    $plain = tempnam(sys_get_temp_dir(), 'agcp-sql-');
    $gzip = tempnam(sys_get_temp_dir(), 'agcp-gzip-');
    file_put_contents($plain, "-- MySQL dump\nSET NAMES utf8mb4;\nCREATE TABLE example (id bigint);\n");
    $gz = gzopen($gzip, 'wb9'); gzwrite($gz, file_get_contents($plain)); gzclose($gz);
    $path = 'backups/database/test.sql.gz.enc';
    $disk->makeDirectory('backups/database');
    app(BackupCipher::class)->encrypt($gzip, $disk->path($path));

    $backup = SystemBackup::query()->create([
        'type' => 'database', 'status' => 'completed', 'storage_disk' => 'local', 'storage_path' => $path,
        'checksum_sha256' => hash_file('sha256', $disk->path($path)), 'file_size' => filesize($disk->path($path)),
        'encrypted' => true, 'started_at' => now(), 'completed_at' => now(), 'expires_at' => now()->addDay(),
    ]);

    $drill = app(BackupVerificationService::class)->verify($backup);
    expect($drill->status)->toBe('passed')
        ->and($drill->checksum_verified)->toBeTrue()
        ->and($drill->decryption_verified)->toBeTrue()
        ->and($drill->archive_verified)->toBeTrue()
        ->and($backup->fresh()->verified_at)->not->toBeNull();

    @unlink($plain); @unlink($gzip);
});

it('rejects a backup whose checksum no longer matches', function (): void {
    $disk = Storage::disk('local');
    $path = 'backups/database/tampered.sql.gz.enc';
    $disk->put($path, 'tampered');
    $backup = SystemBackup::query()->create([
        'type' => 'database', 'status' => 'completed', 'storage_disk' => 'local', 'storage_path' => $path,
        'checksum_sha256' => str_repeat('0', 64), 'file_size' => 8, 'encrypted' => true,
        'started_at' => now(), 'completed_at' => now(), 'expires_at' => now()->addDay(),
    ]);

    expect(fn () => app(BackupVerificationService::class)->verify($backup))
        ->toThrow(RuntimeException::class, 'checksum');
    expect(RestoreDrill::query()->firstOrFail()->status)->toBe('failed');
});
