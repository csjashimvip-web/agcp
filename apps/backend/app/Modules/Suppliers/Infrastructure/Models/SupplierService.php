<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;

final class SupplierService extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'estimated_seconds' => 'integer',
            'priority' => 'integer',
            'enabled' => 'boolean',
            'max_retries' => 'integer',
            'field_map' => 'array',
            'metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierAccount::class, 'supplier_account_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }
}
