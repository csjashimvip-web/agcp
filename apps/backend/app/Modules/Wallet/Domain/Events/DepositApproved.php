<?php
namespace Modules\Wallet\Domain\Events;
use Modules\Shared\Domain\Events\AbstractDomainEvent;
final class DepositApproved extends AbstractDomainEvent
{
    public function __construct(private readonly array $data) { parent::__construct(); }
    public function eventName(): string { return 'wallet.deposit.approved'; }
    public function payload(): array { return $this->data; }
}
