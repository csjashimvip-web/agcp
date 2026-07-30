<?php
namespace Modules\Suppliers\Infrastructure\Providers;

use Modules\Suppliers\Domain\Contracts\SupplierProvider;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use RuntimeException;

final class SandboxSupplierProvider implements SupplierProvider
{
    public function code(): string
    {
        return 'sandbox';
    }

    public function health(SupplierAccount $account): array
    {
        $latency = (int) (($account->metadata['sandbox_latency_ms'] ?? null) ?: 25);
        $healthy = ! (bool) ($account->metadata['sandbox_unhealthy'] ?? false);

        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'score' => $healthy ? 100.0 : 0.0,
            'latency_ms' => $latency,
            'details' => ['provider' => 'sandbox', 'account' => $account->code],
        ];
    }

    public function submit(SupplierAccount $account, string $serviceCode, string $clientReference, array $fields): array
    {
        if ((bool) ($account->metadata['sandbox_fail_submissions'] ?? false) || (bool) ($fields['simulate_failure'] ?? false)) {
            throw new RuntimeException('Sandbox supplier simulated a submission failure.');
        }

        $reference = 'SBX-'.strtoupper(substr(hash('sha256', $account->id.$serviceCode.$clientReference), 0, 18));
        $pending = (bool) ($fields['simulate_pending'] ?? false);

        return [
            'supplier_reference' => $reference,
            'status' => $pending ? 'processing' : 'completed',
            'result' => $pending ? null : [
                'service_code' => $serviceCode,
                'reference' => $clientReference,
                'message' => 'Sandbox fulfillment completed successfully.',
            ],
            'raw' => ['sandbox' => true, 'account' => $account->code],
        ];
    }

    public function status(SupplierAccount $account, string $supplierReference): array
    {
        if ((bool) ($account->metadata['sandbox_fail_status'] ?? false)) {
            throw new RuntimeException('Sandbox supplier simulated a status failure.');
        }

        return [
            'status' => 'completed',
            'result' => ['supplier_reference' => $supplierReference, 'message' => 'Sandbox order completed.'],
            'raw' => ['sandbox' => true],
        ];
    }

    public function cancel(SupplierAccount $account, string $supplierReference): bool
    {
        return true;
    }
}
