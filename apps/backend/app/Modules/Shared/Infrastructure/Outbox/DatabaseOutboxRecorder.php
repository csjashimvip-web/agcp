<?php
namespace Modules\Shared\Infrastructure\Outbox;
use Modules\Shared\Application\Outbox\OutboxRecorder;
use Modules\Shared\Domain\Contracts\DomainEvent;
use Modules\Shared\Infrastructure\Models\OutboxMessage;
class DatabaseOutboxRecorder implements OutboxRecorder
{
    public function record(DomainEvent $event, ?string $tenantId = null, array $metadata = []): void
    {
        OutboxMessage::query()->create([
            'id'=>$event->eventId(),'tenant_id'=>$tenantId,'event_name'=>$event->eventName(),
            'schema_version'=>$event->schemaVersion(),'payload'=>$event->payload(),'metadata'=>$metadata,
            'occurred_at'=>$event->occurredAt(),'available_at'=>now(),'attempts'=>0,
        ]);
    }
}
