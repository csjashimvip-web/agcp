<?php
namespace Modules\Suppliers\Domain\Enums;
enum SupplierOrderStatus: string
{
    case Queued = 'queued';
    case Routing = 'routing';
    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Retrying = 'retrying';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Refunded], true);
    }
}
