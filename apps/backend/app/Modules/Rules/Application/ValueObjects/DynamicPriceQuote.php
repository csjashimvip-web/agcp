<?php
namespace Modules\Rules\Application\ValueObjects;
final readonly class DynamicPriceQuote
{
    public function __construct(
        public string $currency,
        public int $quantity,
        public int $baseAmountMinor,
        public int $adjustmentMinor,
        public int $finalAmountMinor,
        public string $priceListId,
        public array $matchedRuleIds,
        public array $breakdown,
        public ?string $quoteId = null,
        public ?string $expiresAt = null,
    ) {}
}
