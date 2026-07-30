<?php
namespace Modules\Shared\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class OutboxMessage extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['payload'=>'array','metadata'=>'array','occurred_at'=>'immutable_datetime','available_at'=>'immutable_datetime','published_at'=>'immutable_datetime','failed_at'=>'immutable_datetime']; }
}
