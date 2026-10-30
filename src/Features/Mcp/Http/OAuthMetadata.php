<?php

namespace Tey\Kit\Features\Mcp\Http;

use RuntimeException;

/**
 * What the OAuth discovery documents share. The issuer is `mcp.authorization_server` when a host sets it, else this app's URL; either way it must be this app
 * (the authorize and token endpoints beside it are Passport's here), so an issuer on another origin is refused rather than advertised with endpoints it does not serve.
 */
final class OAuthMetadata
{
    public static function issuer(): string
    {
        $issuer = config('mcp.authorization_server');
        if (! is_string($issuer) || $issuer === '') {
            return url('/');
        }
        $own = parse_url(url('/'));
        $set = parse_url($issuer);
        if (! is_array($set) || ! is_array($own) || strtolower((string) ($set['scheme'] ?? '')) !== strtolower((string) ($own['scheme'] ?? '')) || strtolower((string) ($set['host'] ?? '')) !== strtolower((string) ($own['host'] ?? '')) || ($set['port'] ?? null) !== ($own['port'] ?? null) || isset($set['query']) || isset($set['fragment'])) {
            throw new RuntimeException('mcp.authorization_server must be this app\'s own origin ('.url('/').'), since its authorize and token endpoints are served here: '.$issuer);
        }

        return rtrim($issuer, '/');
    }
}
