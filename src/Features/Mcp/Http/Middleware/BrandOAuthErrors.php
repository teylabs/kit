<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Mcp\OAuthErrorPage;

/**
 * On Passport's authorize route: an error that cannot go back to the app (no verified redirect URI, so Passport answers JSON and no redirect) is shown to a person
 * in a browser as the kit's 400 page, through OAuthErrorPage so the host can brand it. An error Passport sends back to the app's verified redirect URI, and any
 * caller asking for JSON, keep Passport's own answer. Passport raises the error as a response exception, which the routing pipeline has usually already turned
 * into its response by the time it reaches this middleware, so both the response and the exception are read.
 */
final class BrandOAuthErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (OAuthServerException $exception) {
            $response = $exception->getResponse();
            if ($request->expectsJson() || ! self::isOAuthError($response)) {
                throw $exception;
            }
        }
        if ($request->expectsJson() || ! self::isOAuthError($response)) {
            return $response;
        }
        $body = json_decode((string) $response->getContent(), true);

        return OAuthErrorPage::render($request, $body['error'], is_string($body['error_description'] ?? null) ? $body['error_description'] : null);
    }

    /** A client error carrying an OAuth JSON error body and no redirect: the answer RFC 6749 §4.1.2.1 gives when the redirect URI is not verified. */
    private static function isOAuthError(Response $response): bool
    {
        if ($response->isRedirection() || $response->getStatusCode() < 400 || $response->getStatusCode() >= 500 || ! str_contains((string) $response->headers->get('Content-Type', ''), 'json')) {
            return false;
        }
        $body = json_decode((string) $response->getContent(), true);

        return is_array($body) && is_string($body['error'] ?? null);
    }
}
