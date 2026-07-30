<?php
namespace Modules\Fraud\Domain\Contracts;
interface FraudSignalProvider
{
    public function assess(array $context): array;
}
