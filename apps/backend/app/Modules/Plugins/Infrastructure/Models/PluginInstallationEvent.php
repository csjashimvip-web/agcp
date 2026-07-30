<?php
namespace Modules\Plugins\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PluginInstallationEvent extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['context' => 'array', 'created_at' => 'immutable_datetime']; }
}
