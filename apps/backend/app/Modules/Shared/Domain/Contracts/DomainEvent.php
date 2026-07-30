<?php
namespace Modules\Shared\Domain\Contracts;
use Carbon\CarbonImmutable;
interface DomainEvent
{
    public function eventId(): string;
    public function eventName(): string;
    public function occurredAt(): CarbonImmutable;
    public function payload(): array;
    public function schemaVersion(): int;
}
