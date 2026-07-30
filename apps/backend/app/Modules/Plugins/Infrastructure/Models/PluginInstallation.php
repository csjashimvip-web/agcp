<?php
namespace Modules\Plugins\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Plugins\Domain\Enums\PluginInstallationStatus;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class PluginInstallation extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected $hidden = ['configuration'];
    protected function casts(): array
    {
        return [
            'status' => PluginInstallationStatus::class, 'enabled' => 'boolean', 'configuration' => 'encrypted:array',
            'installed_at' => 'immutable_datetime', 'enabled_at' => 'immutable_datetime', 'disabled_at' => 'immutable_datetime', 'metadata' => 'array',
        ];
    }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plugin(): BelongsTo { return $this->belongsTo(Plugin::class); }
    public function events(): HasMany { return $this->hasMany(PluginInstallationEvent::class); }
}
