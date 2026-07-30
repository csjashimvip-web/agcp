<?php
namespace Modules\Suppliers\Domain\Enums;
enum SupplierAccountStatus: string
{
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
}
