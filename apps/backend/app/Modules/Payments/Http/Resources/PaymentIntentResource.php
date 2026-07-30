<?php
namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['providerAccount', 'deposit', 'refunds']);
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider_payment_id' => $this->provider_payment_id,
            'provider' => $this->providerAccount ? [
                'id' => $this->providerAccount->id,
                'code' => $this->providerAccount->code,
                'name' => $this->providerAccount->name,
                'provider' => $this->providerAccount->provider,
                'mode' => $this->providerAccount->mode,
            ] : null,
            'wallet_id' => $this->wallet_id,
            'amount_minor' => (int) $this->amount_minor,
            'fee_minor' => (int) $this->fee_minor,
            'total_minor' => (int) $this->total_minor,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'checkout_url' => $this->checkout_url,
            'expires_at' => $this->expires_at?->toAtomString(),
            'completed_at' => $this->completed_at?->toAtomString(),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'deposit_id' => $this->deposit?->id,
            'ledger_transaction_id' => $this->deposit?->ledger_transaction_id,
            'fee_ledger_transaction_id' => $this->fee_ledger_transaction_id,
            'refunded_minor' => (int) $this->refunds->filter(fn ($refund) => $refund->status->value === 'completed')->sum('amount_minor'),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
