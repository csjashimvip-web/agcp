<?php
namespace Modules\Payments\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Payments\Application\Services\PaymentReconciliationService;
use Modules\Payments\Infrastructure\Models\PaymentProviderAccount;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--tenant=} {--provider-account=}';
    protected $description = 'Reconcile verified payment intents, wallet credits and external refunds.';

    public function handle(PaymentReconciliationService $service): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->where('status', 'active')->get();
        foreach ($tenants as $tenant) {
            $account = $this->option('provider-account')
                ? PaymentProviderAccount::query()->where('tenant_id', $tenant->id)->where('code', $this->option('provider-account'))->firstOrFail()
                : null;
            $run = $service->run($tenant->id, $account);
            $this->info($tenant->slug.': checked '.$run->checked_count.', mismatches '.$run->mismatch_count.'.');
        }
        return self::SUCCESS;
    }
}
