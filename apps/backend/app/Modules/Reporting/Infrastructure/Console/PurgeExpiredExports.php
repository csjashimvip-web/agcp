<?php
namespace Modules\Reporting\Infrastructure\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Reporting\Domain\Enums\ExportStatus;
use Modules\Reporting\Infrastructure\Models\DataExport;
final class PurgeExpiredExports extends Command
{
    protected $signature='reports:purge-expired {--limit=500}';
    protected $description='Delete expired private export files and retain auditable export metadata.';
    public function handle():int{$count=0;foreach(DataExport::query()->whereNotNull('expires_at')->where('expires_at','<=',now())->where('status',ExportStatus::Completed)->limit((int)$this->option('limit'))->get() as $export){if($export->storage_path)Storage::disk($export->storage_disk)->delete($export->storage_path);$export->update(['status'=>ExportStatus::Expired,'storage_path'=>null]);$count++;}$this->info("Purged {$count} expired export(s).");return self::SUCCESS;}
}
