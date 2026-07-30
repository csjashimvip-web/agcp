<?php
namespace Modules\Suppliers\Domain\Contracts;
interface SupplierProvider
{
    public function code(): string;
    public function health(): array;
    public function submit(string $serviceCode, string $clientReference, array $fields): array;
    public function status(string $supplierReference): array;
}
