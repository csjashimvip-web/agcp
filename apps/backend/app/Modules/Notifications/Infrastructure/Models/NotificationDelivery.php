<?php
namespace Modules\Notifications\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Domain\Enums\NotificationDeliveryStatus;
final class NotificationDelivery extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['status'=>NotificationDeliveryStatus::class,'payload'=>'array','next_attempt_at'=>'immutable_datetime','sent_at'=>'immutable_datetime','delivered_at'=>'immutable_datetime','failed_at'=>'immutable_datetime'];} }
