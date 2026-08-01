<?php

namespace App\Modules\Supplier\Domain\Contracts;

/**
 * Marker contract for providers that expose a Dhru-compatible supplier API.
 *
 * AGCP core depends on SupplierProvider; Dhru compatibility remains an adapter concern.
 */
interface DhruCompatibleProvider extends SupplierProvider
{
}