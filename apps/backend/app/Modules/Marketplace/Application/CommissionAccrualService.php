<?php

namespace App\Modules\Marketplace\Application;

use Illuminate\Support\Facades\DB;

final class CommissionAccrualService
{
    public function accrueForOrder(int $orderId): int
    {
        $order = DB::table('orders')->where('id', $orderId)->first();

        if (! $order || $order->status !== 'completed') {
            return 0;
        }

        $created = 0;

        $items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get();

        foreach ($items as $item) {
            $listing = DB::table('marketplace_listings')
                ->join(
                    'marketplace_sellers',
                    'marketplace_sellers.id',
                    '=',
                    'marketplace_listings.marketplace_seller_id'
                )
                ->where('marketplace_listings.tenant_id', $order->tenant_id)
                ->where('marketplace_listings.product_id', $item->product_id)
                ->where('marketplace_listings.status', 'active')
                ->where('marketplace_sellers.status', 'active')
                ->first([
                    'marketplace_listings.marketplace_seller_id',
                    'marketplace_listings.seller_commission_bps',
                    'marketplace_sellers.user_id',
                ]);

            if (! $listing || (int) $listing->seller_commission_bps <= 0) {
                continue;
            }

            $exists = DB::table('commission_accruals')
                ->where('order_item_id', $item->id)
                ->where(
                    'marketplace_seller_id',
                    $listing->marketplace_seller_id
                )
                ->exists();

            if ($exists) {
                continue;
            }

            $amount = intdiv(
                ((int) $item->line_total_minor
                    * (int) $listing->seller_commission_bps) + 5000,
                10000
            );

            if ($amount <= 0) {
                continue;
            }

            DB::table('commission_accruals')->insert([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'marketplace_seller_id' => $listing->marketplace_seller_id,
                'beneficiary_user_id' => $listing->user_id,
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'rate_bps' => $listing->seller_commission_bps,
                'status' => 'accrued',
                'accrued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}