<?php
namespace Modules\Payments\Domain\Enums;

enum PaymentWebhookStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
