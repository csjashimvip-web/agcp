<?php
namespace Modules\Payments\Domain\Events;

use Modules\Shared\Domain\Events\AbstractDomainEvent;

final class PaymentIntentCreated extends AbstractDomainEvent
{
    public function __construct(private readonly array $data) { parent::__construct(); }
    public function eventName(): string { return 'payments.intent.created'; }
    public function payload(): array { return $this->data; }
}
