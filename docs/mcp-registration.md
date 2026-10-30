# MCP client registration and discovery

An assistant that has never met your app needs an OAuth client before it can ask for consent. The kit's `mcp` feature serves the discovery documents an MCP client reads to find the authorization server, and, when a host turns it on, lets the assistant register itself as a public client (OAuth 2.0 Dynamic Client Registration, RFC 7591). Everything here is the kit's own code: `Mcp::oauthRoutes()` from laravel/mcp is not called, so the metadata advertises exactly what the kit implements and nothing more.

Verified against the MCP authorization specification of 2026-07-28 and Claude's connector requirements. That specification prefers Client ID Metadata Documents (CIMD) and keeps dynamic registration for compatibility; the kit does not implement CIMD yet and does not advertise it, so Claude registers through this endpoint.

## Discovery

Two documents, at the root well-known paths and at the path-suffixed ones an MCP client tries for a server below the root (`/mcp`), under laravel/mcp's route names so its 401 challenge points at them:

- `GET /.well-known/oauth-authorization-server[/{path}]` (RFC 8414): the issuer, the authorize, token and (when registration is on) registration endpoints, `response_types_supported: ["code"]`, `grant_types_supported: ["authorization_code", "refresh_token"]`, `code_challenge_methods_supported: ["S256"]`, `token_endpoint_auth_methods_supported: ["none"]` and `scopes_supported`, the kit's token scopes.
- `GET /.well-known/oauth-protected-resource[/{path}]` (RFC 9728): `resource` (the URL of that path, which is what an assistant's user typed), `authorization_servers` (the issuer), `scopes_supported`, `bearer_methods_supported: ["header"]` and `resource_name` (the app's name).

