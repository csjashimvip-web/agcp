<?php
namespace Modules\Audit\Infrastructure;
use Illuminate\Http\Request;
use Modules\Audit\Application\AuditLogger;
use Modules\Audit\Infrastructure\Models\AuditEvent;
class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(private readonly Request $request) {}
    public function record(string $action, string $subjectType, ?string $subjectId = null, array $context = [], array $changes = [], ?string $tenantId = null, ?string $actorType = null, ?string $actorId = null): void
    {
        AuditEvent::query()->create([
            'tenant_id'=>$tenantId,'actor_type'=>$actorType,'actor_id'=>$actorId,'action'=>$action,
            'subject_type'=>$subjectType,'subject_id'=>$subjectId,'request_id'=>$this->request->attributes->get('request_id'),
            'ip_address'=>$this->request->ip(),'user_agent'=>mb_substr((string)$this->request->userAgent(),0,1000),
            'context'=>$context,'changes'=>$changes,'occurred_at'=>now(),
        ]);
    }
}
