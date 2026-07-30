<?php
namespace Modules\Notifications\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class NotificationPreference extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['in_app_enabled'=>'boolean','email_enabled'=>'boolean','sms_enabled'=>'boolean','web_push_enabled'=>'boolean'];} }
