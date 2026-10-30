<?php

// MCP client registration: OAuth discovery (RFC 8414, RFC 9728), dynamic client registration (RFC 7591) for public clients with the redirect URI rules, loopback matching,
// PKCE S256, the rate limit, the host's policy, the 400 page and its override, revoking and pruning, and a client registered before this feature, through the kit's routes
// and Passport's real authorize, approve and token routes, with real Passport, Concierge and kit tables, on SQLite memory with synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Token;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Registry;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Mcp\ConsentGate;
use Tey\Kit\Features\Mcp\Http\Middleware\BindMcpConnection;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function httpCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The host's CSRF middleware, as a web group runs it; Laravel skips it under unit tests, which this check is not for CSRF's purposes.
final class EnforcedCsrf extends Illuminate\Foundation\Http\Middleware\PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}

// A host's registration policy: refuses one client name, and keeps what it was shown.
final class BlockedNamesPolicy implements Tey\Kit\Features\Mcp\Registration\RegistrationPolicy
{
    public static ?Tey\Kit\Features\Mcp\Registration\Registration $seen = null;

    public function refuse(Tey\Kit\Features\Mcp\Registration\Registration $registration): ?string
    {
        self::$seen = $registration;

        return $registration->clientName === 'Blocked' ? 'Blocked is not welcome.' : null;
    }
}

