<?php
namespace Modules\Suppliers\Domain\Contracts;

use Modules\Suppliers\Infrastructure\Models\SupplierAccount;

interface SupplierProvider
{
    public function code(): string;

    /** @return array{status:string,score?:float,latency_ms?:int,details?:array} */
    public function health(SupplierAccount $account): array;

    /** @return array{supplier_reference:string,status:string,result?:array,raw?:array} */
    public function submit(SupplierAccount $account, string $serviceCode, string $clientReference, array $fields): array;

    /** @return array{status:string,result?:array,raw?:array} */
    public function status(SupplierAccount $account, string $supplierReference): array;

    public function cancel(SupplierAccount $account, string $supplierReference): bool;
}
