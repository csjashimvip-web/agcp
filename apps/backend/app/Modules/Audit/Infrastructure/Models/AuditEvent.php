<?php
namespace Modules\Audit\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model
{
    use HasUuids;
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected function casts(): array { return ['context'=>'array','changes'=>'array','occurred_at'=>'immutable_datetime']; }
}
