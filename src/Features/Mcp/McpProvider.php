<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Listeners\RebindConnection;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

final class McpProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/connections.php', 'web', $routes)
            ->routes(__DIR__.'/routes/mcp.php', null, $routes)
            ->scopes(['mcp:use' => 'Use workspace MCP'])
            ->connectionGuard(WorkspaceConnection::guard(...))
            ->errors(['connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409]);
        // The AI assistants page is hidden until the MCP connection rework (one address, workspace picked at consent): off by default, so it is neither in the settings nav nor reachable.
        if (config('kit.mcp.ui', false)) {
            $registry->settingsSection('workspace', 'connections', 'AI assistants', 'Connect AI assistants like Claude or ChatGPT to this workspace.', 'settings.read', 'Workspace', 20, 'plug');
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 3).'/resources/views', 'kit');
        Passport::tokensCan(app(Registry::class)->tokenScopes());
        Passport::tokensExpireIn(now()->addHour());
        Passport::authorizationView('kit::mcp.authorize');
        Event::listen(AccessTokenCreated::class, RebindConnection::class);
    }
}
