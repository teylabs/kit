<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Features\SessionUi\Contracts\ConfirmPasswordCancelUrl;
use Throwable;

/**
 * The kit's Cancel for the confirm-password page, the first that is on the app (SafeRedirect) and not the page itself: the `cancel` query, the referer,
 * the intended URL one level up when a GET route that is not a guest page answers there, else kit.home. A refused step falls through to the next.
 */
final class ConfirmPasswordCancel implements ConfirmPasswordCancelUrl
{
    public function resolve(Request $request): string
    {
        $page = parse_url(route('password.confirm'), PHP_URL_PATH);
        $cancel = $request->query('cancel');
        $referer = $request->headers->get('referer');
        foreach ([is_string($cancel) ? $cancel : null, is_string($referer) ? $referer : null] as $candidate) {
            $url = SafeRedirect::sameOrigin($candidate, '');
            if ($url !== '' && parse_url($url, PHP_URL_PATH) !== $page) {
                return $url;
            }
        }
        $intended = $request->hasSession() ? $request->session()->get('url.intended') : null;
        $intended = SafeRedirect::sameOrigin(is_string($intended) ? $intended : null, '');
        $path = $intended === '' ? null : parse_url($intended, PHP_URL_PATH);
        if (is_string($path) && $path !== '/' && ($parent = self::answered(rtrim(dirname($path), '/') ?: '/', $page)) !== null) {
            return $parent;
        }

        return (string) config('kit.home', '/');
    }

    /** The path when a real GET route answers it (not the fallback, not a guest page, not the confirm page): null otherwise, and on any doubt. */
    private static function answered(string $path, mixed $page): ?string
    {
        if ($path === $page || str_contains($path, '\\')) {
            return null;
        }
        try {
            $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
            $middlewares = $route instanceof Route && ! $route->isFallback ? $route->gatherMiddleware() : null;
        } catch (Throwable) {
            return null;
        }
        foreach ($middlewares ?? ['guest'] as $middleware) {
            if (is_string($middleware) && (str_starts_with($middleware, 'guest') || is_a(explode(':', $middleware)[0], RedirectIfAuthenticated::class, true))) {
                return null;
            }
        }

        return $path;
    }
}
