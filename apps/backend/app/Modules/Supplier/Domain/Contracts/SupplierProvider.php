<?php

namespace App\Modules\Supplier\Domain\Contracts;

interface SupplierProvider
{
    public function code(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function services(): array;

    /**
     * @return array{amount_minor:int,currency:string}|null
     */
    public function balance(): ?array;

    /**
     * @param array<string, mixed> $payload
     * @return array{external_order_id:string,status:string,raw?:array<string,mixed>}
     */
    public function submit(array $payload): array;

    /**
     * @return array{status:string,result?:mixed,raw?:array<string,mixed>}
     */
    public function status(string $externalOrderId): array;
}