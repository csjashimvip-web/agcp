<?php
namespace Modules\Plugins\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Plugins\Application\Services\PluginManager;
use Modules\Plugins\Infrastructure\Models\Plugin;
use Modules\Plugins\Infrastructure\Models\PluginInstallation;
use Modules\Tenancy\Application\TenantContext;

final class AdminPluginController extends Controller
{
    public function index(TenantContext $context): array
    {
        $installations = PluginInstallation::query()->with('plugin')->where('tenant_id', $context->requireId())->get()->keyBy('plugin_id');
        return ['data' => Plugin::query()->whereIn('status', ['available', 'deprecated'])->orderBy('category')->orderBy('name')->get()->map(function (Plugin $plugin) use ($installations): array {
            $installation = $installations->get($plugin->id);
            return ['id' => $plugin->id, 'slug' => $plugin->slug, 'name' => $plugin->name, 'version' => $plugin->version,
                'category' => $plugin->category, 'provider_type' => $plugin->provider_type, 'description' => $plugin->description,
                'is_core' => $plugin->is_core, 'capabilities' => $plugin->capabilities ?? [], 'config_schema' => $plugin->config_schema ?? [],
                'requested_permissions' => $plugin->requested_permissions ?? [], 'installation' => $installation ? [
                    'id' => $installation->id, 'status' => $installation->status->value, 'enabled' => $installation->enabled,
                    'installed_version' => $installation->installed_version, 'installed_at' => $installation->installed_at?->toIso8601String(),
                    'configured_keys' => array_keys($installation->configuration ?? []), 'last_error' => $installation->last_error,
                ] : null];
        })->all()];
    }

    public function install(Request $request, Plugin $plugin, TenantContext $context, PluginManager $manager): array
    {
        $data = $request->validate(['configuration' => ['nullable', 'array']]);
        return ['data' => $manager->install($context->requireId(), $plugin, $request->user(), $data['configuration'] ?? [])];
    }
    public function configure(Request $request, PluginInstallation $installation, TenantContext $context, PluginManager $manager): array
    {
        abort_unless($installation->tenant_id === $context->requireId(), 404);
        $data = $request->validate(['configuration' => ['required', 'array']]);
        return ['data' => $manager->configure($installation->load('plugin'), $request->user(), $data['configuration'])];
    }
    public function enable(Request $request, PluginInstallation $installation, TenantContext $context, PluginManager $manager): array
    {
        abort_unless($installation->tenant_id === $context->requireId(), 404);
        return ['data' => $manager->enable($installation->load('plugin'), $request->user())];
    }
    public function disable(Request $request, PluginInstallation $installation, TenantContext $context, PluginManager $manager): array
    {
        abort_unless($installation->tenant_id === $context->requireId(), 404);
        return ['data' => $manager->disable($installation->load('plugin'), $request->user())];
    }
}
