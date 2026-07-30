<?php
namespace Modules\Analytics\Domain\Enums;
enum InsightSeverity: string
{
    case Info = 'info';
    case Opportunity = 'opportunity';
    case Warning = 'warning';
    case Critical = 'critical';
}
