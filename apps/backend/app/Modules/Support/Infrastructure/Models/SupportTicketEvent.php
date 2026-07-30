<?php
namespace Modules\Support\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class SupportTicketEvent extends Model { use HasUuids; public $timestamps=false; protected $guarded=[]; protected function casts():array{return ['from_value'=>'array','to_value'=>'array','metadata'=>'array','created_at'=>'immutable_datetime'];} }
