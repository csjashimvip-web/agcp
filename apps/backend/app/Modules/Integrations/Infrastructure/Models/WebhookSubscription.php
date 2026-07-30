<?php
namespace Modules\Integrations\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class WebhookSubscription extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['enabled'=>'boolean'];} }
