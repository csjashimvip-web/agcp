<?php
namespace Modules\Integrations\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class WebhookDelivery extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['payload'=>'encrypted','next_attempt_at'=>'immutable_datetime','delivered_at'=>'immutable_datetime','failed_at'=>'immutable_datetime'];} public function endpoint(){return $this->belongsTo(WebhookEndpoint::class,'webhook_endpoint_id');} }
