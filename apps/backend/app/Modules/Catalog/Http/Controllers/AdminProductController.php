<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminProductController
{
    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'sku' => [
                'required',
                'string',
                'max:96',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'type' => ['required', Rule::in(['service', 'physical', 'digital'])],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'currency' => ['required', 'string', 'size:3'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'cost_minor' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $requestedSlug = $validated['slug'] ?? null;

        $product = Product::query()->create([
            ...$validated,
            'tenant_id' => $tenant->id(),
            'slug' => $requestedSlug
                ?: Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
            'currency' => strtoupper($validated['currency']),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'catalog.product.created',
            'product',
            $product->id,
            [
                'sku' => $product->sku,
                'name' => $product->name,
            ],
        );

        return response()->json([
            'data' => $product,
        ], 201);
    }

    public function update(
        Request $request,
        int $productId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $product = Product::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($productId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['draft', 'active', 'inactive']),
            ],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'cost_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $before = $product->only(array_keys($validated));

        $product->fill($validated)->save();

        $audit->record(
            $request,
            $tenant->id(),
            'catalog.product.updated',
            'product',
            $product->id,
            [
                'before' => $before,
                'after' => $product->only(array_keys($validated)),
            ],
        );

        return response()->json([
            'data' => $product->fresh(),
        ]);
    }
}