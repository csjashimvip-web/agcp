<?php
namespace Modules\Wallet\Domain\Enums;
enum DepositStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
