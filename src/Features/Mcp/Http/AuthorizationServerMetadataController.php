<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Registry;

/**
 * Authorization server metadata (RFC 8414), at the root well-known path and the path-suffixed one an MCP client tries for a server below the root, under
 * laravel/mcp's route names. The kit's own, so it advertises only what the kit implements: `code`, the authorization code and refresh grants, S256, public
 * clients, the kit's token scopes, and `registration_endpoint` only while registration is on.
 */
final class AuthorizationServerMetadataController
{
    public function __invoke(Registry $registry): JsonResponse
    {
        return response()->json(array_filter([
            'issuer' => OAuthMetadata::issuer(),
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
}
