<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminMarketplaceController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'sellers' => DB::table('marketplace_sellers')
                    ->join('users', 'users.id', '=', 'marketplace_sellers.user_id')
                    ->where('marketplace_sellers.tenant_id', $tenant->id())
                    ->orderBy('marketplace_sellers.display_name')
                    ->get([
                        'marketplace_sellers.id',
                        'marketplace_sellers.display_name',
                        'marketplace_sellers.status',
                        'users.name as user_name',
                        'users.email as user_email',
                    ]),
                'listings' => DB::table('marketplace_listings')
                    ->join(
                        'marketplace_sellers',
                        'marketplace_sellers.id',
                        '=',
                        'marketplace_listings.marketplace_seller_id'
                    )
                    ->join(
                        'products',
                        'products.id',
                        '=',
                        'marketplace_listings.product_id'
                    )
                    ->where('marketplace_listings.tenant_id', $tenant->id())
                    ->orderBy('products.name')
                    ->get([
                        'marketplace_listings.id',
                        'marketplace_listings.status',
                        'marketplace_listings.seller_commission_bps',
                        'marketplace_sellers.display_name as seller_name',
                        'products.id as product_id',
                        'products.sku',
                        'products.name as product_name',
                    ]),
                'accruals' => DB::table('commission_accruals')
                    ->join(
                        'marketplace_sellers',
                        'marketplace_sellers.id',
                        '=',
                        'commission_accruals.marketplace_seller_id'
                    )
                    ->join('orders', 'orders.id', '=', 'commission_accruals.order_id')
                    ->where('commission_accruals.tenant_id', $tenant->id())
                    ->orderByDesc('commission_accruals.id')
                    ->limit(100)
                    ->get([
                        'commission_accruals.id',
                        'commission_accruals.amount_minor',
                        'commission_accruals.currency',
                        'commission_accruals.rate_bps',
                        'commission_accruals.status',
                        'commission_accruals.accrued_at',
                        'marketplace_sellers.display_name as seller_name',
                        'orders.order_number',
                    ]),
            ],
        ]);
    }

    public function createSeller(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'user_email' => ['required', 'email'],
            'display_name' => ['required', 'string', 'max:255'],
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

        abort_unless($membership, 422, 'User must be an active tenant member.');

        $id = DB::table('marketplace_sellers')->updateOrInsert(
            [
                'tenant_id' => $tenant->id(),
                'user_id' => $user->id,
            ],
            [
                'display_name' => $validated['display_name'],
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $seller = DB::table('marketplace_sellers')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $user->id)
            ->first();

        $audit->record(
            $request,
            $tenant->id(),
            'marketplace.seller.saved',
            'marketplace_seller',
            $seller?->id,
            $validated,
        );

        return response()->json(['data' => $seller], 201);
    }

    public function createListing(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'seller_id' => [
                'required',
                'integer',
                Rule::exists('marketplace_sellers', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'seller_commission_bps' => [
                'required',
                'integer',
                'min:0',
                'max:10000',
            ],
        ]);

        $existing = DB::table('marketplace_listings')
            ->where('tenant_id', $tenant->id())
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            DB::table('marketplace_listings')
                ->where('id', $existing->id)
                ->update([
                    'marketplace_seller_id' => $validated['seller_id'],
                    'seller_commission_bps' => $validated['seller_commission_bps'],
                    'status' => 'active',
                    'updated_at' => now(),
                ]);

            $listingId = $existing->id;
        } else {
            $listingId = DB::table('marketplace_listings')->insertGetId([
                'tenant_id' => $tenant->id(),
                'marketplace_seller_id' => $validated['seller_id'],
                'product_id' => $validated['product_id'],
                'seller_commission_bps' => $validated['seller_commission_bps'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $audit->record(
            $request,
            $tenant->id(),
            'marketplace.listing.saved',
            'marketplace_listing',
            $listingId,
            $validated,
        );

        return response()->json([
            'data' => DB::table('marketplace_listings')
                ->where('id', $listingId)
                ->first(),
        ], 201);
    }
}