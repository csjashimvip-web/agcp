<?php
namespace Modules\Commerce\Application\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\InventoryLevel;
use Modules\Commerce\Infrastructure\Models\InventoryReservation;
use Modules\Commerce\Infrastructure\Models\Order;
final class InventoryService
{
    /** @return array<int,InventoryReservation> */
    public function reserve(Order $order, CatalogVariant $variant, int $quantity): array
    {
        $item = $variant->item;
        if (!$item->inventory_tracking) return [];

        $remaining = $quantity;
        $reservations = [];
        $levels = InventoryLevel::query()
            ->where('catalog_variant_id', $variant->id)
            ->whereHas('location', fn($q) => $q->where('tenant_id', $order->tenant_id)->where('status', 'active'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($levels as $level) {
            $available = $level->available();
            if ($available <= 0) continue;
            $take = min($remaining, $available);
            $level->increment('reserved', $take);
            $reservations[] = InventoryReservation::query()->create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'inventory_level_id' => $level->id,
                'catalog_variant_id' => $variant->id,
                'quantity' => $take,
                'status' => 'active',
                'expires_at' => now()->addHours(24),
            ]);
            $remaining -= $take;
            if ($remaining === 0) break;
        }

        if ($remaining > 0 && !$item->allow_backorder) {
            throw ValidationException::withMessages(['inventory' => 'Insufficient inventory for '.$item->name.'.']);
        }
        return $reservations;
    }

    public function release(Order $order): void
    {
        $reservations = InventoryReservation::query()->where('order_id', $order->id)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
        foreach ($reservations as $reservation) {
            $level = InventoryLevel::query()->whereKey($reservation->inventory_level_id)->lockForUpdate()->firstOrFail();
            $level->forceFill(['reserved' => max(0, (int)$level->reserved - (int)$reservation->quantity)])->save();
            $reservation->forceFill(['status' => 'released', 'released_at' => now()])->save();
        }
    }

    public function consume(Order $order): void
    {
        $reservations = InventoryReservation::query()->where('order_id', $order->id)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
        foreach ($reservations as $reservation) {
            $level = InventoryLevel::query()->whereKey($reservation->inventory_level_id)->lockForUpdate()->firstOrFail();
            $quantity = (int)$reservation->quantity;
            if ((int)$level->on_hand < $quantity) {
                throw ValidationException::withMessages(['inventory' => 'Reserved inventory is no longer available.']);
            }
            $level->forceFill([
                'on_hand' => (int)$level->on_hand - $quantity,
                'reserved' => max(0, (int)$level->reserved - $quantity),
            ])->save();
            $reservation->forceFill(['status' => 'consumed', 'released_at' => now()])->save();
        }
    }
}
