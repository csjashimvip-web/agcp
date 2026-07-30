<?php
namespace Modules\Commerce\Domain\Events;
use Modules\Shared\Domain\Events\AbstractDomainEvent;
final class OrderPlaced extends AbstractDomainEvent
{
    public function name(): string { return 'commerce.order.placed'; }
}
