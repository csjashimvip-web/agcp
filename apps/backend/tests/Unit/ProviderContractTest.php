<?php

namespace Tests\Unit;

use App\Modules\Payments\Domain\Contracts\PaymentProvider;
use App\Modules\Supplier\Domain\Contracts\DhruCompatibleProvider;
use App\Modules\Supplier\Domain\Contracts\SupplierProvider;
use PHPUnit\Framework\TestCase;

final class ProviderContractTest extends TestCase
{
    public function test_dhru_integrations_remain_behind_the_generic_supplier_contract(): void
    {
        $this->assertTrue(
            is_subclass_of(DhruCompatibleProvider::class, SupplierProvider::class)
        );
    }

    public function test_payment_provider_abstraction_exists(): void
    {
        $this->assertTrue(interface_exists(PaymentProvider::class));
    }
}