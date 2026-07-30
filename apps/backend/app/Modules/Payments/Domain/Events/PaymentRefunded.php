<?php
namespace Modules\Payments\Domain\Events;

use Modules\Shared\Domain\Events\AbstractDomainEvent;

final class PaymentRefunded extends AbstractDomainEvent
{
    public function __construct(private readonly array $data) { parent::__construct(); }
    public function eventName(): string { return 'payments.refund.completed'; }
    public function payload(): array { return $this->data; }
}
