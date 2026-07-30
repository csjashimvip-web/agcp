<?php
namespace Modules\Analytics\Domain\Enums;
enum InsightType: string
{
    case Sales = 'sales';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Fraud = 'fraud';
    case Operations = 'operations';
}
