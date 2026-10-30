<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Mcp\OAuthErrorPage;

/**
 * On Passport's authorize route: every authorization request carries a PKCE code challenge with the S256 method (OAuth 2.1 §4.1.1; the MCP authorization spec
 * requires S256 and the kit advertises only it). league requires a challenge for a public client but would accept `plain`; this refuses before league sees the
 * request, with a 400 and no redirect, since the redirect URI has not been verified at this point.
 */
final class RequirePkce
{
    public function handle(Request $request, Closure $next): Response
    {
        $challenge = $request->query('code_challenge');
        $method = $request->query('code_challenge_method');
        if (! is_string($challenge) || preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $challenge) !== 1) {
            return self::refuse($request, 'A PKCE code_challenge of 43 to 128 unreserved characters is required.');
        }
        if ($method !== 'S256') {
            return self::refuse($request, 'code_challenge_method must be S256.');
        }

        return $next($request);
    }

    private static function refuse(Request $request, string $description): Response
    {
        return $request->expectsJson() ? OAuthErrorPage::json('invalid_request', $description) : OAuthErrorPage::render($request, 'invalid_request', $description);
    }
}
