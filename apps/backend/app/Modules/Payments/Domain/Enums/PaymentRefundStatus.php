<?php
namespace Modules\Payments\Domain\Enums;

enum PaymentRefundStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
