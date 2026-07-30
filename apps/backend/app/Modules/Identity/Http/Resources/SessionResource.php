<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device' => $this->device?->name ?? 'Unknown device',
            'platform' => $this->device?->platform,
            'browser' => $this->device?->browser,
            'ip_address' => $this->ip_address,
            'current' => $request->attributes->get('auth_session_id') === $this->id,
            'authenticated_at' => $this->authenticated_at?->toAtomString(),
            'last_active_at' => $this->last_active_at?->toAtomString(),
            'revoked_at' => $this->revoked_at?->toAtomString(),
        ];
    }
}
