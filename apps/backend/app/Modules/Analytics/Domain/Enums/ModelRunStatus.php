<?php
namespace Modules\Analytics\Domain\Enums;
enum ModelRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
