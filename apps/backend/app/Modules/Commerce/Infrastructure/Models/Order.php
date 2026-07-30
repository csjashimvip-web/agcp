<?php
namespace Modules\Commerce\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;
use Modules\Wallet\Infrastructure\Models\Wallet;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
final class Order extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'status'=>OrderStatus::class,'subtotal_minor'=>'integer','discount_minor'=>'integer','total_minor'=>'integer',
            'placed_at'=>'immutable_datetime','canceled_at'=>'immutable_datetime','metadata'=>'array',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function ledgerTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function statusHistory(): HasMany { return $this->hasMany(OrderStatusHistory::class); }
    public function reservations(): HasMany { return $this->hasMany(InventoryReservation::class); }
    public function supplierOrders(): HasMany { return $this->hasMany(SupplierOrder::class); }
}
