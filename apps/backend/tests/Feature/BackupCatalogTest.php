<?php

namespace Tests\Feature;

use App\Modules\Reliability\Application\BackupCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackupCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_registration_and_restore_drill_are_auditable(): void
    {
        $service = app(BackupCatalogService::class);

        $backup = $service->register(
            'C:\backups\agcp-test.sql',
            str_repeat('a', 64),
            12345,
        );

        $this->assertSame('completed', $backup->status);

        $drill = $service->recordDrill(
            $backup->id,
            true,
            'Checksum verified and restore completed in isolated database.'
        );

        $this->assertSame('passed', $drill->status);

        $this->assertNotNull(
            \Illuminate\Support\Facades\DB::table('backup_catalogs')
                ->where('id', $backup->id)
                ->value('verified_at')
        );
    }
}