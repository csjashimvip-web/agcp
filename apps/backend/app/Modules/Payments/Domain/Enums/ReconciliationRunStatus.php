<?php
namespace Modules\Payments\Domain\Enums;

enum ReconciliationRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
