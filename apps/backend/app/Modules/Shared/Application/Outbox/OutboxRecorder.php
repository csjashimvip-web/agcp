<?php
namespace Modules\Shared\Application\Outbox;
use Modules\Shared\Domain\Contracts\DomainEvent;
interface OutboxRecorder
{
    public function record(DomainEvent $event, ?string $tenantId = null, array $metadata = []): void;
}
