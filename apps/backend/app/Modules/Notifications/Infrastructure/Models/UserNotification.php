<?php
namespace Modules\Notifications\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
final class UserNotification extends Model { use HasUuids; protected $table='user_notifications'; protected $guarded=[]; protected function casts():array{return ['data'=>'array','read_at'=>'immutable_datetime','archived_at'=>'immutable_datetime'];} public function user():BelongsTo{return $this->belongsTo(User::class);} }
