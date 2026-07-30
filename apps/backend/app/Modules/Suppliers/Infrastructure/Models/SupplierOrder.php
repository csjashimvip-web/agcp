<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Commerce\Infrastructure\Models\OrderItem;
use Modules\Suppliers\Domain\Enums\SupplierOrderStatus;
use Modules\Wallet\Infrastructure\Models\LedgerTransaction;

final class SupplierOrder extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SupplierOrderStatus::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'result_payload' => 'array',
            'queued_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'next_poll_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(SupplierAccount::class, 'supplier_account_id'); }
    public function service(): BelongsTo { return $this->belongsTo(SupplierService::class, 'supplier_service_id'); }
    public function routingProfile(): BelongsTo { return $this->belongsTo(SupplierRoutingProfile::class, 'routing_profile_id'); }
    public function refundTransaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class, 'refund_ledger_transaction_id'); }
    public function attemptLogs(): HasMany { return $this->hasMany(SupplierAttempt::class); }
    public function decisions(): HasMany { return $this->hasMany(SupplierRoutingDecision::class); }
}
