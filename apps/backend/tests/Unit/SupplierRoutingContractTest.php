<?php

namespace Tests\Unit;

use App\Modules\Supplier\Domain\Contracts\DhruCompatibleProvider;
use App\Modules\Supplier\Infrastructure\Dhru\DhruFusionProvider;
use PHPUnit\Framework\TestCase;

final class SupplierRoutingContractTest extends TestCase
{
    public function test_dhru_provider_implements_the_generic_compatibility_contract(): void
    {
        $this->assertTrue(
            is_subclass_of(DhruFusionProvider::class, DhruCompatibleProvider::class)
        );
    }
}