<?php
namespace Modules\Payments\Application\Services;

use InvalidArgumentException;
use Modules\Payments\Domain\Contracts\PaymentProvider;

final class PaymentProviderRegistry
{
    /** @var array<string,PaymentProvider> */
    private array $providers = [];

    /** @param iterable<PaymentProvider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) $this->providers[$provider->code()] = $provider;
    }

    public function get(string $code): PaymentProvider
    {
        return $this->providers[$code] ?? throw new InvalidArgumentException('Unknown payment provider: '.$code);
    }

    /** @return array<int,string> */
    public function codes(): array
    {
        return array_keys($this->providers);
    }
}
