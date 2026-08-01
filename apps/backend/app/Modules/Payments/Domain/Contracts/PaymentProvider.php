<?php

namespace App\Modules\Payments\Domain\Contracts;

interface PaymentProvider
{
    public function code(): string;

    /**
     * @param array<string, mixed> $payload
     * @return array{provider_reference:string,status:string,redirect_url?:string,raw?:array<string,mixed>}
     */
    public function createIntent(array $payload): array;

    /**
     * @return array{valid:bool,event_id?:string,event_type?:string,payload?:array<string,mixed>}
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * @return array{provider_reference:string,status:string,raw?:array<string,mixed>}
     */
    public function refund(string $providerReference, int $amountMinor, string $currency): array;
}