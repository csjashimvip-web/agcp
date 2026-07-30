<?php
namespace Modules\Suppliers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Suppliers\Domain\Enums\RoutingStrategy;
use Modules\Suppliers\Infrastructure\Models\SupplierRoutingProfile;
use Modules\Tenancy\Application\TenantContext;

final class AdminSupplierRoutingProfileController extends Controller
{
    public function show(TenantContext $tenant): array
    {
        $profile = SupplierRoutingProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->requireId(), 'slug' => 'default'],
            ['name' => 'Default supplier routing', 'strategy' => RoutingStrategy::Balanced, 'is_default' => true, 'status' => 'active'],
        );
        return ['data' => $this->data($profile)];
    }

    public function update(Request $request, TenantContext $tenant): array
    {
        $validated = $request->validate([
            'strategy' => ['required', Rule::enum(RoutingStrategy::class)],
            'weights' => ['nullable', 'array'],
            'weights.cost' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weights.success' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weights.latency' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weights.health' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'weights.priority' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);
        $profile = SupplierRoutingProfile::query()->updateOrCreate(
            ['tenant_id' => $tenant->requireId(), 'slug' => 'default'],
            [
                'name' => 'Default supplier routing',
                'strategy' => $validated['strategy'],
                'weights' => $validated['weights'] ?? null,
                'is_default' => true,
                'status' => 'active',
            ],
        );
        return ['data' => $this->data($profile)];
    }

    private function data(SupplierRoutingProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'slug' => $profile->slug,
            'strategy' => $profile->strategy->value,
            'weights' => $profile->weights,
            'is_default' => (bool) $profile->is_default,
            'status' => $profile->status,
        ];
    }
}
