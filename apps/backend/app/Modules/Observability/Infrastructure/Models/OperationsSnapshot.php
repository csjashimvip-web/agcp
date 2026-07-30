<?php
namespace Modules\Observability\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class OperationsSnapshot extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['checks'=>'array','captured_at'=>'immutable_datetime'];} }
