<?php
namespace Modules\Suppliers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Suppliers\Domain\Enums\RoutingStrategy;

final class SupplierRoutingProfile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'strategy' => RoutingStrategy::class,
            'is_default' => 'boolean',
            'weights' => 'array',
            'metadata' => 'array',
        ];
    }
}
