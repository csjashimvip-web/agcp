<?php
namespace Modules\Suppliers\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Suppliers\Application\Services\SupplierHealthService;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;

final class CheckSupplierHealth extends Command
{
    protected $signature = 'supplier:health-check {--tenant=}';
    protected $description = 'Check configured supplier providers and update health metrics.';

    public function handle(SupplierHealthService $health): int
    {
        $query = SupplierAccount::query()->where('status', '!=', 'disabled');
        if ($this->option('tenant')) $query->where('tenant_id', $this->option('tenant'));
        $count = 0;
        $query->orderBy('created_at')->chunk(100, function ($suppliers) use ($health, &$count): void {
            foreach ($suppliers as $supplier) {
                $health->check($supplier);
                $count++;
            }
        });
        $this->info('Checked '.$count.' supplier account(s).');
        return self::SUCCESS;
    }
}
