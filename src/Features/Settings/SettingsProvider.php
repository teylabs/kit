<?php

namespace Tey\Kit\Features\Settings;

use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;

final class SettingsProvider extends ServiceProvider implements Contributes
{
    public function register(): void
    {
        Defaults::apply($this->app, 'settings', 'spatie/laravel-settings');
    }

    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/settings.php', 'web', $routes)
            ->tools(in_array('mcp', $features, true) ? [ReadSettings::class, UpdateSettings::class] : [])
            ->settingsSection('workspace', 'general', 'General', 'The workspace display name and timezone.', 'settings.read', 'Workspace', -10, 'sliders-horizontal')
            ->scopes(['settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings'])
            ->abilities(['settings.read' => 'workspace.settings', 'settings.write' => 'workspace.settings'])
            ->capabilities(['workspace.settings'])
            ->permission('workspace.settings.update', ['owner', 'admin'], 'workspace.settings', ['settings.write'])
            ->provisioning(function (object $workspace): void {
                $repository = SettingsRepositoryFactory::create();
                // Unset until someone names it: the workspace's own name shows meanwhile, and follows a rename.
                $repository->createProperty(WorkspaceSettings::group(), 'display_name', '');
                $repository->createProperty(WorkspaceSettings::group(), 'timezone', 'UTC');
            })
            ->errors(['stale_settings' => 409], ['stale_settings' => 'These settings changed since you opened the form. Reload and review the current values.']);
    }
}