final class ThrowingPolicy implements Tey\Kit\Features\Mcp\Registration\RegistrationPolicy
{
    public function refuse(Tey\Kit\Features\Mcp\Registration\Registration $registration): ?string
    {
        throw new LogicException('policy broke');
    }
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-mcp-registration-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true], 'mcp' => ['registration' => getenv('KIT_MCP_REGISTRATION_CHECK') !== 'off']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Inertia\ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach ($stubs as $file) {
        (require $file)->up();
    }

    $app['router']->middlewareGroup('web', [Illuminate\Session\Middleware\StartSession::class, EnforcedCsrf::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);
    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $owner = HttpUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'email_verified_at' => now()]);
    ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => $owner->assignRole('owner'), [$owner]);
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);

    // Passport's signing keys, made for this run; the throttle alias a host has.
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    $config->set('passport.private_key', $private);
    $config->set('passport.public_key', openssl_pkey_get_details($key)['key']);
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);
    Illuminate\Support\Facades\RateLimiter::for('api', fn () => Illuminate\Cache\RateLimiting\Limit::none());
    // Room for every registration below; the rate limit has its own section.
    $config->set('kit.mcp.registration_per_minute', 1000);

    // One browser: the session cookie is carried from request to request, and the guard is signed in as $as (or signed out).
    $cookies = [];
    $send = function (?HttpUser $as, string $method, string $uri, array $input = [], array $headers = [], ?string $json = null) use ($app, &$cookies): Symfony\Component\HttpFoundation\Response {
        $guard = $app['auth']->guard('web');
        $as ? $guard->setUser($as) : $guard->forgetUser();
        $request = Request::create('https://kit.test'.$uri, $method, $input, $cookies, [], array_merge(['HTTP_ACCEPT' => 'text/html'], $json === null ? [] : ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $headers), $json);
        $app->instance('request', $request);
        // Passport memoises a client lookup with once(), per process; a real host serves each request in its own.
        Illuminate\Support\Once::flush();
        try {
            $response = $app['router']->dispatch($request);
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $response = new Illuminate\Http\Response('', $error->getStatusCode(), $error->getHeaders());
        } catch (Illuminate\Session\TokenMismatchException) {
            $response = new Illuminate\Http\Response('', 419);
        } catch (Laravel\Passport\Exceptions\OAuthServerException $error) {
            $response = $error->render($request);
        } catch (Illuminate\Auth\AuthenticationException|Laravel\Passport\Exceptions\AuthenticationException) {
            $response = new Illuminate\Http\Response('', 401);
        }
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    };
    $json = fn (Symfony\Component\HttpFoundation\Response $response): array => (array) json_decode((string) $response->getContent(), true);
    $register = fn (array $body) => $send(null, 'POST', '/oauth/register', [], [], json_encode($body));
    $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml'];
    $verifier = bin2hex(random_bytes(32));
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $authorize = fn (string $clientId, string $redirect, array $override = []) => '/oauth/authorize?'.http_build_query([...['client_id' => $clientId, 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => 'mcp:use', 'state' => 'st4te', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'], ...$override]);
    $query = function (Symfony\Component\HttpFoundation\Response $response): array {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    };
    // The whole flow for a client: consent page, approve into Acme, the code exchanged at the redirect URI asked for; the access token, or the step that refused.
    $connect = function (string $clientId, string $redirect) use ($send, $owner, $workspace, $authorize, $inertia, $query, $verifier, $json): array {
        $page = $send($owner, 'GET', $authorize($clientId, $redirect), [], $inertia);
        if ($page->isRedirect() && isset($query($page)['code'])) {
            // Consent skipped: the person already holds a live connection for this client in the workspace.
            $approved = $page;
        } elseif ($page->getStatusCode() !== 200) {
            return ['step' => 'authorize', 'status' => $page->getStatusCode(), 'body' => (string) $page->getContent()];
        } else {
            $props = $json($page)['props'];
            $approved = $send($owner, 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token'], 'workspace' => $workspace->id]);
            if (! $approved->isRedirect()) {
                return ['step' => 'approve', 'status' => $approved->getStatusCode()];
            }
        }
        $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => $redirect, 'code' => $query($approved)['code'] ?? '', 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);

        return ['step' => 'token', 'status' => $exchange->getStatusCode(), 'location' => (string) $approved->headers->get('Location'), 'token' => $json($exchange)['access_token'] ?? null, 'refresh' => $json($exchange)['refresh_token'] ?? null];
    };
    $claudeRedirect = 'https://claude.ai/api/mcp/auth_callback';

    if (getenv('KIT_MCP_REGISTRATION_CHECK') === 'off') {
        // Booted with registration off (the default): no route, no registration_endpoint, discovery otherwise unchanged.
        httpCheck($register(['client_name' => 'Claude', 'redirect_uris' => [$claudeRedirect]])->getStatusCode() === 404, 'with kit.mcp.registration off, POST /oauth/register does not exist (404)');
        $meta = $json($send(null, 'GET', '/.well-known/oauth-authorization-server'));
        httpCheck(! array_key_exists('registration_endpoint', $meta) && $meta['issuer'] === 'https://kit.test' && $meta['code_challenge_methods_supported'] === ['S256'], 'with registration off, the authorization server metadata names no registration_endpoint and still advertises S256');
        echo "Registration-off checks passed.\n";
        exit(0);
    }

    // Discovery: RFC 8414 and RFC 9728, root and path-suffixed, advertising only what the kit implements.
    $as = $json($send(null, 'GET', '/.well-known/oauth-authorization-server'));
    httpCheck($as === [
        'issuer' => 'https://kit.test', 'authorization_endpoint' => 'https://kit.test/oauth/authorize', 'token_endpoint' => 'https://kit.test/oauth/token', 'registration_endpoint' => 'https://kit.test/oauth/register',
        'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code', 'refresh_token'], 'code_challenge_methods_supported' => ['S256'], 'token_endpoint_auth_methods_supported' => ['none'], 'scopes_supported' => $scopes = array_keys($registry->tokenScopes()),
    ] && $scopes[0] === 'mcp:use' && in_array('mcp:act', $scopes, true), 'the authorization server metadata (RFC 8414) names the issuer, the three endpoints, code, authorization_code and refresh_token, S256 only, public clients only, and the kit scopes; nothing else (no CIMD, no client secrets, no RFC 7592)');
    httpCheck($json($send(null, 'GET', '/.well-known/oauth-authorization-server/mcp')) === $as, 'the path-suffixed authorization server metadata is the same document');
    $pr = $json($send(null, 'GET', '/.well-known/oauth-protected-resource/mcp'));
    httpCheck($pr === ['resource' => 'https://kit.test/mcp', 'authorization_servers' => ['https://kit.test'], 'scopes_supported' => $scopes, 'bearer_methods_supported' => ['header'], 'resource_name' => 'Kit'], 'the protected resource metadata (RFC 9728) for /mcp names that resource exactly, this issuer, the scopes and the bearer header');
    httpCheck($json($send(null, 'GET', '/.well-known/oauth-protected-resource'))['resource'] === 'https://kit.test', 'the root protected resource metadata names the root');
    $config->set('mcp.authorization_server', 'https://kit.test');
    httpCheck($json($send(null, 'GET', '/.well-known/oauth-authorization-server'))['issuer'] === 'https://kit.test' && $json($send(null, 'GET', '/.well-known/oauth-protected-resource'))['authorization_servers'] === ['https://kit.test'], 'a host\'s mcp.authorization_server, on this origin, is the issuer in both documents');
    $config->set('mcp.authorization_server', 'https://kit.test/');
    httpCheck($json($send(null, 'GET', '/.well-known/oauth-authorization-server'))['issuer'] === 'https://kit.test', 'a trailing slash on the issuer is trimmed');
    $config->set('mcp.authorization_server', 'https://elsewhere.test');
    httpCheck($send(null, 'GET', '/.well-known/oauth-authorization-server')->getStatusCode() === 500 && $send(null, 'GET', '/.well-known/oauth-protected-resource')->getStatusCode() === 500, 'an issuer on another origin is refused (500) rather than advertised with endpoints it does not serve');
    $config->set('mcp.authorization_server', null);
    foreach (['mcp.oauth.protected-resource', 'mcp.oauth.protected-resource.nested', 'mcp.oauth.authorization-server', 'mcp.oauth.authorization-server.nested', 'mcp.oauth.register'] as $name) {
        httpCheck($app['router']->has($name), 'the kit owns laravel/mcp\'s route name '.$name);
    }

    // The 401 challenge: laravel/mcp's resource_metadata pointer, with the scope an assistant should ask for added outside it.
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    httpCheck($kernel->hasMiddleware(Tey\Kit\Features\Mcp\Http\Middleware\AdvertiseScope::class), 'AdvertiseScope is prepended to the HTTP kernel');
    $mcpRequest = Request::create('https://kit.test/mcp', 'POST');
    $mcpRequest->setRouteResolver(fn () => $app['router']->getRoutes()->match($mcpRequest));
    $challenged = (new Tey\Kit\Features\Mcp\Http\Middleware\AdvertiseScope)->handle($mcpRequest, fn ($request) => (new Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader)->handle($request, fn () => response('', 401)));
    httpCheck($challenged->headers->get('WWW-Authenticate') === 'Bearer realm="mcp", resource_metadata="https://kit.test/.well-known/oauth-protected-resource/mcp", scope="mcp:use"', 'the 401 on /mcp carries resource_metadata for /mcp and scope="mcp:use"');
    $plainRequest = Request::create('https://kit.test/.well-known/oauth-authorization-server', 'GET');
    $plainRequest->setRouteResolver(fn () => $app['router']->getRoutes()->match($plainRequest));
    httpCheck((new Tey\Kit\Features\Mcp\Http\Middleware\AdvertiseScope)->handle($plainRequest, fn () => response('', 401))->headers->get('WWW-Authenticate') === null, 'a 401 off the MCP routes gets no challenge from the kit');

    // Registration (RFC 7591): a public client, the row Passport's consent and token paths read, no secret and no management credentials in the answer.
    $before = Client::query()->count();
    $registered = $register(['client_name' => 'Claude', 'redirect_uris' => [$claudeRedirect], 'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none', 'application_type' => 'web', 'scope' => 'mcp:use', 'logo_uri' => 'https://claude.ai/logo.png', 'software_id' => 'claude']);
    $body = $json($registered);
    $clientId = (string) ($body['client_id'] ?? '');
    $row = Client::query()->find($clientId);
    httpCheck($registered->getStatusCode() === 201 && $row !== null && Client::query()->count() === $before + 1, 'a registration makes one Passport client (201)');
    httpCheck(array_keys($body) === ['client_id', 'client_id_issued_at', 'client_name', 'redirect_uris', 'grant_types', 'response_types', 'token_endpoint_auth_method', 'application_type', 'scope'] && $body['client_name'] === 'Claude' && $body['redirect_uris'] === [$claudeRedirect] && $body['grant_types'] === ['authorization_code', 'refresh_token'] && $body['response_types'] === ['code'] && $body['token_endpoint_auth_method'] === 'none' && $body['application_type'] === 'web' && $body['scope'] === 'mcp:use' && is_int($body['client_id_issued_at']), 'the answer is the RFC 7591 client information: id, issued_at, the metadata as registered, the kit scopes; no client_secret, no registration_access_token, no registration_client_uri');
    httpCheck($registered->headers->get('Cache-Control') === 'no-store, private' || str_contains((string) $registered->headers->get('Cache-Control'), 'no-store'), 'the client information is not cacheable');
    httpCheck($row->secret === null && $row->owner_id === null && $row->provider === null && ! $row->confidential() && $row->redirect_uris === [$claudeRedirect] && $row->grant_types === ['authorization_code', 'refresh_token'] && ! $row->revoked, 'the row is a public authorization-code client with no owner, secret or provider, the shape laravel/mcp\'s registration stored');
    httpCheck($json($register(['redirect_uris' => [$claudeRedirect]]))['client_name'] === 'claude.ai', 'with no client_name, the first redirect host names the client');
    httpCheck($json($register(['redirect_uris' => [$claudeRedirect]]))['scope'] === 'mcp:use' && $json($register(['redirect_uris' => [$claudeRedirect], 'scope' => 'mcp:act mcp:use']))['scope'] === 'mcp:act mcp:use', 'the answer\'s scope is what the client asked to register with, else mcp:use; never every supported scope');
    httpCheck(DB::table('kit_mcp_registrations')->where('oauth_client_id', $clientId)->where('application_type', 'web')->where('ip', '127.0.0.1')->whereNull('adopted_at')->exists(), 'the registration is recorded with its application type and IP');
    httpCheck(($json($register(['redirect_uris' => [$claudeRedirect], 'scope' => '']))['error'] ?? null) === 'invalid_client_metadata', 'an empty scope is refused');

    // Registration refusals (RFC 7591 §3.2.2): the redirect URI rules, and metadata the kit does not implement.
    $refusals = [
        'an http redirect to a public host' => [['redirect_uris' => ['http://evil.test/callback']], 'invalid_redirect_uri'],
        'a wildcard' => [['redirect_uris' => ['https://*.claude.ai/callback']], 'invalid_redirect_uri'],
        'a fragment' => [['redirect_uris' => ['https://claude.ai/callback#x']], 'invalid_redirect_uri'],
        'userinfo' => [['redirect_uris' => ['https://user:pw@claude.ai/callback']], 'invalid_redirect_uri'],
        'whitespace' => [['redirect_uris' => ['https://claude.ai/call back']], 'invalid_redirect_uri'],
        'a javascript URI' => [['redirect_uris' => ['javascript:alert(1)']], 'invalid_redirect_uri'],
        'a data URI' => [['redirect_uris' => ['data:text/html,hi']], 'invalid_redirect_uri'],
        'a custom scheme not allowed by the host' => [['redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback']], 'invalid_redirect_uri'],
        'a relative URI' => [['redirect_uris' => ['/callback']], 'invalid_redirect_uri'],
        'https with no host' => [['redirect_uris' => ['https:///callback']], 'invalid_redirect_uri'],
        'no redirect_uris' => [['client_name' => 'x'], 'invalid_redirect_uri'],
        'an empty list' => [['redirect_uris' => []], 'invalid_redirect_uri'],
        'eleven redirect URIs' => [['redirect_uris' => array_map(fn (int $i) => 'https://claude.ai/cb'.$i, range(1, 11))], 'invalid_redirect_uri'],
        'a non-string redirect URI' => [['redirect_uris' => [['https://claude.ai/callback']]], 'invalid_redirect_uri'],
        'a confidential client' => [['redirect_uris' => [$claudeRedirect], 'token_endpoint_auth_method' => 'client_secret_basic'], 'invalid_client_metadata'],
        'the client_credentials grant' => [['redirect_uris' => [$claudeRedirect], 'grant_types' => ['client_credentials']], 'invalid_client_metadata'],
        'the token response type' => [['redirect_uris' => [$claudeRedirect], 'response_types' => ['token']], 'invalid_client_metadata'],
        'an unknown application_type' => [['redirect_uris' => [$claudeRedirect], 'application_type' => 'service'], 'invalid_client_metadata'],
        'a scope the kit does not have' => [['redirect_uris' => [$claudeRedirect], 'scope' => 'mcp:use admin:all'], 'invalid_client_metadata'],
        'a client_name with control characters' => [['redirect_uris' => [$claudeRedirect], 'client_name' => "Cla\x00ude"], 'invalid_client_metadata'],
        'a JSON list body' => [[$claudeRedirect], 'invalid_client_metadata'],
    ];
    $before = Client::query()->count();
    foreach ($refusals as $case => [$payload, $error]) {
        $response = $register($payload);
        httpCheck($response->getStatusCode() === 400 && ($json($response)['error'] ?? null) === $error && is_string($json($response)['error_description'] ?? null), $case.' is refused with 400 '.$error.' ('.$response->getStatusCode().' '.substr((string) $response->getContent(), 0, 160).')');
    }
    $notJson = $send(null, 'POST', '/oauth/register', [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], 'redirect_uris=x');
    httpCheck($notJson->getStatusCode() === 400 && ($json($notJson)['error'] ?? null) === 'invalid_client_metadata', 'a body that is not a JSON object is refused with invalid_client_metadata');
    httpCheck(Client::query()->count() === $before, 'no refusal made a client');

    // Loopback redirects for native clients (RFC 8252 §7.3): registered without a port, matched at authorize and token whatever the port, for localhost as well as 127.0.0.1.
    $native = $json($register(['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost/callback', 'http://127.0.0.1/callback'], 'application_type' => 'native']));
    httpCheck(($native['application_type'] ?? null) === 'native' && $native['redirect_uris'] === ['http://localhost/callback', 'http://127.0.0.1/callback'], 'loopback http redirect URIs register for a native client');
    $local = $connect($native['client_id'], 'http://localhost:51234/callback');
    httpCheck($local['step'] === 'token' && $local['status'] === 200 && is_string($local['token']) && str_starts_with($local['location'], 'http://localhost:51234/callback?'), 'http://localhost on an ephemeral port passes consent, the redirect and the code exchange');
    $ip = $connect($native['client_id'], 'http://127.0.0.1:60000/callback');
    httpCheck($ip['step'] === 'token' && $ip['status'] === 200, 'http://127.0.0.1 on another port does too');
    $wrongPath = $connect($native['client_id'], 'http://localhost:51234/other');
    httpCheck($wrongPath['step'] === 'authorize' && $wrongPath['status'] === 400 && str_contains($wrongPath['body'], 'invalid_client'), 'a loopback redirect with another path is refused: only the port is free');
    $notLoopback = $connect($native['client_id'], 'https://localhost.evil.test/callback');
    httpCheck($notLoopback['step'] === 'authorize' && $notLoopback['status'] === 400, 'a non-loopback host that merely starts with localhost is refused');
    $webClient = $connect($clientId, 'https://claude.ai:8443/api/mcp/auth_callback');
    httpCheck($webClient['step'] === 'authorize' && $webClient['status'] === 400, 'an https redirect still matches exactly: a different port is refused');

    // The consent page names the verified redirect host and marks a self-registered client as unverified.
    $consent = $send($owner, 'GET', $authorize($clientId, $claudeRedirect), [], $inertia);
    $consentProps = $json($consent)['props'] ?? [];
    httpCheck($consent->getStatusCode() === 200 && ($consentProps['redirect_host'] ?? null) === 'claude.ai' && ($consentProps['registered'] ?? null) === true && ($consentProps['client'] ?? null) === 'Claude', 'the consent page carries the redirect host (claude.ai) and marks the registered client as unverified');
    $lookalike = $json($register(['client_name' => 'Claude', 'redirect_uris' => ['https://evil.example/cb']]));
    $lookalikeConsent = $json($send($owner, 'GET', $authorize($lookalike['client_id'], 'https://evil.example/cb'), [], $inertia))['props'] ?? [];
    httpCheck(($lookalikeConsent['redirect_host'] ?? null) === 'evil.example' && ($lookalikeConsent['registered'] ?? null) === true, 'a lookalike named Claude is shown with its real redirect host, evil.example, and as unverified');
    httpCheck(str_contains((string) file_get_contents(dirname(__DIR__).'/resources/features/mcp/pages/Mcp/Authorize/Page.vue.stub'), 'data-consent-unverified') && str_contains((string) file_get_contents(dirname(__DIR__).'/resources/features/mcp/pages/Mcp/Authorize/Page.vue.stub'), 'redirect_host'), 'the consent page stub renders the host and the unverified note');
    $fragment = $send($owner, 'GET', $authorize($clientId, $claudeRedirect.'#frag'), [], $inertia);
    httpCheck($fragment->getStatusCode() === 400 && ! $fragment->isRedirect() && str_contains((string) $fragment->getContent(), 'fragment'), 'a redirect_uri with a fragment is refused at authorize with the 400 page, never matched');

    // The registered client connects: consent, approve, the code exchange, a refresh that rotates, and the token opens MCP.
    $flow = $connect($clientId, $claudeRedirect);
    httpCheck($flow['step'] === 'token' && $flow['status'] === 200 && is_string($flow['token']) && is_string($flow['refresh']) && str_starts_with($flow['location'], $claudeRedirect.'?'), 'the registered client gets consent, a code at its redirect URI and an access token with a refresh token');
    $refreshed = $send(null, 'POST', '/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $flow['refresh']], ['HTTP_ACCEPT' => 'application/json']);
    $again = $send(null, 'POST', '/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $flow['refresh']], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($refreshed->getStatusCode() === 200 && is_string($json($refreshed)['refresh_token'] ?? null) && $json($refreshed)['refresh_token'] !== $flow['refresh'] && $again->getStatusCode() === 400 && ($json($again)['error'] ?? null) === 'invalid_grant', 'a refresh rotates the refresh token (the old one is invalid_grant afterwards), as OAuth 2.1 requires for a public client');
    $live = Token::query()->where('client_id', $clientId)->where('user_id', $owner->id)->where('revoked', false)->get();
    httpCheck($live->count() === 1 && $live->first()->scopes === ['mcp:use'] && DB::table('connections')->where('oauth_token_id', $live->first()->id)->whereNull('revoked_at')->exists(), 'after the refresh one access token is live for the client, with the scope asked for, and the connection has moved onto it');

    // A client registered before the kit's registration (the row laravel/mcp's registration stored, provider null, no scopes column) keeps working as it is.
    $legacy = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Claude', 'secret' => null, 'provider' => null, 'redirect_uris' => [$claudeRedirect], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false]);
    $legacyFlow = $connect((string) $legacy->getKey(), $claudeRedirect);
    httpCheck($legacyFlow['step'] === 'token' && $legacyFlow['status'] === 200 && is_string($legacyFlow['token']), 'a client registered before this feature, stored as laravel/mcp stored it, authorizes and exchanges a token unchanged: no migration, no re-registration');
    httpCheck(! DB::table('kit_mcp_registrations')->where('oauth_client_id', $legacy->getKey())->exists(), 'it is not a recorded registration: the kit never marks a client on its own');
    httpCheck(Schema::getColumnListing('oauth_clients') === ['id', 'owner_type', 'owner_id', 'name', 'secret', 'provider', 'redirect_uris', 'grant_types', 'revoked', 'created_at', 'updated_at'], 'the oauth_clients table is Passport\'s as shipped: the kit adds no column');

    // PKCE: S256 or nothing, refused before league sees the request, with no redirect.
    $noPkce = $send($owner, 'GET', $authorize($clientId, $claudeRedirect, ['code_challenge' => null, 'code_challenge_method' => null]), [], $inertia);
    httpCheck($noPkce->getStatusCode() === 400 && ! $noPkce->isRedirect() && str_contains((string) $noPkce->getContent(), 'code_challenge'), 'an authorization request without PKCE is refused with the 400 page, never redirected');
    $plain = $send($owner, 'GET', $authorize($clientId, $claudeRedirect, ['code_challenge' => $verifier, 'code_challenge_method' => 'plain']), [], $inertia);
    httpCheck($plain->getStatusCode() === 400 && ! $plain->isRedirect() && str_contains((string) $plain->getContent(), 'S256'), 'the plain method is refused: S256 only');
    $plainJson = $send($owner, 'GET', $authorize($clientId, $claudeRedirect, ['code_challenge_method' => 'plain']), [], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($plainJson->getStatusCode() === 400 && ($json($plainJson)['error'] ?? null) === 'invalid_request', 'a JSON caller gets the refusal as JSON invalid_request');
    $short = $send($owner, 'GET', $authorize($clientId, $claudeRedirect, ['code_challenge' => 'short']), [], $inertia);
    httpCheck($short->getStatusCode() === 400, 'a code_challenge outside 43 to 128 unreserved characters is refused');

    // The 400 page: the kit's by default, the host's through OAuthErrorPage::using.
    $unknown = $send($owner, 'GET', $authorize((string) Str::uuid(), $claudeRedirect), [], $inertia);
    httpCheck($unknown->getStatusCode() === 400 && str_contains((string) $unknown->getContent(), 'cannot continue') && str_contains((string) $unknown->getContent(), 'invalid_client'), 'an unknown client gets the kit\'s 400 page naming invalid_client');
    Tey\Kit\Features\Mcp\OAuthErrorPage::using(fn (Request $request, string $error, ?string $description) => response('BRANDED '.$error.' '.$request->path()));
    $branded = $send($owner, 'GET', $authorize((string) Str::uuid(), $claudeRedirect), [], $inertia);
    httpCheck($branded->getStatusCode() === 400 && (string) $branded->getContent() === 'BRANDED invalid_client oauth/authorize', 'a host\'s renderer makes the page, with the error and the request, at 400');
    $brandedJson = $send($owner, 'GET', $authorize((string) Str::uuid(), $claudeRedirect), [], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($brandedJson->getStatusCode() === 401 && ($json($brandedJson)['error'] ?? null) === 'invalid_client', 'a JSON caller still gets Passport\'s own answer');
    Tey\Kit\Features\Mcp\OAuthErrorPage::using(null);
    $denied = $send($owner, 'GET', $authorize($clientId, $claudeRedirect, ['scope' => 'nope']), [], $inertia);
    httpCheck($denied->isRedirect() && str_starts_with((string) $denied->headers->get('Location'), $claudeRedirect.'?') && ($query($denied)['error'] ?? null) === 'invalid_scope', 'an error for a verified redirect URI still goes back to the app (invalid_scope), not to the page');

    // A host's policy: a reason refuses with 403 access_denied, after the kit's own checks.
    $config->set('kit.mcp.registration_policy', BlockedNamesPolicy::class);
    $blocked = $register(['client_name' => 'Blocked', 'redirect_uris' => [$claudeRedirect]]);
    httpCheck($blocked->getStatusCode() === 403 && $json($blocked) === ['error' => 'access_denied', 'error_description' => 'Blocked is not welcome.'], 'the policy refuses a registration with 403 access_denied and its reason');
    httpCheck($register(['client_name' => 'Welcome', 'redirect_uris' => [$claudeRedirect]])->getStatusCode() === 201, 'the policy lets another through');
    httpCheck(BlockedNamesPolicy::$seen instanceof Tey\Kit\Features\Mcp\Registration\Registration && BlockedNamesPolicy::$seen->ip === '127.0.0.1' && BlockedNamesPolicy::$seen->applicationType === 'web' && ! BlockedNamesPolicy::$seen->loopbackOnly(), 'the policy sees the client name, redirect URIs, application type and IP');
    $config->set('kit.mcp.registration_policy', stdClass::class);
    $before = Client::query()->count();
    $broken = $register(['client_name' => 'x', 'redirect_uris' => [$claudeRedirect]]);
    httpCheck($broken->getStatusCode() === 403 && $json($broken) === ['error' => 'access_denied', 'error_description' => 'Registration is not allowed here.'] && Client::query()->count() === $before, 'a policy class that is not a RegistrationPolicy refuses with 403 access_denied (reported) and registers nothing');
    $config->set('kit.mcp.registration_policy', ThrowingPolicy::class);
    $thrown = $register(['client_name' => 'x', 'redirect_uris' => [$claudeRedirect]]);
    httpCheck($thrown->getStatusCode() === 403 && ($json($thrown)['error'] ?? null) === 'access_denied' && Client::query()->count() === $before, 'a policy that throws refuses with 403 access_denied and registers nothing');
    $config->set('kit.mcp.registration_policy', 'App\\Nope\\MissingPolicy');
    httpCheck($register(['client_name' => 'x', 'redirect_uris' => [$claudeRedirect]])->getStatusCode() === 403 && Client::query()->count() === $before, 'a policy class that does not exist refuses with 403 too');
    $config->set('kit.mcp.registration_policy', null);

    // Custom schemes: only those the host lists, exactly, and never a browser scheme even when listed.
    $config->set('kit.mcp.redirect_schemes', ['cursor', 'javascript']);
    httpCheck($register(['client_name' => 'Cursor', 'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback']])->getStatusCode() === 201, 'a listed private-use scheme registers');
    httpCheck(($json($register(['redirect_uris' => ['vscode://redhat.vscode/callback']]))['error'] ?? null) === 'invalid_redirect_uri', 'an unlisted scheme is refused');
    httpCheck(($json($register(['redirect_uris' => ['javascript:alert(1)']]))['error'] ?? null) === 'invalid_redirect_uri', 'javascript is refused even when listed');
    httpCheck(($json($register(['redirect_uris' => ['http://evil.test/callback']]))['error'] ?? null) === 'invalid_redirect_uri', 'non-loopback http is refused whatever the list');
    httpCheck(($json($register(['redirect_uris' => ['cursor:']]))['error'] ?? null) === 'invalid_redirect_uri', 'a listed scheme with nothing after it is refused');
    $config->set('kit.mcp.redirect_schemes', []);

    // The rate limit: per IP, the host's number per minute.
    $config->set('kit.mcp.registration_per_minute', 2);
    Illuminate\Support\Facades\RateLimiter::clear('kit-mcp-register|127.0.0.1');
    Illuminate\Support\Facades\Cache::flush();
    $statuses = [];
    foreach (range(1, 3) as $i) {
        $statuses[] = $register(['client_name' => 'Burst '.$i, 'redirect_uris' => [$claudeRedirect]])->getStatusCode();
    }
    httpCheck($statuses === [201, 201, 429], 'the third registration in a minute from one IP is refused (429)');
    $config->set('kit.mcp.registration_per_minute', 1000);
    Illuminate\Support\Facades\Cache::flush();

    // Revoking and pruning registered clients.
    $command = new Tey\Kit\Features\Mcp\Console\ClientsCommand;
    $command->setLaravel($app);
    $run = function (array $input) use ($command): array {
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $command->run(new Symfony\Component\Console\Input\ArrayInput($input), $output);

        return [$status, $output->fetch()];
    };
    [$status, $listed] = $run([]);
    httpCheck($status === 0 && str_contains($listed, $clientId) && str_contains($listed, 'Claude Code') && ! str_contains($listed, (string) $legacy->getKey()), 'the command lists the live recorded registrations only: not the client registered before the record');
    httpCheck($run(['--revoke' => (string) $legacy->getKey()])[0] === 1 && ! Client::query()->findOrFail($legacy->getKey())->revoked, 'revoking an unrecorded client is refused: it may be the host\'s own');
    $handMade = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Hand-made public', 'secret' => null, 'provider' => null, 'redirect_uris' => ['https://app.test/cb'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
    [$status] = $run(['--revoke' => $clientId]);
    $revokedRow = Client::query()->find($clientId);
    httpCheck($status === 0 && $revokedRow->revoked && Token::query()->where('client_id', $clientId)->where('revoked', false)->doesntExist(), 'revoking a client marks it revoked and revokes every token it holds');
    httpCheck($connect($clientId, $claudeRedirect)['status'] === 400, 'a revoked client can no longer authorize');
    $afterRevoke = $send(null, 'POST', '/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $json($refreshed)['refresh_token']], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($afterRevoke->getStatusCode() === 401 || $afterRevoke->getStatusCode() === 400, 'its refresh token is refused too');
    httpCheck($run(['--revoke' => $clientId])[0] === 1, 'revoking it again fails: it is no longer a live registered client');
    $stale = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Stale', 'secret' => null, 'provider' => null, 'redirect_uris' => [$claudeRedirect], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
    $active = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Active', 'secret' => null, 'provider' => null, 'redirect_uris' => [$claudeRedirect], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
    Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $owner->id, 'client_id' => $active->id, 'name' => null, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour()]);
    $firstParty = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'First party', 'secret' => 'hashed', 'provider' => 'users', 'redirect_uris' => ['https://app.test/callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
    foreach ([$stale, $active] as $adoptable) {
        httpCheck($run(['--adopt' => (string) $adoptable->getKey()])[0] === 0 && DB::table('kit_mcp_registrations')->where('oauth_client_id', $adoptable->getKey())->whereNotNull('adopted_at')->exists(), 'adopting an earlier public client records it, marked adopted');
    }
    httpCheck($run(['--adopt' => (string) $stale->getKey()])[0] === 0 && DB::table('kit_mcp_registrations')->where('oauth_client_id', $stale->getKey())->count() === 1, 'adopting it again is a no-op');
    httpCheck($run(['--adopt' => (string) $firstParty->getKey()])[0] === 1 && $run(['--adopt' => (string) Str::uuid()])[0] === 1, 'a confidential client, or an unknown id, cannot be adopted');
    [$status, $dry] = $run(['--prune-unused' => '30', '--dry-run' => true]);
    httpCheck($status === 0 && str_contains($dry, 'Would revoke '.$stale->getKey()) && ! str_contains($dry, (string) $active->getKey()) && ! Client::query()->findOrFail($stale->getKey())->revoked, 'a dry run names the client with no token in 30 days and revokes nothing');
    [$status] = $run(['--prune-unused' => '30']);
    httpCheck($status === 0 && Client::query()->findOrFail($stale->getKey())->revoked && ! Client::query()->findOrFail($active->getKey())->revoked && ! Client::query()->findOrFail($firstParty->getKey())->revoked && ! Client::query()->findOrFail($legacy->getKey())->revoked && ! Client::query()->findOrFail($handMade->getKey())->revoked, 'pruning revokes the stale recorded client only: not one with a recent token, not a confidential client, not a recent one, and not an unrecorded hand-made public client however old');
    httpCheck($run(['--prune-unused' => '0'])[0] === 1 && $run(['--prune-unused' => 'x'])[0] === 1, 'a prune window below one day is refused');

    // The same check with registration off, in a fresh process.
    $off = [];
    exec('KIT_MCP_REGISTRATION_CHECK=off '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($argv[1]).' 2>&1', $off, $offStatus);
    httpCheck($offStatus === 0 && in_array('Registration-off checks passed.', $off, true), 'with registration off: no route, no registration_endpoint ('.count($off).' lines)');
    foreach ($off as $line) {
        if (str_starts_with($line, 'PASS ')) {
            echo $line."\n";
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
echo "MCP registration checks on SQLite memory through the kit's discovery and registration routes and Passport's real authorize, approve and token routes; no browser render, no consumer app, no live assistant.\n";
