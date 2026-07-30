<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierRoutingDecision extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['candidate_scores' => 'array'];
    }

    public function supplierOrder(): BelongsTo { return $this->belongsTo(SupplierOrder::class); }
    public function selectedSupplier(): BelongsTo { return $this->belongsTo(SupplierAccount::class, 'selected_supplier_account_id'); }
    public function selectedService(): BelongsTo { return $this->belongsTo(SupplierService::class, 'selected_supplier_service_id'); }
}
