<?php
namespace Modules\Observability\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class OperationalIncident extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['evidence'=>'array','acknowledged_at'=>'immutable_datetime','resolved_at'=>'immutable_datetime','last_seen_at'=>'immutable_datetime'];} }
