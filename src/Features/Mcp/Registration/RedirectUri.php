<?php

namespace Tey\Kit\Features\Mcp\Registration;

/**
 * The redirect URIs a dynamically registered client may hold (RFC 7591 §2, OAuth 2.1 §1.5, RFC 8252 §7.3): `https` with a host; `http` only to a loopback
 * host (any port, for a native client's ephemeral listener); or a private-use scheme the host lists in `kit.mcp.redirect_schemes`. Never a wildcard, a fragment,
 * userinfo, whitespace, or a scheme that runs in the browser (javascript, data, blob, file), listed or not. Checked at registration only: a client registered
 * before this feature keeps the URIs it holds.
 */
final class RedirectUri
{
    public const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /** Schemes refused even when a host lists them: they execute or read in the browser, never reach a client. `http` and `https` have their own rules above. */
    public const array REFUSED_SCHEMES = ['javascript', 'data', 'blob', 'file', 'http', 'https', 'about', 'vbscript'];

    /** Why the URI is refused, in a sentence for the registration error, or null when it may be registered. */
    public static function refusal(mixed $uri, array $schemes = []): ?string
    {
        if (! is_string($uri) || $uri === '' || strlen($uri) > 1024) {
            return 'redirect_uris must be strings of at most 1024 characters.';
        }
        if (preg_match('/[\s\x00-\x1f\x7f]/', $uri) === 1 || str_contains($uri, '*')) {
            return 'A redirect URI may not contain whitespace, control characters or a wildcard.';
        }
        if (str_contains($uri, '#')) {
            return 'A redirect URI may not carry a fragment.';
        }
        $parts = parse_url($uri);
        if ($parts === false || ! isset($parts['scheme']) || $parts['scheme'] === '') {
            return 'A redirect URI must be absolute, with a scheme.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'A redirect URI may not carry credentials.';
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === 'https') {
            return $host === '' ? 'An https redirect URI must name a host.' : null;
        }
        if ($scheme === 'http') {
            return in_array($host, self::LOOPBACK_HOSTS, true) ? null : 'An http redirect URI is only allowed to a loopback host (localhost, 127.0.0.1 or [::1]).';
        }
        if (in_array($scheme, self::REFUSED_SCHEMES, true)) {
            return 'The '.$scheme.' scheme is never allowed for a redirect URI.';
        }
        $allowed = array_map(fn (mixed $allowed): string => strtolower((string) $allowed), array_values(array_filter($schemes, 'is_string')));
        if (! in_array($scheme, $allowed, true)) {
            return 'The '.$scheme.' scheme is not an allowed redirect scheme.';
        }
        if ($host === '' && (($parts['path'] ?? '') === '' || ($parts['path'] ?? '') === '/')) {
            return 'A '.$scheme.' redirect URI must name a host or a path.';
        }

        return null;
    }

    /** An http URI to a loopback host, whose port the client picks per session (RFC 8252 §7.3). */
    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);

        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'http' && ! isset($parts['user']) && ! isset($parts['pass'])
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::LOOPBACK_HOSTS, true);
    }

    /** The URI with its port removed, for comparing two loopback URIs; null when it does not parse. */
    public static function withoutPort(string $uri): ?string
    {
        $parts = parse_url($uri);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
