<?php
namespace Modules\Analytics\Domain\Enums;
enum CustomerSegmentCode: string
{
    case Champions = 'champions';
    case Loyal = 'loyal';
    case Promising = 'promising';
    case AtRisk = 'at_risk';
    case Dormant = 'dormant';
    case NewCustomer = 'new_customer';
}
