<?php
namespace Modules\Commerce\Domain\Events;

use Modules\Shared\Domain\Events\AbstractDomainEvent;

final class OrderPlaced extends AbstractDomainEvent
{
    public function __construct(private readonly array $data)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'commerce.order.placed';
    }

    public function payload(): array
    {
        return $this->data;
    }
}
