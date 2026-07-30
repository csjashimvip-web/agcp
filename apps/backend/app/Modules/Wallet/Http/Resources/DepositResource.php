<?php
namespace Modules\Wallet\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class DepositResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]),
            'amount_minor' => (int) $this->amount_minor,
            'amount' => number_format($this->amount_minor / 100, 2, '.', ''),
            'currency' => $this->currency,
            'method' => $this->method,
            'status' => $this->status->value,
            'external_reference' => $this->external_reference,
            'customer_note' => $this->customer_note,
            'admin_note' => $request->routeIs('api.v1.admin.*') ? $this->admin_note : null,
            'ledger_transaction_id' => $this->ledger_transaction_id,
            'submitted_at' => $this->submitted_at?->toAtomString(),
            'reviewed_at' => $this->reviewed_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
