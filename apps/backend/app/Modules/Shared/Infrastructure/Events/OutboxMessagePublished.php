<?php
namespace Modules\Shared\Infrastructure\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class OutboxMessagePublished
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventName,
        public readonly int $schemaVersion,
        public readonly array $payload,
        public readonly array $metadata,
        public readonly ?string $tenantId,
    ) {}
}
