<?php
namespace Modules\SaaS\Domain\Enums;
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';

    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }
}
