<?php

namespace Tey\Kit\Features\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use Tey\Kit\Features\Workspaces\Models\Workspace;

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
                // A compatibility mirror only: General reads and renames the workspace's own name (WorkspaceProfile).
                $repository->createProperty(WorkspaceSettings::group(), 'display_name', '');
                $repository->createProperty(WorkspaceSettings::group(), 'timezone', 'UTC');
            })
            ->workspaceTimezoneUsing(
                fn (): string => WorkspaceSettings::current()->timezone,
                function (int $actorId, string $timezone): void {
                    DB::transaction(function () use ($actorId, $timezone): void {
                        $workspace = Workspace::required();
                        $settings = WorkspaceSettings::current();
                        $settings->timezone = $timezone;
                        $settings->save();
                        Workspace::query()->whereKey($workspace->getKey())->increment('settings_version');
                        app(Audit::class)->write($actorId, (int) $workspace->getKey(), 'workspace.settings.updated', ['fields' => ['timezone']]);
                    });
                },
            )
            ->errors(['stale_settings' => 409], ['stale_settings' => 'These settings changed since you opened the form. Reload and review the current values.']);
    }
}
