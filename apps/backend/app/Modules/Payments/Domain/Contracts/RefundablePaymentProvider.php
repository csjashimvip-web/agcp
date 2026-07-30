<?php
namespace Modules\Payments\Domain\Contracts;

interface RefundablePaymentProvider
{
    /**
     * @param array<string,mixed> $context
     * @return array{provider_refund_id:string,status:string,metadata?:array<string,mixed>}
     */
    public function refundPayment(string $providerPaymentId, string $reference, int $amountMinor, string $currency, array $context = []): array;
}
