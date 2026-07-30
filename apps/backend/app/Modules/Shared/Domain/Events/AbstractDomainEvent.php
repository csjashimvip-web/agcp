<?php
namespace Modules\Shared\Domain\Events;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Contracts\DomainEvent;
abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly string $id;
    private readonly CarbonImmutable $at;
    public function __construct(?string $eventId = null, ?CarbonImmutable $occurredAt = null)
    {
        $this->id = $eventId ?? (string) Str::uuid();
        $this->at = $occurredAt ?? CarbonImmutable::now();
    }
    public function eventId(): string { return $this->id; }
    public function occurredAt(): CarbonImmutable { return $this->at; }
    public function schemaVersion(): int { return 1; }
}
