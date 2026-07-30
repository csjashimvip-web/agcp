<?php
namespace Modules\Commerce\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Infrastructure\Models\Cart;
use Modules\Commerce\Infrastructure\Models\CartItem;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
final class CartService
{
    public function __construct(private readonly PricingService $pricing) {}

    public function current(User $user, string $tenantId, string $currency): Cart
    {
        $cart = Cart::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'currency' => strtoupper($currency),
            'status' => 'active',
        ], ['expires_at' => now()->addDays(14)]);
        return $cart->load(['items.variant.item', 'items.priceList']);
    }

    public function add(User $user, string $tenantId, string $variantId, int $quantity, array $configuration, string $currency): Cart
    {
        return DB::transaction(function () use ($user, $tenantId, $variantId, $quantity, $configuration, $currency): Cart {
            if ($quantity < 1 || $quantity > 1000) throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 1000.']);
            $variant = CatalogVariant::query()->with('item')->whereKey($variantId)->where('status', 'active')
                ->whereHas('item', fn($q) => $q->where('tenant_id', $tenantId)->where('status', 'active'))->firstOrFail();
            $this->validateConfiguration($variant, $configuration);
            $price = $this->pricing->resolve($variant, $tenantId, $currency, $quantity);
            $cart = $this->current($user, $tenantId, $currency);
            CartItem::query()->create([
                'cart_id' => $cart->id,
                'catalog_variant_id' => $variant->id,
                'price_list_id' => $price->price_list_id,
                'quantity' => $quantity,
                'unit_price_minor' => $price->amount_minor,
                'configuration' => $configuration,
            ]);
            return $cart->fresh(['items.variant.item', 'items.priceList']);
        }, 5);
    }

    public function update(User $user, string $tenantId, CartItem $item, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $tenantId, $item, $quantity): Cart {
            $locked = CartItem::query()->with(['cart', 'variant.item'])->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->cart->user_id !== $user->id || $locked->cart->tenant_id !== $tenantId || $locked->cart->status !== 'active') abort(404);
            if ($quantity <= 0) $locked->delete();
            else {
                if ($quantity > 1000) throw ValidationException::withMessages(['quantity' => 'Quantity cannot exceed 1000.']);
                $price = $this->pricing->resolve($locked->variant, $tenantId, $locked->cart->currency, $quantity);
                $locked->forceFill(['quantity'=>$quantity,'unit_price_minor'=>$price->amount_minor,'price_list_id'=>$price->price_list_id])->save();
            }
            return $locked->cart->fresh(['items.variant.item', 'items.priceList']);
        }, 5);
    }

    private function validateConfiguration(CatalogVariant $variant, array $configuration): void
    {
        if ($variant->item->type->value !== 'service') return;
        foreach (($variant->item->service_schema['fields'] ?? []) as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '' && ($field['required'] ?? false) && (!array_key_exists($name, $configuration) || trim((string)$configuration[$name]) === '')) {
                throw ValidationException::withMessages(['configuration.'.$name => 'This service field is required.']);
            }
        }
    }
}
