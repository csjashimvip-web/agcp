<?php
namespace Modules\Commerce\Application\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Commerce\Domain\Events\OrderPlaced;
use Modules\Commerce\Infrastructure\Models\Cart;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Commerce\Infrastructure\Models\OrderItem;
use Modules\Commerce\Infrastructure\Models\OrderStatusHistory;
use Modules\Fraud\Application\Services\FraudRiskEngine;
use Modules\Fraud\Domain\Enums\FraudDecision;
use Modules\Fraud\Infrastructure\Models\FraudRiskAssessment;
use Modules\Rules\Application\Services\DynamicPricingService;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Wallet\Application\Services\LedgerService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Infrastructure\Models\Wallet;
final class CheckoutService
{
    public function __construct(
        private readonly DynamicPricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
        private readonly OutboxRecorder $outbox,
        private readonly AuditLogger $audit,
        private readonly FraudRiskEngine $fraud,
    ) {}

    public function checkout(User $user,string $tenantId,string $cartId,string $walletId,?string $idempotencyKey,array $riskContext=[]): Order
    {
        $hash=$idempotencyKey?hash('sha256',$idempotencyKey):null;
        if($hash!==null){$existing=Order::query()->where(['tenant_id'=>$tenantId,'user_id'=>$user->id,'idempotency_key_hash'=>$hash])->first();if($existing)return $existing->load(['items','statusHistory']);}
        return DB::transaction(function() use($user,$tenantId,$cartId,$walletId,$idempotencyKey,$hash,$riskContext): Order {
            $cart=Cart::query()->with(['items.variant.item'])->whereKey($cartId)->where(['tenant_id'=>$tenantId,'user_id'=>$user->id,'status'=>'active'])->lockForUpdate()->firstOrFail();
            if($cart->items->isEmpty())throw ValidationException::withMessages(['cart'=>'Your cart is empty.']);
            $wallet=Wallet::query()->with('account')->whereKey($walletId)->where(['tenant_id'=>$tenantId,'owner_id'=>$user->id,'currency'=>$cart->currency,'status'=>'active'])->firstOrFail();
            $priced=[];$subtotal=0;$baseSubtotal=0;
            foreach($cart->items as $line){$quote=$this->pricing->quote($line->variant,$tenantId,$cart->currency,(int)$line->quantity,$user);$total=$quote->finalAmountMinor*(int)$line->quantity;$baseTotal=$quote->baseAmountMinor*(int)$line->quantity;$subtotal+=$total;$baseSubtotal+=$baseTotal;$priced[]=[$line,$quote,$total];}
            if($wallet->availableBalanceMinor()<$subtotal)throw ValidationException::withMessages(['wallet'=>'Insufficient available wallet balance.']);
            $risk=$this->fraud->assessCheckout($user,$tenantId,$subtotal,$cart->id,['currency'=>$cart->currency]+$riskContext);
            if($risk->decision===FraudDecision::Block)throw ValidationException::withMessages(['risk'=>'This checkout was blocked by the risk engine. Assessment: '.$risk->assessmentId]);
            $onHold=$risk->decision===FraudDecision::Review;
            $order=Order::query()->create([
                'tenant_id'=>$tenantId,'user_id'=>$user->id,'wallet_id'=>$wallet->id,'number'=>$this->number(),'status'=>'confirmed','payment_status'=>'paid','fulfillment_status'=>$onHold?'on_hold':'unfulfilled',
                'currency'=>$cart->currency,'subtotal_minor'=>$baseSubtotal,'discount_minor'=>max(0,$baseSubtotal-$subtotal),'surcharge_minor'=>max(0,$subtotal-$baseSubtotal),'total_minor'=>$subtotal,'idempotency_key_hash'=>$hash,'placed_at'=>now(),
                'metadata'=>['cart_id'=>$cart->id,'risk_assessment_id'=>$risk->assessmentId,'risk_score'=>$risk->score,'risk_level'=>$risk->level->value,'risk_decision'=>$risk->decision->value],
            ]);
            FraudRiskAssessment::query()->whereKey($risk->assessmentId)->update(['order_id'=>$order->id]);
            foreach($priced as[$line,$quote,$total]){
                OrderItem::query()->create([
                    'order_id'=>$order->id,'catalog_variant_id'=>$line->variant->id,'item_name'=>$line->variant->item->name,'variant_name'=>$line->variant->name,'sku'=>$line->variant->sku,'item_type'=>$line->variant->item->type->value,'quantity'=>$line->quantity,
                    'unit_price_minor'=>$quote->finalAmountMinor,'total_minor'=>$total,'status'=>$onHold?'on_hold':'pending','configuration'=>$line->configuration,
                    'metadata'=>['price_list_id'=>$quote->priceListId,'base_price_minor'=>$quote->baseAmountMinor,'adjustment_minor'=>$quote->adjustmentMinor,'pricing_rule_ids'=>$quote->matchedRuleIds,'pricing_breakdown'=>$quote->breakdown],
                ]);$this->inventory->reserve($order,$line->variant,(int)$line->quantity);
            }
            $revenue=$this->wallets->systemAccount($tenantId,$cart->currency,'revenue:commerce-sales','Commerce sales revenue',AccountType::Revenue,LedgerDirection::Credit);
            $transaction=$this->ledger->post(tenantId:$tenantId,eventType:'order.paid',description:'Wallet payment for order '.$order->number,entries:[['account_id'=>$wallet->ledger_account_id,'direction'=>LedgerDirection::Debit,'amount_minor'=>$subtotal],['account_id'=>$revenue->id,'direction'=>LedgerDirection::Credit,'amount_minor'=>$subtotal]],referenceType:Order::class,referenceId:$order->id,idempotencyKey:$idempotencyKey,metadata:['order_number'=>$order->number,'cart_id'=>$cart->id,'risk_assessment_id'=>$risk->assessmentId]);
            $order->forceFill(['ledger_transaction_id'=>$transaction->id])->save();
            OrderStatusHistory::query()->create(['order_id'=>$order->id,'actor_id'=>$user->id,'to_status'=>'confirmed','note'=>$onHold?'Order paid and held for fraud review.':'Order placed and paid from wallet.']);
            $cart->forceFill(['status'=>'converted'])->save();
            $payload=['order_id'=>$order->id,'number'=>$order->number,'user_id'=>$user->id,'total_minor'=>$subtotal,'currency'=>$cart->currency,'fulfillment_on_hold'=>$onHold];
            $this->outbox->record(new OrderPlaced($payload),$tenantId,['actor_id'=>$user->id]);
            $this->audit->record('commerce.order.placed',Order::class,$order->id,$payload,[],$tenantId,User::class,$user->id);
            return $order->fresh(['items','statusHistory','wallet.account']);
        },5);
    }
    private function number(): string{return 'AG-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));}
}
