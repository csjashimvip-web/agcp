<?php
namespace Modules\Commerce\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Commerce\Infrastructure\Models\OrderStatusHistory;
use Modules\Wallet\Application\Services\LedgerService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
final class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
        private readonly AuditLogger $audit,
    ) {}

    public function cancel(Order $order, User $actor, string $note = 'Customer canceled order.'): Order
    {
        return DB::transaction(function () use ($order, $actor, $note): Order {
            $locked = Order::query()->with(['wallet.account','items','supplierOrders'])->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['order'=>'Only pending or confirmed orders can be canceled.']);
            }
            if ($locked->supplierOrders->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'Automatic supplier fulfillment has started. This order can no longer be canceled from the commerce workflow.',
                ]);
            }
            $this->inventory->release($locked);
            if ($locked->payment_status === 'paid') {
                $revenue = $this->wallets->systemAccount($locked->tenant_id, $locked->currency, 'revenue:commerce-sales', 'Commerce sales revenue', AccountType::Revenue, LedgerDirection::Credit);
                $this->ledger->post(
                    tenantId:$locked->tenant_id,eventType:'order.refunded',description:'Wallet refund for canceled order '.$locked->number,
                    entries:[
                        ['account_id'=>$revenue->id,'direction'=>LedgerDirection::Debit,'amount_minor'=>$locked->total_minor],
                        ['account_id'=>$locked->wallet->ledger_account_id,'direction'=>LedgerDirection::Credit,'amount_minor'=>$locked->total_minor],
                    ],referenceType:Order::class,referenceId:$locked->id,metadata:['reason'=>$note],
                );
            }
            $from=$locked->status->value;
            $locked->forceFill(['status'=>OrderStatus::Canceled,'payment_status'=>'refunded','fulfillment_status'=>'canceled','canceled_at'=>now()])->save();
            $locked->items()->update(['status'=>'canceled']);
            OrderStatusHistory::query()->create(['order_id'=>$locked->id,'actor_id'=>$actor->id,'from_status'=>$from,'to_status'=>'canceled','note'=>$note]);
            $this->audit->record('commerce.order.canceled', Order::class, $locked->id, ['note'=>$note], ['from_status'=>$from], $locked->tenant_id, User::class, $actor->id);
            return $locked->fresh(['items','statusHistory']);
        }, 5);
    }

    public function transition(Order $order, User $actor, OrderStatus $to, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $actor, $to, $note): Order {
            $locked=Order::query()->with(['items','supplierOrders'])->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->supplierOrders->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'Supplier-managed orders are transitioned automatically by the supplier engine.',
                ]);
            }
            $allowed=[
                'confirmed'=>['processing','canceled'],
                'processing'=>['completed'],
                'pending'=>['confirmed','canceled'],
            ];
            $from=$locked->status->value;
            if (!in_array($to->value, $allowed[$from] ?? [], true)) throw ValidationException::withMessages(['status'=>'This order status transition is not allowed.']);
            if ($to===OrderStatus::Canceled) return $this->cancel($locked,$actor,$note ?? 'Administrator canceled order.');
            if ($to===OrderStatus::Completed) {
                $this->inventory->consume($locked);
                $locked->items()->update(['status'=>'completed']);
                $locked->fulfillment_status='fulfilled';
            } elseif ($to===OrderStatus::Processing) {
                $locked->items()->update(['status'=>'processing']);
                $locked->fulfillment_status='processing';
            }
            $locked->status=$to;
            $locked->save();
            OrderStatusHistory::query()->create(['order_id'=>$locked->id,'actor_id'=>$actor->id,'from_status'=>$from,'to_status'=>$to->value,'note'=>$note]);
            $this->audit->record('commerce.order.status_changed', Order::class, $locked->id, ['to_status'=>$to->value,'note'=>$note], ['from_status'=>$from], $locked->tenant_id, User::class, $actor->id);
            return $locked->fresh(['items','statusHistory']);
        }, 5);
    }
}
