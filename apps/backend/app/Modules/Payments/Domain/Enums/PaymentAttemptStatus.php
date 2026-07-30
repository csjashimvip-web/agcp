<?php
namespace Modules\Payments\Domain\Enums;

enum PaymentAttemptStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
