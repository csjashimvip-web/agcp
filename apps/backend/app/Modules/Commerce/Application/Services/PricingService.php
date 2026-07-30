<?php
namespace Modules\Commerce\Application\Services;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
final class PricingService
{
    public function resolve(CatalogVariant $variant, string $tenantId, string $currency, int $quantity = 1, ?string $segment = null): CatalogPrice
    {
        $now = now();
        $price = CatalogPrice::query()
            ->with('priceList')
            ->where('catalog_variant_id', $variant->id)
            ->where('min_quantity', '<=', $quantity)
            ->where(fn($query) => $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity))
            ->whereHas('priceList', function ($query) use ($tenantId, $currency, $segment, $now): void {
                $query->where('tenant_id', $tenantId)
                    ->where('currency', strtoupper($currency))
                    ->where('status', 'active')
                    ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                    ->where(function ($q) use ($segment): void {
                        $q->whereNull('customer_segment');
                        if ($segment !== null) $q->orWhere('customer_segment', $segment);
                    });
            })
            ->join('price_lists', 'price_lists.id', '=', 'catalog_prices.price_list_id')
            ->orderByRaw('CASE WHEN price_lists.customer_segment IS NULL THEN 1 ELSE 0 END')
            ->orderBy('price_lists.priority')
            ->orderByDesc('catalog_prices.min_quantity')
            ->select('catalog_prices.*')
            ->first();

        if ($price === null) {
            throw ValidationException::withMessages(['price' => 'No active price is available for this item and currency.']);
        }
        return $price->load('priceList');
    }
}
