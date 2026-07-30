<?php
namespace Modules\Suppliers\Application\Services;

use InvalidArgumentException;
use Modules\Suppliers\Domain\Contracts\SupplierProvider;

final class SupplierProviderRegistry
{
    /** @var array<string,SupplierProvider> */
    private array $providers = [];

    public function register(SupplierProvider $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function get(string $code): SupplierProvider
    {
        return $this->providers[$code]
            ?? throw new InvalidArgumentException("Supplier provider [{$code}] is not registered.");
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->providers);
    }
}
