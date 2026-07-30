<?php
namespace Modules\Payments\Domain\Contracts;
interface PaymentProvider
{
    public function code(): string;
    public function createPayment(string $reference, int $amountMinor, string $currency, array $context = []): array;
    public function verifyWebhook(string $payload, array $headers): array;
}
