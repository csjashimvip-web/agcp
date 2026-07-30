<?php
namespace Modules\Integrations\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class WebhookEndpoint extends Model { use HasUuids; protected $guarded=[]; protected $hidden=['signing_secret']; protected function casts():array{return ['signing_secret'=>'encrypted','custom_headers'=>'encrypted:array','metadata'=>'array','verify_tls'=>'boolean','last_success_at'=>'immutable_datetime','last_failure_at'=>'immutable_datetime'];} public function subscriptions():HasMany{return $this->hasMany(WebhookSubscription::class);} }
