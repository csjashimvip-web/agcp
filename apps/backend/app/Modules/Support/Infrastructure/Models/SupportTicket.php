<?php
namespace Modules\Support\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Support\Domain\Enums\TicketPriority;
use Modules\Support\Domain\Enums\TicketStatus;
final class SupportTicket extends Model { use HasUuids; protected $guarded=[]; protected function casts():array{return ['priority'=>TicketPriority::class,'status'=>TicketStatus::class,'tags'=>'array','metadata'=>'array','first_response_due_at'=>'immutable_datetime','resolution_due_at'=>'immutable_datetime','first_responded_at'=>'immutable_datetime','resolved_at'=>'immutable_datetime','closed_at'=>'immutable_datetime','last_activity_at'=>'immutable_datetime'];} public function requester():BelongsTo{return $this->belongsTo(User::class,'requester_id');} public function assignee():BelongsTo{return $this->belongsTo(User::class,'assigned_to');} public function messages():HasMany{return $this->hasMany(SupportTicketMessage::class);} public function events():HasMany{return $this->hasMany(SupportTicketEvent::class);} }
