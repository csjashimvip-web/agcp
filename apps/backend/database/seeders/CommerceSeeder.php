<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Infrastructure\Models\CatalogCategory;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\InventoryLevel;
use Modules\Commerce\Infrastructure\Models\InventoryLocation;
use Modules\Commerce\Infrastructure\Models\PriceList;
use Modules\Tenancy\Infrastructure\Models\Tenant;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'araabi-global')->firstOrFail();

        DB::transaction(function () use ($tenant): void {
            $services = CatalogCategory::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'digital-services'],
                ['name' => 'Digital Services', 'description' => 'Secure digital and server-assisted services.', 'status' => 'active', 'sort_order' => 10],
            );
            $products = CatalogCategory::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'products'],
                ['name' => 'Products', 'description' => 'Physical and digital products.', 'status' => 'active', 'sort_order' => 20],
            );

            $retail = PriceList::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'retail', 'currency' => $tenant->default_currency],
                ['name' => 'Retail pricing', 'priority' => 100, 'status' => 'active'],
            );
            $location = InventoryLocation::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'MAIN'],
                ['name' => 'Main inventory', 'status' => 'active'],
            );

            $definitions = [
                [
                    'category' => $services,
                    'item' => [
                        'type' => 'service', 'name' => 'IMEI Status Check', 'slug' => 'imei-status-check', 'sku' => 'SRV-IMEI-CHECK',
                        'summary' => 'Submit an IMEI number for a structured device-status report.',
                        'description' => 'A manual fulfillment service ready for supplier automation in Phase 5.',
                        'status' => 'active', 'fulfillment_mode' => 'manual', 'inventory_tracking' => false, 'allow_backorder' => true,
                        'service_schema' => ['fields' => [['name' => 'imei', 'label' => 'IMEI number', 'type' => 'text', 'required' => true]]],
                    ],
                    'variant' => ['name' => 'Standard report', 'sku' => 'SRV-IMEI-CHECK-STD'],
                    'price' => 500,
                    'stock' => null,
                ],
                [
                    'category' => $products,
                    'item' => [
                        'type' => 'digital', 'name' => 'AGCP Starter License', 'slug' => 'agcp-starter-license', 'sku' => 'DIG-AGCP-STARTER',
                        'summary' => 'A demonstration digital-license catalog item.',
                        'description' => 'Delivered manually in Phase 4; automated delivery can be connected later.',
                        'status' => 'active', 'fulfillment_mode' => 'manual', 'inventory_tracking' => false, 'allow_backorder' => true,
                    ],
                    'variant' => ['name' => 'One year', 'sku' => 'DIG-AGCP-STARTER-1Y'],
                    'price' => 2500,
                    'stock' => null,
                ],
                [
                    'category' => $products,
                    'item' => [
                        'type' => 'physical', 'name' => 'Premium USB-C Cable', 'slug' => 'premium-usb-c-cable', 'sku' => 'PHY-CABLE-USBC',
                        'summary' => 'A demonstration inventory-tracked physical product.',
                        'description' => 'Used to validate stock reservation and order completion flows.',
                        'status' => 'active', 'fulfillment_mode' => 'manual', 'inventory_tracking' => true, 'allow_backorder' => false,
                    ],
                    'variant' => ['name' => '1 metre · Black', 'sku' => 'PHY-CABLE-USBC-1M-BLK', 'attributes' => ['length' => '1m', 'color' => 'black']],
                    'price' => 1299,
                    'stock' => 50,
                ],
            ];

            foreach ($definitions as $definition) {
                $item = CatalogItem::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $definition['item']['slug']],
                    array_merge($definition['item'], ['category_id' => $definition['category']->id, 'published_at' => now()]),
                );
                $variant = CatalogVariant::query()->updateOrCreate(
                    ['catalog_item_id' => $item->id, 'sku' => $definition['variant']['sku']],
                    array_merge($definition['variant'], ['status' => 'active', 'is_default' => true]),
                );
                CatalogPrice::query()->updateOrCreate(
                    ['price_list_id' => $retail->id, 'catalog_variant_id' => $variant->id, 'min_quantity' => 1],
                    ['amount_minor' => $definition['price']],
                );
                if ($definition['stock'] !== null) {
                    InventoryLevel::query()->updateOrCreate(
                        ['inventory_location_id' => $location->id, 'catalog_variant_id' => $variant->id],
                        ['on_hand' => $definition['stock'], 'reserved' => 0, 'safety_stock' => 2],
                    );
                }
            }
        }, 5);
    }
}
