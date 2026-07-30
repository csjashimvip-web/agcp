<?php
namespace Modules\Support\Domain\Enums;
enum TicketStatus:string { case Open='open'; case PendingCustomer='pending_customer'; case PendingInternal='pending_internal'; case Resolved='resolved'; case Closed='closed'; }
