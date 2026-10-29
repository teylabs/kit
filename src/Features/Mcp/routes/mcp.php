<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Tey\Kit\Features\Mcp\Http\Middleware\BindConsentWorkspace;
use Tey\Kit\Features\Mcp\Http\Middleware\BindMcpConnection;
use Tey\Kit\Features\Mcp\Http\Middleware\RefuseCookieSessions;
use Tey\Kit\Features\Mcp\WorkspaceServer;

Mcp::oauthRoutes(registration: false);
Mcp::web('/mcp/connections/{connection}', WorkspaceServer::class)->whereNumber('connection')->middleware(['auth:api', 'concierge.active', BindMcpConnection::class]);

// Public Laravel route middleware composes the package's web/session routes. A cookie session cannot hold the consent request, so it is refused first.
foreach (['passport.authorizations.authorize', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $name) {
    $route = Route::getRoutes()->getByName($name);
    if ($route === null) {
        throw new RuntimeException('Passport authorization routes are unavailable.');
    }
    $route->middleware([RefuseCookieSessions::class, 'auth:web', 'concierge.active']);
}
// Approving binds the connection to the workspace chosen on the consent page, which must be one of the person's.
Route::getRoutes()->getByName('passport.authorizations.approve')->middleware(BindConsentWorkspace::class);
