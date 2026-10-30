<?php

namespace Tey\Kit\Features\Mcp\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Registration\RedirectUri;
use Tey\Kit\Features\Mcp\Registration\Registration;
use Tey\Kit\Features\Mcp\Registration\RegistrationPolicy;

/**
 * Dynamic client registration (RFC 7591), `POST /oauth/register`, for public clients only: an assistant such as Claude registers itself before its first
 * authorization. The client is a Passport authorization-code client with no secret, exactly as the kit's consent and token paths read them, so a client
 * registered by another route keeps working. The answer carries no secret and no management credentials (RFC 7592 is not offered, so not advertised).
 * Refusals are RFC 7591 §3.2.2: 400 with `invalid_redirect_uri` or `invalid_client_metadata`; the host's policy refuses with 403 `access_denied`.
 */
final class RegisterClientController
{
    public const array GRANT_TYPES = ['authorization_code', 'refresh_token'];

    public const array APPLICATION_TYPES = ['web', 'native'];

    public function __invoke(Request $request, ClientRepository $clients, Registry $registry): JsonResponse
    {
        $body = $request->json()->all();
        if (! is_array($body) || $body === [] || array_is_list($body)) {
            return self::refuse('invalid_client_metadata', 'The registration request must be a JSON object.');
        }
        $uris = $body['redirect_uris'] ?? null;
        if (! is_array($uris) || ! array_is_list($uris) || $uris === [] || count($uris) > 10) {
            return self::refuse('invalid_redirect_uri', 'redirect_uris must list between 1 and 10 redirect URIs.');
        }
        $schemes = (array) config('kit.mcp.redirect_schemes', []);
        foreach ($uris as $uri) {
            if (($why = RedirectUri::refusal($uri, $schemes)) !== null) {
                return self::refuse('invalid_redirect_uri', $why);
            }
        }
        $uris = array_values(array_unique($uris));
        $name = $body['client_name'] ?? null;
        if ($name !== null && (! is_string($name) || trim($name) === '' || mb_strlen($name) > 255 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1)) {
            return self::refuse('invalid_client_metadata', 'client_name must be a plain string of at most 255 characters.');
        }
        $name = $name === null ? (string) (parse_url($uris[0], PHP_URL_HOST) ?: 'MCP client') : trim($name);
        $type = $body['application_type'] ?? 'web';
        if (! in_array($type, self::APPLICATION_TYPES, true)) {
            return self::refuse('invalid_client_metadata', 'application_type must be web or native.');
        }
        if (($why = self::unsupported($body, 'grant_types', self::GRANT_TYPES) ?? self::unsupported($body, 'response_types', ['code'])) !== null) {
            return self::refuse('invalid_client_metadata', $why);
        }
        if (array_key_exists('token_endpoint_auth_method', $body) && $body['token_endpoint_auth_method'] !== 'none') {
            return self::refuse('invalid_client_metadata', 'Only public clients are registered: token_endpoint_auth_method must be none.');
        }
        $scopes = array_keys($registry->tokenScopes());
        if (array_key_exists('scope', $body)) {
            $asked = is_string($body['scope']) ? array_values(array_filter(explode(' ', $body['scope']))) : null;
            if ($asked === null || array_diff($asked, $scopes) !== []) {
                return self::refuse('invalid_client_metadata', 'scope may only name scopes this server supports: '.implode(' ', $scopes).'.');
            }
        }

        $registration = new Registration($name, $uris, $type, $request->ip(), $body);
        if (($why = self::policy()?->refuse($registration)) !== null) {
            return self::refuse('access_denied', $why, 403);
        }

        $client = $clients->createAuthorizationCodeGrantClient(name: $name, redirectUris: $uris, confidential: false);

        return response()->json([
            'client_id' => (string) $client->getKey(),
            'client_id_issued_at' => $client->created_at?->getTimestamp() ?? now()->getTimestamp(),
            'client_name' => $name,
            'redirect_uris' => $uris,
            'grant_types' => self::GRANT_TYPES,
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'application_type' => $type,
            'scope' => implode(' ', $scopes),
        ], 201)->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    public static function policy(): ?RegistrationPolicy
    {
        $class = config('kit.mcp.registration_policy');
        if ($class === null || $class === '') {
            return null;
        }
        $policy = is_string($class) && class_exists($class) ? app($class) : null;
        if (! $policy instanceof RegistrationPolicy) {
            throw new RuntimeException('kit.mcp.registration_policy must name a class implementing '.RegistrationPolicy::class.'.');
        }

        return $policy;
    }

    /** @param  list<string>  $supported */
    private static function unsupported(array $body, string $field, array $supported): ?string
    {
        if (! array_key_exists($field, $body)) {
            return null;
        }
        $asked = $body[$field];
        if (! is_array($asked) || $asked === [] || array_diff($asked, $supported) !== []) {
            return $field.' may only list: '.implode(', ', $supported).'.';
        }

        return null;
    }

    private static function refuse(string $error, string $description, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $error, 'error_description' => $description], $status)->header('Cache-Control', 'no-store');
    }
}
