<?php

namespace Modules\Identity\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Infrastructure\Models\AuthSession;
use Modules\Tenancy\Application\TenantContext;

class RecordSuccessfulLogout
{
    public function __construct(
        private readonly Request $request,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User || ! $this->request->hasSession()) {
            return;
        }

        $hash = hash_hmac('sha256', $this->request->session()->getId(), (string) config('app.key'));
        AuthSession::query()->where('user_id', $event->user->id)->where('session_hash', $hash)->update(['revoked_at' => now()]);

        $this->audit->record(
            action: 'identity.logout.succeeded',
            subjectType: User::class,
            subjectId: $event->user->id,
            tenantId: $this->tenantContext->id(),
            actorType: User::class,
            actorId: $event->user->id,
        );
    }
}
