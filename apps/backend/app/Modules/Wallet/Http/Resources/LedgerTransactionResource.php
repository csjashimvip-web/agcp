<?php
namespace Modules\Wallet\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class LedgerTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'description' => $this->description,
            'status' => $this->status,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'posted_at' => $this->posted_at?->toAtomString(),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'account_id' => $entry->ledger_account_id,
                'account_name' => $entry->account?->name,
                'direction' => $entry->direction->value,
                'amount_minor' => (int) $entry->amount_minor,
                'currency' => $entry->currency,
                'balance_after_minor' => (int) $entry->balance_after_minor,
            ])),
        ];
    }
}
