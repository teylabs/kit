<?php

namespace Tey\Kit\Features\SessionUi;

/**
 * Keeps a redirect on the app: a URL is used only when it is a plain path ("/me", never "//host" or "/\host") or an absolute http(s) URL on the app's own
 * scheme, host and port (app.url) without credentials. Anything else (another host, javascript: or data:, backslashes, control characters, whitespace) is the fallback.
 */
final class SafeRedirect
{
    public static function sameOrigin(?string $url, string $fallback, ?string $origin = null): string
    {
        if ($url === null || $url === '' || trim($url) !== $url || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F\s]/', $url) === 1) {
            return $fallback;
        }
        if ($url[0] === '/') {
            return str_starts_with($url, '//') ? $fallback : $url;
        }
        $parts = parse_url($url);
        $app = parse_url($origin ?? (string) config('app.url'));
        if ($parts === false || $app === false || ! isset($parts['scheme'], $parts['host'], $app['scheme'], $app['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return $fallback;
        }
        $scheme = strtolower($parts['scheme']);
        $port = fn (array $p, string $s): int => $p['port'] ?? ($s === 'https' ? 443 : 80);
        $same = in_array($scheme, ['http', 'https'], true) && $scheme === strtolower($app['scheme'])
            && strtolower($parts['host']) === strtolower($app['host']) && $port($parts, $scheme) === $port($app, strtolower($app['scheme']));

        return $same ? $url : $fallback;
    }
}
