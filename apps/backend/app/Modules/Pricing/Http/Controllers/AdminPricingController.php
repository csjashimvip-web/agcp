<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminPricingController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'tiers' => DB::table('reseller_tiers')
                    ->where('tenant_id', $tenant->id())
                    ->orderBy('priority')
                    ->get(),
                'tier_memberships' => DB::table('reseller_tier_memberships')
                    ->join('users', 'users.id', '=', 'reseller_tier_memberships.user_id')
                    ->join('reseller_tiers', 'reseller_tiers.id', '=', 'reseller_tier_memberships.reseller_tier_id')
                    ->where('reseller_tier_memberships.tenant_id', $tenant->id())
                    ->get([
                        'reseller_tier_memberships.id',
                        'reseller_tier_memberships.status',
                        'users.name as user_name',
                        'users.email as user_email',
                        'reseller_tiers.name as tier_name',
                    ]),
                'coupons' => DB::table('coupons')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->get(),
                'tax_rules' => DB::table('tax_rules')
                    ->where('tenant_id', $tenant->id())
                    ->orderBy('priority')
                    ->get(),
            ],
        ]);
    }

    public function createTier(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:96'],
            'default_discount_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $exists = DB::table('reseller_tiers')
            ->where('tenant_id', $tenant->id())
            ->where('slug', $slug)
            ->exists();

        abort_if($exists, 422, 'Tier slug already exists.');

        $id = DB::table('reseller_tiers')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'slug' => $slug,
            'default_discount_bps' => $validated['default_discount_bps'],
            'priority' => $validated['priority'] ?? 100,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.tier.created',
            'reseller_tier',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('reseller_tiers')->where('id', $id)->first(),
        ], 201);
    }

    public function assignTier(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'user_email' => ['required', 'email'],
            'tier_id' => [
                'required',
                'integer',
                Rule::exists('reseller_tiers', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
        ]);

        $user = DB::table('users')
            ->where('email', $validated['user_email'])
            ->first();

        abort_unless($user, 404, 'User not found.');

        $membership = DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($membership, 422, 'User is not an active tenant member.');

        DB::table('reseller_tier_memberships')->updateOrInsert(
            [
                'tenant_id' => $tenant->id(),
                'user_id' => $user->id,
            ],
            [
                'reseller_tier_id' => $validated['tier_id'],
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.tier.assigned',
            'user',
            $user->id,
            ['tier_id' => $validated['tier_id']],
        );

        return response()->json(['data' => ['assigned' => true]]);
    }

    public function setTierPrice(
        Request $request,
        int $tierId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $tier = DB::table('reseller_tiers')
            ->where('tenant_id', $tenant->id())
            ->where('id', $tierId)
            ->first();

        abort_unless($tier, 404);

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'fixed_price_minor' => ['nullable', 'integer', 'min:0'],
            'discount_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        abort_if(
            $validated['fixed_price_minor'] === null
                && $validated['discount_bps'] === null,
            422,
            'Provide fixed_price_minor or discount_bps.'
        );

        DB::table('reseller_tier_prices')->updateOrInsert(
            [
                'reseller_tier_id' => $tierId,
                'product_id' => $validated['product_id'],
            ],
            [
                'fixed_price_minor' => $validated['fixed_price_minor'],
                'discount_bps' => $validated['discount_bps'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.tier_price.updated',
            'reseller_tier',
            $tierId,
            $validated,
        );

        return response()->json(['data' => ['saved' => true]]);
    }

    public function createCoupon(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'min_subtotal_minor' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $code = strtoupper(trim($validated['code']));

        $exists = DB::table('coupons')
            ->where('tenant_id', $tenant->id())
            ->where('code', $code)
            ->exists();

        abort_if($exists, 422, 'Coupon code already exists.');

        if ($validated['type'] === 'fixed') {
            abort_if(
                ! isset($validated['amount_minor']),
                422,
                'Fixed coupon requires amount_minor.'
            );
        }

        if ($validated['type'] === 'percent') {
            abort_if(
                ! isset($validated['rate_bps']),
                422,
                'Percent coupon requires rate_bps.'
            );
        }

        $id = DB::table('coupons')->insertGetId([
            'tenant_id' => $tenant->id(),
            'code' => $code,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'amount_minor' => $validated['amount_minor'] ?? null,
            'rate_bps' => $validated['rate_bps'] ?? null,
            'min_subtotal_minor' => $validated['min_subtotal_minor'] ?? 0,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'per_user_limit' => $validated['per_user_limit'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.coupon.created',
            'coupon',
            $id,
            ['code' => $code],
        );

        return response()->json([
            'data' => DB::table('coupons')->where('id', $id)->first(),
        ], 201);
    }

    public function createTaxRule(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $id = DB::table('tax_rules')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'rate_bps' => $validated['rate_bps'],
            'priority' => $validated['priority'] ?? 100,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'pricing.tax_rule.created',
            'tax_rule',
            $id,
            $validated,
        );

        return response()->json([
            'data' => DB::table('tax_rules')->where('id', $id)->first(),
        ], 201);
    }
}