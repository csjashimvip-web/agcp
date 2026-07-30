<?php
namespace Modules\Notifications\Domain\Enums;
enum NotificationChannel:string { case InApp='in_app'; case Email='email'; case Sms='sms'; case WebPush='web_push'; }
