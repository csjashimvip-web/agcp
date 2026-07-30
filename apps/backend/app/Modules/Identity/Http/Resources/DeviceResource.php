<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'browser' => $this->browser,
            'last_ip' => $this->last_ip,
            'trusted' => $this->trusted_at !== null,
            'trusted_at' => $this->trusted_at?->toAtomString(),
            'first_seen_at' => $this->first_seen_at?->toAtomString(),
            'last_seen_at' => $this->last_seen_at?->toAtomString(),
        ];
    }
}
