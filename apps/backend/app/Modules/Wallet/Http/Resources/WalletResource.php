<?php
namespace Modules\Wallet\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('account');
        $held = $this->activeHoldMinor();
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'currency' => $this->currency,
            'status' => $this->status,
            'balance_minor' => (int) $this->account->balance_minor,
            'held_minor' => $held,
            'available_minor' => (int) $this->account->balance_minor - $held,
            'balance' => number_format($this->account->balance_minor / 100, 2, '.', ''),
            'available' => number_format(((int) $this->account->balance_minor - $held) / 100, 2, '.', ''),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
