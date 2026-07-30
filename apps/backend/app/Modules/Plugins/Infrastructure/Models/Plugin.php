<?php
namespace Modules\Plugins\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plugin extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return ['is_core' => 'boolean', 'capabilities' => 'array', 'config_schema' => 'array', 'requested_permissions' => 'array', 'manifest' => 'array'];
    }
    public function installations(): HasMany { return $this->hasMany(PluginInstallation::class); }
}
