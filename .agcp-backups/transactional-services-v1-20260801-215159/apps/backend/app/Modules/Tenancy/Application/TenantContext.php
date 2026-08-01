<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Models\Tenant;
use RuntimeException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        if ($tenant->status !== 'active') {
            throw new RuntimeException('Tenant is not active.');
        }

        $this->tenant = $tenant;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('Tenant context has not been resolved.');
    }

    public function id(): int
    {
        return (int) $this->tenant()->getKey();
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}