The issuer is `mcp.authorization_server` when the host sets it (laravel/mcp's config), else the app's URL. It must be this app's own origin, since the authorize and token endpoints advertised beside it are Passport's here: an issuer on another origin is refused when the metadata is read, rather than advertised with endpoints it does not serve. Not advertised, because not implemented: CIMD, confidential client authentication, RFC 7592 client management, the `iss` authorization response parameter, revocation and introspection.

The 401 an unauthenticated request to `/mcp` gets carries `WWW-Authenticate: Bearer realm="mcp", resource_metadata="…/.well-known/oauth-protected-resource/mcp", scope="mcp:use"`: laravel/mcp writes the pointer, and the kit's `AdvertiseScope` middleware (prepended to the HTTP kernel, acting on MCP routes only) adds the scope an assistant should ask for first.

## Registration

Off by default: `POST /oauth/register` does not exist and the metadata names no `registration_endpoint`. Turn it on with

```php
// config/kit.php
'mcp' => ['registration' => true],   // or KIT_MCP_REGISTRATION=true
```

The request is a JSON object (RFC 7591 §2). The kit reads `redirect_uris` (1 to 10), `client_name` (optional; the first redirect host otherwise), `application_type` (`web`, the default, or `native`), and, if sent, `grant_types`, `response_types`, `token_endpoint_auth_method` and `scope`, which must be within what the metadata advertises. Other fields are ignored. A public client only: `token_endpoint_auth_method` other than `none` is refused, and no secret is ever issued.

The answer (201) is the client information: `client_id`, `client_id_issued_at`, `client_name`, `redirect_uris`, `grant_types`, `response_types`, `token_endpoint_auth_method: "none"`, `application_type` and `scope` (the kit's token scopes). No `client_secret`, no `registration_access_token`, no `registration_client_uri`. Refusals are 400 with `error` (`invalid_redirect_uri` or `invalid_client_metadata`) and `error_description`.

The client is a Passport authorization-code client with no owner, no secret and no provider: the same row laravel/mcp's own registration stored, read by the same consent and token paths. A client registered before this feature keeps working as it is; no Passport column is added, and nothing is re-registered. What tells a registered client from a host's own public client is the kit's `kit_mcp_registrations` row, written with it (the mcp feature's migration `0001_01_02_000603`; without the table, registration answers 500 and the command refuses). `kit:mcp:clients` lists these clients, `--revoke=<id>` revokes one with every token it holds, and `--prune-unused=<days>` (with `--dry-run` to look first) revokes those with no token issued in that many days, since an assistant registers a new client on every fresh connection. Only recorded clients are touched: a client registered before the record existed (laravel/mcp's registration) is adopted by hand, `kit:mcp:clients --adopt=<id>`, one at a time; the kit never marks one on its own.

The response's `scope` is what the client asked to register with (within the scopes the metadata advertises), else the base `mcp:use`; it is the scope the client should request, not a grant. What a token holds is decided at consent.

### Redirect URIs

Checked at registration, each URI must be one of:

- `https` with a host;
- `http` to a loopback host, `localhost`, `127.0.0.1` or `[::1]`, with any port or none (a native client such as Claude Code listens on a port it picks per session, RFC 8252 §7.3);
- a private-use scheme the host lists in `kit.mcp.redirect_schemes` (`['cursor', 'vscode']`, say), matched exactly. The list is empty by default.

Never: a wildcard, a fragment, userinfo, whitespace, plain `http` to any other host, or `javascript`, `data`, `blob` and `file`, listed or not.

At authorization and token time Passport matches the request's `redirect_uri` against the registered list exactly, as RFC 6749 §4.1.2.1 requires, with one allowance: a loopback URI matches a registered loopback URI whatever its port (`LoopbackClientRepository`, Passport's client bridge; league/oauth2-server does this for the IP literals, the kit adds `localhost`). An `https` URI on a different port, or a loopback URI on a different path, is refused.

### PKCE

Every authorization request must carry a `code_challenge` of 43 to 128 unreserved characters with `code_challenge_method=S256` (`RequirePkce`, on Passport's authorize route). A missing challenge or the `plain` method is refused with 400 before league sees the request and never redirected, since the redirect URI is not verified at that point.

### Rate limit and policy

Registration is limited per IP, `kit.mcp.registration_per_minute` (10) through the named limiter `kit-mcp-register`; over it, 429. The key is the request's IP as Laravel resolves it, so the limit is only as good as the app's trusted-proxy configuration: behind a proxy or load balancer, trust exactly those proxies (`TrustProxies`), never `*` on a host that is reachable directly, or a caller can pick its own `X-Forwarded-For` and its own bucket. A host may also refuse registrations:

```php
// config/kit.php
'mcp' => ['registration_policy' => App\Support\AssistantRegistrationPolicy::class],
```

```php
final class AssistantRegistrationPolicy implements Tey\Kit\Features\Mcp\Registration\RegistrationPolicy
{
    public function refuse(Registration $registration): ?string
    {
        return $registration->loopbackOnly() && app()->isProduction() ? 'Only hosted assistants may register here.' : null;
    }
}
```

The policy sees the client name, the redirect URIs, the application type, the IP and the raw metadata, after the kit's own checks. A reason is answered as 403 `access_denied`; null lets the registration through. A policy that cannot run, a class that is not a `RegistrationPolicy` or one that throws, is reported and refuses the same way (403 with a generic reason); it never lets a registration through.

## The consent page

Passport has verified the client and its `redirect_uri` before the kit's consent page renders. The page shows the redirect host the person will be sent to, and for a client that registered itself (or an owner-less public client shaped like one, before the registration record existed) says "Unverified app": the client's name is its own claim, so a `client_name` of "Claude" with a redirect to another host is shown beside that host, for the person to deny. `AuthorizeViewModel::redirect_host()` and `registered()` carry both.

The label is deliberately conservative: it applies to every recorded registration, and also to any public client with no owner, no secret and no provider, whether recorded or not, because that is exactly the row a registration makes, and clients registered before the `kit_mcp_registrations` table existed (laravel/mcp's registration) have no record. A host's own first-party public client shaped that way gets the label too. To drop it, give that client an owner (Passport's `owner` morph, the usual shape for a first-party client) or make it confidential; do not `--adopt` it, since adoption records it as a registration and `--prune-unused` would then be allowed to revoke it. The kit keeps no separate "verified" flag.

## The OAuth error page

When an authorization request cannot go back to its app (an unknown client, a `redirect_uri` the client never registered, PKCE missing or not S256), Passport answers a bare JSON error. A person in a browser gets a readable 400 page instead (`BrandOAuthErrors`, on the authorize route); a caller asking for JSON keeps Passport's answer, and an error Passport sends back to a verified redirect URI (`invalid_scope`, `access_denied`) still goes there. The page is the kit's `kit::mcp.error` view by default. To brand it:

```php
// a Blade view of your own, receiving title, error and description
'mcp' => ['oauth_error_view' => 'errors.oauth'],

// or your own response, say an Inertia error page; in a service provider's boot
Tey\Kit\Features\Mcp\OAuthErrorPage::using(fn (Request $request, string $error, ?string $description) => Inertia::render('Error/Page', ['status' => 400])->toResponse($request));
```

The closure wins over the view; either way the status is 400.

## What Claude needs

Claude's hosted apps register with the redirect URI `https://claude.ai/api/mcp/auth_callback`; Claude Code uses a loopback redirect on an ephemeral port. Both are public clients sending S256 PKCE, both read the protected resource metadata from the 401 (its `resource` must equal the URL the user typed) and the authorization server metadata at the well-known path, and both expect `registration_endpoint` or CIMD. Discovery, registration and token endpoints get 10 seconds. Refresh tokens rotate (league revokes the one used). All of this is what the kit serves.

## Checks

`composer test:mcp-registration` covers both discovery documents (root and `/mcp`), every route name laravel/mcp expects, the scope on the 401 challenge, registration for a hosted and a native client, each redirect refusal, the custom-scheme list (including a listed `javascript` still refused), loopback port matching for `localhost` and `127.0.0.1` through consent and the code exchange, exact `https` matching, the refresh rotation, the policy (refusal, pass-through, a broken policy class), the rate limit, PKCE (missing, `plain`, short, as a page and as JSON), the 400 page and a host's renderer, revoking and pruning, a client stored as laravel/mcp stored it, and, in a second process, registration off. No browser render, no live assistant, no consumer app.
