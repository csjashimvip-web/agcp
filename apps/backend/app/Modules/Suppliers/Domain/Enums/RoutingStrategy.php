<?php
namespace Modules\Suppliers\Domain\Enums;
enum RoutingStrategy: string
{
    case Balanced = 'balanced';
    case Cheapest = 'cheapest';
    case Fastest = 'fastest';
    case HighestSuccess = 'highest_success';
    case Priority = 'priority';
}
