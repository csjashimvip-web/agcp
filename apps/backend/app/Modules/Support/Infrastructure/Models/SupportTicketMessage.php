<?php
namespace Modules\Support\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
final class SupportTicketMessage extends Model { use HasUuids; public $timestamps=false; protected $guarded=[]; protected function casts():array{return ['is_internal'=>'boolean','attachments'=>'array','created_at'=>'immutable_datetime'];} }
