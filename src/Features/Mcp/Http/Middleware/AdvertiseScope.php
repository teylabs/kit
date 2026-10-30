<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * The scope an assistant should ask for, on the 401 that starts its sign-in (RFC 6750 §3; the MCP spec's scope selection reads it before `scopes_supported`).
 * laravel/mcp's global middleware writes the challenge with the resource metadata URL; this runs outside it (prepended to the kernel) and adds `scope` to that
 * challenge, on MCP routes only.
 */
final class AdvertiseScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();
        if ($response->getStatusCode() !== 401 || ! $route instanceof \Illuminate\Routing\Route || ! in_array(AddWwwAuthenticateHeader::class, app('router')->gatherRouteMiddleware($route), true)) {
            return $response;
        }
        $challenge = (string) $response->headers->get('WWW-Authenticate', '');
        if ($challenge === '' || str_contains($challenge, 'scope=')) {
            return $response;
        }
        $response->headers->set('WWW-Authenticate', $challenge.', scope="'.self::scope().'"');

        return $response;
    }

    public static function scope(): string
    {
        return Registrar::OAUTH_SCOPE;
    }
}
