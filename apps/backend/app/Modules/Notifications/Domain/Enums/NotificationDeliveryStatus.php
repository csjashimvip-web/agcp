<?php
namespace Modules\Notifications\Domain\Enums;
enum NotificationDeliveryStatus:string { case Queued='queued'; case Sending='sending'; case Sent='sent'; case Delivered='delivered'; case Failed='failed'; case Suppressed='suppressed'; }
