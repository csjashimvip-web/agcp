<?php

namespace App\Modules\Supplier\Application\Contracts;

use App\Modules\Supplier\Domain\Contracts\SupplierProvider;
use App\Modules\Supplier\Domain\Models\Supplier;

interface SupplierProviderFactory
{
    public function make(Supplier $supplier): SupplierProvider;
}