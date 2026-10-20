<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Tey\Kit\Features\Core\Contributes;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

final class McpProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->routes(__DIR__.'/routes/connections.php', 'web', $routes)
            ->routes(__DIR__.'/routes/mcp.php', null, $routes)
            ->settingsSection('workspace', 'connections', 'Connections', 'Delegated access for MCP clients to this workspace.', 'settings.read', 'Workspace', 20, 'plug')
            ->scopes(['mcp:use' => 'Use workspace MCP'])
            ->connectionGuard(WorkspaceConnection::guard(...))
            ->errors(['connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 3).'/resources/views', 'kit');
        Passport::tokensCan(app(Registry::class)->tokenScopes());
        Passport::tokensExpireIn(now()->addHour());
        Passport::authorizationView('kit::mcp.authorize');
    }
}
