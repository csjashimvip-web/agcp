<?php
namespace Modules\Tenancy\Application;
use Modules\Tenancy\Infrastructure\Models\Tenant;
class TenantContext
{
    private ?Tenant $tenant = null;
    public function set(?Tenant $tenant): void { $this->tenant = $tenant; }
    public function clear(): void { $this->tenant = null; }
    public function tenant(): ?Tenant { return $this->tenant; }
    public function id(): ?string { return $this->tenant?->getKey(); }
    public function requireId(): string { return $this->id() ?? throw new \LogicException('Tenant context unavailable.'); }
}
