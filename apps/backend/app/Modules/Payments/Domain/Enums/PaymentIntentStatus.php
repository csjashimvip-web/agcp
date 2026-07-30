<?php
namespace Modules\Payments\Domain\Enums;

enum PaymentIntentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Expired, self::PartiallyRefunded, self::Refunded], true);
    }
}
