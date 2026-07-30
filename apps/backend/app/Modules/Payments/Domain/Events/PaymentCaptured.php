<?php
namespace Modules\Payments\Domain\Events;

use Modules\Shared\Domain\Events\AbstractDomainEvent;

final class PaymentCaptured extends AbstractDomainEvent
{
    public function __construct(private readonly array $data) { parent::__construct(); }
    public function eventName(): string { return 'payments.intent.captured'; }
    public function payload(): array { return $this->data; }
}
