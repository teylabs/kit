<?php

namespace Tey\Kit\Features\Settings;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Defaults;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadMailIdentity;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSetup;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use Tey\Kit\Features\Settings\Mail\ResendSendingDomains;
use Tey\Kit\Features\Settings\Mail\SendingDomains;
use Tey\Kit\Features\Settings\Mail\WorkspaceMailIdentity;
use Tey\Kit\Features\Settings\Setup\SetupChecks;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Mail\FluentMailMessage;

final class SettingsProvider extends ServiceProvider implements Contributes
{
    public function register(): void
    {
        Defaults::apply($this->app, 'settings', 'spatie/laravel-settings');
        $this->app->bindIf(SendingDomains::class, ResendSendingDomains::class);
    }

    public function boot(): void
    {
        // Workspace mail identity: every FluentMailMessage built inside a workspace takes its sender, address and reply-to from it.
        FluentMailMessage::resolveIdentityUsing(fn (?object $sender): ?array => WorkspaceMailIdentity::resolve($sender));
        // Setup's queue row: when the newest job finished.
        Event::listen(JobProcessed::class, fn () => Cache::forever(SetupChecks::LAST_FINISHED, now()->getTimestamp()));
    }

    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        SetupChecks::register($registry);
        $registry->routes(__DIR__.'/routes/settings.php', 'web', $routes)
            ->tools(in_array('mcp', $features, true) ? [ReadSettings::class, UpdateSettings::class, ReadMailIdentity::class, ReadSetup::class] : [])
            ->settingsSection('workspace', 'general', 'General', 'The workspace display name and timezone.', 'settings.read', 'Workspace', -10, 'sliders-horizontal')
            ->settingsSection('workspace', 'email', 'Email sending', 'The sender name, from address and reply-to of mail from this workspace.', 'settings.read', 'Workspace', -5, 'mail')
            ->settingsSection('workspace', 'setup', 'Setup', 'What is set up, what is missing, and how to fix each: mail, media, security and the queue.', 'settings.write', 'Workspace', 900, 'gauge')
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
                        $workspace = Workspaces::required();
                        $settings = WorkspaceSettings::current();
                        $settings->timezone = $timezone;
                        $settings->save();
                        Workspaces::query()->whereKey($workspace->getKey())->increment('settings_version');
                        app(Audit::class)->write($actorId, (int) $workspace->getKey(), 'workspace.settings.updated', ['fields' => ['timezone']]);
                    });
                },
            )
            ->errors(['stale_settings' => 409, 'stale_mail_identity' => 409], ['stale_settings' => 'These settings changed since you opened the form. Reload and review the current values.', 'stale_mail_identity' => 'Email sending changed since you opened the form. Reload and review the current values.']);
    }
}
