<?php
namespace Modules\SaaS\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class TenantBrandingProfile extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['settings' => 'array']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
