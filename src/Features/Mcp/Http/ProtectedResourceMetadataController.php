<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\JsonResponse;
use Tey\Kit\Features\Core\Registry;

/**
 * Protected resource metadata (RFC 9728) for this app as an MCP server, at the root well-known path and the path-suffixed one (`/mcp`): the resource is the URL
 * of that path, which is what an assistant's user typed; laravel/mcp's 401 challenge points at the path-suffixed document.
 */
final class ProtectedResourceMetadataController
{
    public function __invoke(Registry $registry, ?string $path = null): JsonResponse
    {
        return response()->json([
            'resource' => url('/'.ltrim((string) $path, '/')),
            'authorization_servers' => [OAuthMetadata::issuer()],
            'scopes_supported' => array_keys($registry->tokenScopes()),
            'bearer_methods_supported' => ['header'],
            'resource_name' => (string) config('app.name'),
        ]);
    }
}
