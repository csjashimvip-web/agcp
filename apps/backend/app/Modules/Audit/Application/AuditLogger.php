<?php
namespace Modules\Audit\Application;
interface AuditLogger
{
    public function record(string $action, string $subjectType, ?string $subjectId = null, array $context = [], array $changes = [], ?string $tenantId = null, ?string $actorType = null, ?string $actorId = null): void;
}
