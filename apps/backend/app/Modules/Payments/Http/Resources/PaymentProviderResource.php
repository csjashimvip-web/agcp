<?php
namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'code' => $this->code,
            'name' => $this->name,
            'mode' => $this->mode,
            'status' => $this->status,
            'priority' => (int) $this->priority,
            'currencies' => $this->currencies ?? [],
            'minimum_amount_minor' => (int) $this->minimum_amount_minor,
            'maximum_amount_minor' => (int) $this->maximum_amount_minor,
            'fee_basis_points' => (int) $this->fee_basis_points,
            'fee_fixed_minor' => (int) $this->fee_fixed_minor,
            'metadata' => $this->metadata ?? [],
            'webhook_path' => '/api/v1/payments/webhooks/'.rawurlencode((string) $this->provider).'/'.rawurlencode((string) $this->code),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
