<?php

namespace Tey\Kit\Features\Mcp;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page a person sees when an OAuth authorization request cannot go back to its app: an unknown client, a redirect_uri the client never registered, PKCE
 * missing or not S256. RFC 6749 §4.1.2.1 forbids redirecting then, and Passport answers a bare JSON error; a browser gets a readable 400 instead, JSON callers
 * keep the JSON. A host brands it without forking: `OAuthErrorPage::using(fn (Request $request, string $error, ?string $description): Response => ...)` for
 * its own page (an Inertia error page, say), or `kit.mcp.oauth_error_view` naming a Blade view that receives `error`, `description` and `title`.
 */
final class OAuthErrorPage
{
    /** @var (Closure(Request, string, ?string): Response)|null */
    private static ?Closure $renderer = null;

    /** @param  (Closure(Request, string, ?string): Response)|null  $renderer  null puts the kit's page back */
    public static function using(?Closure $renderer): void
    {
        self::$renderer = $renderer;
    }

    public static function render(Request $request, string $error, ?string $description = null): Response
    {
        if (self::$renderer !== null) {
            return (self::$renderer)($request, $error, $description)->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
        $view = config('kit.mcp.oauth_error_view');
        $view = is_string($view) && $view !== '' ? $view : 'kit::oauth-error';

        return response()->view($view, ['title' => 'This connection request cannot continue', 'error' => $error, 'description' => $description], Response::HTTP_BAD_REQUEST);
    }

    public static function json(string $error, ?string $description = null): Response
    {
        return response()->json(array_filter(['error' => $error, 'error_description' => $description]), Response::HTTP_BAD_REQUEST)->header('Cache-Control', 'no-store');
    }
}
