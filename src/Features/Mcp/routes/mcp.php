<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Tey\Kit\Features\Mcp\Http\Middleware\BindConsentWorkspace;
use Tey\Kit\Features\Mcp\Http\Middleware\BindMcpConnection;
use Tey\Kit\Features\Mcp\Http\Middleware\BrandOAuthErrors;
use Tey\Kit\Features\Mcp\Http\Middleware\RefuseCookieSessions;
use Tey\Kit\Features\Mcp\Http\Middleware\RequirePkce;
use Tey\Kit\Features\Mcp\Http\OAuthMetadataController;
use Tey\Kit\Features\Mcp\Http\RegisterClientController;
use Tey\Kit\Features\Mcp\McpProvider;
use Tey\Kit\Features\Mcp\WorkspaceServer;

// OAuth discovery (RFC 8414, RFC 9728), the kit's own so it advertises exactly what the kit implements, under laravel/mcp's route names (its 401 challenge points at the nested one).
Route::get('/.well-known/oauth-authorization-server', [OAuthMetadataController::class, 'authorizationServer'])->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [OAuthMetadataController::class, 'authorizationServer'])->where('path', '.*')->name('mcp.oauth.authorization-server.nested');
Route::get('/.well-known/oauth-protected-resource', [OAuthMetadataController::class, 'protectedResource'])->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [OAuthMetadataController::class, 'protectedResource'])->where('path', '.*')->name('mcp.oauth.protected-resource.nested');
// Dynamic client registration (RFC 7591), only when the host turns it on: off, the route does not exist and the metadata names no registration_endpoint.
if (McpProvider::registration()) {
    Route::post('/oauth/register', RegisterClientController::class)->middleware('throttle:'.McpProvider::REGISTRATION_LIMITER)->name('mcp.oauth.register');
}
// Named after being added (no route group here), so the name index is refreshed for the metadata's Route::has and laravel/mcp's challenge.
Route::getRoutes()->refreshNameLookups();
// One address for every assistant: the access token picks its connection. A connection's own address still works, and must match the token's.
Mcp::web('/mcp', WorkspaceServer::class)->middleware(['auth:api', 'concierge.active', BindMcpConnection::class]);
Mcp::web('/mcp/connections/{connection}', WorkspaceServer::class)->whereNumber('connection')->middleware(['auth:api', 'concierge.active', BindMcpConnection::class]);

// Public Laravel route middleware composes the package's web/session routes. A cookie session cannot hold the consent request, so it is refused first.
foreach (['passport.authorizations.authorize', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $name) {
    $route = Route::getRoutes()->getByName($name);
    if ($route === null) {
        throw new RuntimeException('Passport authorization routes are unavailable.');
    }
    $route->middleware([RefuseCookieSessions::class, 'auth:web', 'concierge.active']);
}
// A request that cannot go back to its app gets the kit's 400 page (a host brands it through OAuthErrorPage), and PKCE S256 is required before league sees the request.
Route::getRoutes()->getByName('passport.authorizations.authorize')->middleware([BrandOAuthErrors::class, RequirePkce::class]);
// Approving binds the connection to the workspace chosen on the consent page, which must be one of the person's. A host with its own consent (kit.mcp.consent=host) binds its own.
if (McpProvider::consent() === 'kit') {
    Route::getRoutes()->getByName('passport.authorizations.approve')->middleware(BindConsentWorkspace::class);
}
