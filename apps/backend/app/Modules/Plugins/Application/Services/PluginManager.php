<?php
namespace Modules\Plugins\Application\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Application\AuditLogger;
use Modules\Plugins\Domain\Enums\PluginInstallationStatus;
use Modules\Plugins\Infrastructure\Models\Plugin;
use Modules\Plugins\Infrastructure\Models\PluginInstallation;
use Modules\Plugins\Infrastructure\Models\PluginInstallationEvent;
use Modules\SaaS\Application\Services\EntitlementService;

final class PluginManager
{
    public function __construct(private readonly EntitlementService $entitlements, private readonly AuditLogger $audit) {}

    public function install(string $tenantId, Plugin $plugin, User $actor, array $configuration = []): PluginInstallation
    {
        $this->assertMarketplace($tenantId);
        abort_unless($plugin->status === 'available', 422, 'This plugin is not available for installation.');
        $this->validateConfiguration($plugin, $configuration, false);
        $installation = PluginInstallation::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'plugin_id' => $plugin->id],
            ['status' => PluginInstallationStatus::Installed, 'installed_version' => $plugin->version, 'enabled' => false,
                'configuration' => $configuration, 'installed_by' => $actor->id, 'installed_at' => now(), 'last_error' => null],
        );
        $this->event($installation, 'installed', $actor, ['version' => $plugin->version]);
        $this->audit->record('plugin.installed', PluginInstallation::class, $installation->id, ['plugin' => $plugin->slug], [], $tenantId, User::class, $actor->id);
        return $installation->fresh('plugin');
    }

    public function configure(PluginInstallation $installation, User $actor, array $configuration): PluginInstallation
    {
        $merged = array_replace_recursive($installation->configuration ?? [], $configuration);
        $this->validateConfiguration($installation->plugin, $merged, false);
        $installation->forceFill(['configuration' => $merged, 'last_error' => null])->save();
        $this->event($installation, 'configured', $actor, ['keys' => array_keys($configuration)]);
        return $installation->fresh('plugin');
    }

    public function enable(PluginInstallation $installation, User $actor): PluginInstallation
    {
        $this->assertMarketplace($installation->tenant_id);
        $this->validateConfiguration($installation->plugin, $installation->configuration ?? [], true);
        $installation->forceFill(['status' => PluginInstallationStatus::Enabled, 'enabled' => true, 'enabled_at' => now(), 'disabled_at' => null, 'last_error' => null])->save();
        $this->event($installation, 'enabled', $actor);
        return $installation->fresh('plugin');
    }

    public function disable(PluginInstallation $installation, User $actor): PluginInstallation
    {
        $installation->forceFill(['status' => PluginInstallationStatus::Disabled, 'enabled' => false, 'disabled_at' => now()])->save();
        $this->event($installation, 'disabled', $actor);
        return $installation->fresh('plugin');
    }

    private function assertMarketplace(string $tenantId): void
    {
        abort_unless($this->entitlements->enabled($tenantId, 'plugins.marketplace'), 403, 'The current subscription does not include the plugin marketplace.');
    }

    private function validateConfiguration(Plugin $plugin, array $configuration, bool $requireAll): void
    {
        $schema = $plugin->config_schema ?? [];
        foreach (($schema['required'] ?? []) as $key) {
            if ($requireAll && blank(Arr::get($configuration, (string) $key))) {
                throw ValidationException::withMessages(["configuration.{$key}" => "The {$key} configuration value is required."]);
            }
        }
        foreach (($schema['properties'] ?? []) as $key => $definition) {
            if (! array_key_exists($key, $configuration)) continue;
            $value = $configuration[$key];
            $type = $definition['type'] ?? 'string';
            if ($type === 'string' && ! is_string($value)) throw ValidationException::withMessages(["configuration.{$key}" => "The {$key} value must be a string."]);
            if ($type === 'boolean' && ! is_bool($value)) throw ValidationException::withMessages(["configuration.{$key}" => "The {$key} value must be true or false."]);
        }
    }

    private function event(PluginInstallation $installation, string $event, User $actor, array $context = []): void
    {
        PluginInstallationEvent::query()->create(['plugin_installation_id' => $installation->id, 'event' => $event, 'actor_id' => $actor->id, 'context' => $context]);
    }
}
