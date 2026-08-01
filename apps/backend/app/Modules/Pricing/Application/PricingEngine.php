<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Catalog\Domain\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PricingEngine
{
    /**
     * @param array<int, array{product_id:int,quantity:int}> $items
     * @return array<string,mixed>
     */
    public function quote(
        int $tenantId,
        int $userId,
        array $items,
        ?string $couponCode = null,
    ): array {
        $tier = $this->activeTier($tenantId, $userId);
        $lines = [];
        $subtotal = 0;

        foreach ($items as $line) {
            $quantity = max(1, (int) $line['quantity']);

            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->findOrFail((int) $line['product_id']);

            $unitPrice = $this->tierPrice(
                $product,
                $tier?->id,
                $tier?->default_discount_bps ?? 0,
            );

            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'unit_price_minor' => $unitPrice,
                'line_total_minor' => $lineTotal,
                'base_unit_price_minor' => (int) $product->price_minor,
            ];
        }

        $coupon = $this->resolveCoupon(
            $tenantId,
            $userId,
            $couponCode,
            $subtotal,
        );

        $couponDiscount = $coupon
            ? $this->couponDiscount($coupon, $subtotal)
            : 0;

        $ruleResult = $this->advancedRules(
            $tenantId,
            $tier?->id,
            $subtotal,
        );

        $discount = min(
            $subtotal + $ruleResult['surcharge_minor'],
            $couponDiscount + $ruleResult['discount_minor']
        );

        $taxable = max(
            0,
            $subtotal + $ruleResult['surcharge_minor'] - $discount
        );

        $taxRule = $this->activeTaxRule($tenantId);
        $tax = $taxRule
            ? $this->basisPoints($taxable, (int) $taxRule->rate_bps)
            : 0;

        return [
            'lines' => $lines,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'coupon_discount_minor' => $couponDiscount,
            'rule_discount_minor' => $ruleResult['discount_minor'],
            'surcharge_minor' => $ruleResult['surcharge_minor'],
            'tax_minor' => $tax,
            'total_minor' => max(0, $taxable + $tax),
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'tier_id' => $tier?->id,
            'tier_name' => $tier?->name,
            'pricing_rules' => $ruleResult['rules'],
        ];
    }

    private function activeTier(int $tenantId, int $userId): ?object
    {
        return DB::table('reseller_tier_memberships')
            ->join(
                'reseller_tiers',
                'reseller_tiers.id',
                '=',
                'reseller_tier_memberships.reseller_tier_id'
            )
            ->where('reseller_tier_memberships.tenant_id', $tenantId)
            ->where('reseller_tier_memberships.user_id', $userId)
            ->where('reseller_tier_memberships.status', 'active')
            ->where('reseller_tiers.status', 'active')
            ->where(function ($query): void {
                $query->whereNull('reseller_tier_memberships.starts_at')
                    ->orWhere('reseller_tier_memberships.starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('reseller_tier_memberships.ends_at')
                    ->orWhere('reseller_tier_memberships.ends_at', '>=', now());
            })
            ->orderBy('reseller_tiers.priority')
            ->first([
                'reseller_tiers.id',
                'reseller_tiers.name',
                'reseller_tiers.default_discount_bps',
            ]);
    }

    private function tierPrice(
        Product $product,
        ?int $tierId,
        int $defaultDiscountBps,
    ): int {
        if (! $tierId) {
            return (int) $product->price_minor;
        }

        $override = DB::table('reseller_tier_prices')
            ->where('reseller_tier_id', $tierId)
            ->where('product_id', $product->id)
            ->first();

        if ($override?->fixed_price_minor !== null) {
            return max(0, (int) $override->fixed_price_minor);
        }

        $discountBps = $override?->discount_bps !== null
            ? (int) $override->discount_bps
            : $defaultDiscountBps;

        return max(
            0,
            (int) $product->price_minor
                - $this->basisPoints(
                    (int) $product->price_minor,
                    $discountBps
                )
        );
    }

    private function resolveCoupon(
        int $tenantId,
        int $userId,
        ?string $couponCode,
        int $subtotalMinor,
    ): ?object {
        $code = strtoupper(trim((string) $couponCode));

        if ($code === '') {
            return null;
        }

        $coupon = DB::table('coupons')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Coupon is invalid or inactive.'],
            ]);
        }

        if ($subtotalMinor < (int) $coupon->min_subtotal_minor) {
            throw ValidationException::withMessages([
                'coupon_code' => [
                    'Order subtotal does not meet the coupon minimum.',
                ],
            ]);
        }

        if ($coupon->usage_limit !== null) {
            $used = DB::table('coupon_redemptions')
                ->where('coupon_id', $coupon->id)
                ->count();

            if ($used >= (int) $coupon->usage_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Coupon usage limit has been reached.'],
                ]);
            }
        }

        if ($coupon->per_user_limit !== null) {
            $usedByUser = DB::table('coupon_redemptions')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($usedByUser >= (int) $coupon->per_user_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => [
                        'Coupon usage limit for this account has been reached.',
                    ],
                ]);
            }
        }

        return $coupon;
    }

    private function couponDiscount(object $coupon, int $subtotalMinor): int
    {
        $discount = match ((string) $coupon->type) {
            'fixed' => (int) ($coupon->amount_minor ?? 0),
            'percent' => $this->basisPoints(
                $subtotalMinor,
                (int) ($coupon->rate_bps ?? 0)
            ),
            default => 0,
        };

        return min($subtotalMinor, max(0, $discount));
    }

    /**
     * @return array{
     *   discount_minor:int,
     *   surcharge_minor:int,
     *   rules:array<int,array<string,mixed>>
     * }
     */
    private function advancedRules(
        int $tenantId,
        ?int $tierId,
        int $subtotalMinor,
    ): array {
        $rules = DB::table('pricing_rules')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('min_subtotal_minor', '<=', $subtotalMinor)
            ->where(function ($query) use ($subtotalMinor): void {
                $query->whereNull('max_subtotal_minor')
                    ->orWhere('max_subtotal_minor', '>=', $subtotalMinor);
            })
            ->where(function ($query) use ($tierId): void {
                $query->whereNull('reseller_tier_id');

                if ($tierId) {
                    $query->orWhere('reseller_tier_id', $tierId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority')
            ->get();

        $discount = 0;
        $surcharge = 0;
        $applied = [];

        foreach ($rules as $rule) {
            $value = $rule->value_type === 'percent'
                ? $this->basisPoints(
                    $subtotalMinor,
                    (int) ($rule->rate_bps ?? 0)
                )
                : (int) ($rule->amount_minor ?? 0);

            $value = max(0, $value);

            if ($rule->effect === 'discount') {
                $discount += $value;
            } elseif ($rule->effect === 'surcharge') {
                $surcharge += $value;
            } else {
                continue;
            }

            $applied[] = [
                'id' => (int) $rule->id,
                'code' => (string) $rule->code,
                'effect' => (string) $rule->effect,
                'value_minor' => $value,
            ];

            if (! $rule->stackable) {
                break;
            }
        }

        return [
            'discount_minor' => min($subtotalMinor, $discount),
            'surcharge_minor' => $surcharge,
            'rules' => $applied,
        ];
    }

    private function activeTaxRule(int $tenantId): ?object
    {
        return DB::table('tax_rules')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority')
            ->first();
    }

    private function basisPoints(int $amountMinor, int $basisPoints): int
    {
        $basisPoints = max(0, min($basisPoints, 10000));

        return intdiv(
            ($amountMinor * $basisPoints) + 5000,
            10000
        );
    }
}