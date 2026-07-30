<?php

namespace Modules\Identity\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Modules\Audit\Application\AuditLogger;
use Modules\Tenancy\Application\TenantContext;

class RecordSuccessfulLogin
{
    public function __construct(
        private readonly Request $request,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->save();

        $this->audit->record(
            action: 'identity.login.succeeded',
            subjectType: User::class,
            subjectId: $event->user->id,
            tenantId: $this->tenantContext->id(),
            actorType: User::class,
            actorId: $event->user->id,
        );
    }
}
