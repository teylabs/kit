<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Registry;

/**
 * OAuth discovery for MCP clients, advertising only what the kit implements: authorization server metadata (RFC 8414) and protected resource metadata
 * (RFC 9728), at the root well-known paths and the path-suffixed ones an MCP client tries for a server below the root. Under laravel/mcp's route names,
 * so its WWW-Authenticate challenge points here. The issuer is `mcp.authorization_server` when a host sets it, else this app's URL.
 */
final class OAuthMetadataController
{
    public function authorizationServer(Registry $registry): JsonResponse
    {
        return response()->json(array_filter([
            'issuer' => self::issuer(),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => Route::has('mcp.oauth.register') ? route('mcp.oauth.register') : null,
            'response_types_supported' => ['code'],
            'grant_types_supported' => RegisterClientController::GRANT_TYPES,
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => array_keys($registry->tokenScopes()),
        ], fn (mixed $value): bool => $value !== null));
    }

    public function protectedResource(Registry $registry, ?string $path = null): JsonResponse
    {
        return response()->json([
            'resource' => url('/'.ltrim((string) $path, '/')),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => array_keys($registry->tokenScopes()),
            'bearer_methods_supported' => ['header'],
            'resource_name' => (string) config('app.name'),
        ]);
    }

    public static function issuer(): string
    {
        $issuer = config('mcp.authorization_server');

        return is_string($issuer) && $issuer !== '' ? $issuer : url('/');
    }
}
