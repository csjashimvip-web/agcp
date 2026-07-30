<?php
namespace Modules\Payments\Domain\Contracts;

interface PaymentProvider
{
    public function code(): string;

    /**
     * @param array<string,mixed> $context
     * @return array{provider_payment_id:string,checkout_url:?string,status:string,expires_at:?string,metadata?:array<string,mixed>}
     */
    public function createPayment(string $reference, int $amountMinor, string $currency, array $context = []): array;

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $context
     * @return array{event_id:string,type:string,reference:string,provider_payment_id:?string,amount_minor:int,currency:string,occurred_at:?string,metadata?:array<string,mixed>}
     */
    public function verifyWebhook(string $payload, array $headers, array $context = []): array;
}
