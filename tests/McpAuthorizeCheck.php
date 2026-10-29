<?php

// MCP authorize: Passport's consent screen as the kit's Inertia page, the workspace chosen there, deny and approve through Passport's real routes, the code exchange binding the
// connection, and revoking from the AI assistants page (own, another member's, an owner's), with real Passport, Concierge and kit tables, on SQLite memory with synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-mcp-consent-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
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
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $people = [];
    foreach (['owner', 'admin', 'member', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        if ($name === 'outsider') {
            continue;
        }
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);

    $client = function (string $name) use ($people): Client {
        return Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => $name, 'secret' => null, 'provider' => 'users', 'redirect_uris' => ['https://assistant.test/callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false]);
    };
    $claude = $client('Assistant');
    $other = $client('Other assistant');
    // Issues a token the way Passport's token repository does: the row first, then AccessTokenCreated.
    $issue = function (HttpUser $user, Client $client, array $scopes = ['mcp:use', 'mcp:act']): Token {
        $token = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $user->id, 'client_id' => $client->id, 'name' => null, 'scopes' => $scopes, 'revoked' => false, 'expires_at' => now()->addHour()]);
        event(new AccessTokenCreated($token->id, (string) $user->id, (string) $client->id));

        return $token;
    };
    // A refresh as league's refresh-token grant runs it: the old access token is revoked, then the new one is issued.
    $refresh = function (Token $old) use ($issue): Token {
        $old->revoke();

        return $issue(HttpUser::query()->findOrFail($old->user_id), Client::query()->findOrFail($old->client_id));
    };
    $connect = function (HttpUser $user, Token $token, array $scopes = ['mcp:act']) use ($workspace): int {
        return DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $user->id, 'oauth_token_id' => $token->id, 'scopes' => json_encode($scopes), 'intent_key' => bin2hex(random_bytes(4)), 'fingerprint' => 'x', 'expires_at' => $token->expires_at]);
    };
    // Runs $call inside the bound connection, as a tool call would: its result, or the HTTP status the binding refused with.
    $open = function (HttpUser $user, Token $token, int $connection, ?Closure $call = null) use ($app): mixed {
        $request = Request::create('/mcp/connections/'.$connection, 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer synthetic']);
        $request->setRouteResolver(fn () => (new Route('POST', '/mcp/connections/{connection}', []))->bind($request));
        $user = HttpUser::query()->findOrFail($user->id)->withAccessToken(new AccessToken(['oauth_access_token_id' => $token->id, 'oauth_client_id' => $token->client_id, 'oauth_user_id' => (string) $user->id, 'oauth_scopes' => $token->scopes]));
        // Binding the request resets its user resolver to the auth manager, so the token's user is set after.
        $app->instance('request', $request);
        $request->setUserResolver(fn () => $user);
        try {
            $result = null;
            $status = (new BindMcpConnection)->handle($request, function () use ($call, &$result) {
                $result = $call ? $call() : 'ok';

                return response('ok');
            })->getStatusCode();

            return $call ? $result : $status;
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            return $error->getStatusCode();
        }
    };


    // Passport's signing keys, made for this run.
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    $config->set('passport.private_key', $private);
    $config->set('passport.public_key', openssl_pkey_get_details($key)['key']);
    $config->set('kit.mcp.ui', true);
    // Passport's token route is throttled, as a host's would be.
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);
    Illuminate\Support\Facades\RateLimiter::for('api', fn () => Illuminate\Cache\RateLimiting\Limit::none());

    $globex = Workspace::create(['name' => 'Globex', 'slug' => 'globex']);
    $initech = Workspace::create(['name' => 'Initech', 'slug' => 'initech']);
    foreach ([$globex, $initech] as $other) {
        $other->execute(function () use ($registry, $other): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($other);
            }
        });
    }
    // The owner owns Acme and Globex; nobody here belongs to Initech.
    ConciergeModels::membership()::query()->create(['workspace_id' => $globex->id, 'user_id' => $people['owner']->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Tey\Concierge\Concierge::class)->context()->run($globex, fn () => $people['owner']->assignRole('owner'), [$people['owner']]);
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);

    // One browser: the session cookie is carried from request to request, and the guard is signed in as $as (or signed out).
    $cookies = [];
    $send = function (?HttpUser $as, string $method, string $uri, array $input = [], array $headers = []) use ($app, &$cookies): Symfony\Component\HttpFoundation\Response {
        $guard = $app['auth']->guard('web');
        $as ? $guard->setUser($as) : $guard->forgetUser();
        $request = Request::create('https://kit.test'.$uri, $method, $input, $cookies, [], array_merge(['HTTP_ACCEPT' => 'text/html'], $headers));
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $response = new Illuminate\Http\Response('', $error->getStatusCode());
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
    $client = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Assistant', 'secret' => null, 'provider' => 'users', 'redirect_uris' => ['https://assistant.test/callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false]);
    $verifier = bin2hex(random_bytes(32));
    $authorize = fn (array $override = []) => '/oauth/authorize?'.http_build_query([...['client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'response_type' => 'code', 'scope' => 'mcp:use', 'state' => 'st4te',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'], ...$override]);
    $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml'];
    $counts = fn (): array => [DB::table('connections')->count(), DB::table('oauth_auth_codes')->count(), DB::table('oauth_access_tokens')->count()];
    $query = function (Symfony\Component\HttpFoundation\Response $response): array {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    };

    // Who may see it, and which redirect_uri.
    httpCheck($send(null, 'GET', $authorize())->getStatusCode() === 401, 'the consent page is refused to a guest (Passport asks them to sign in)');
    $before = $counts();
    $refused = $send($people['owner'], 'GET', $authorize(['redirect_uri' => 'https://evil.test/callback']), [], $inertia);
    httpCheck($refused->getStatusCode() === 401 && str_contains((string) $refused->getContent(), 'invalid_client') && ! $refused->isRedirect() && $counts() === $before, "a redirect_uri not registered for the client is refused with invalid_client, never redirected to");

    // The page: client, abilities in plain words, the workspace choice.
    $page = $send($people['owner'], 'GET', $authorize(), [], $inertia);
    $props = json_decode((string) $page->getContent(), true)['props'] ?? [];
    httpCheck(json_decode((string) $page->getContent(), true)['component'] === 'Mcp/Authorize/Page' && $props['client'] === 'Assistant', 'the consent page is the kit Inertia page and names the client');
    httpCheck($props['scopes'] === [['key' => 'mcp:use', 'label' => 'Act as you in the workspaces you approve, with what your role there allows']], 'the requested scope is shown in plain words');
    httpCheck(array_column($props['workspaces'], 'name') === ['Acme', 'Globex'] && in_array($props['workspace'], [$workspace->id, $globex->id], true), "the workspace choice lists the person's active workspaces only (not Initech) with a default");
    httpCheck(is_string($props['auth_token']) && $props['auth_token'] !== '' && is_string($props['csrf_token']) && $props['csrf_token'] !== '', 'the page carries Passport\'s auth token and the CSRF token for its plain posts');
    // Passport refuses an undefined scope before the view, so the view itself is called with one: it is listed as it was asked for.
    $direct = Request::create('https://kit.test/oauth/authorize', 'GET', [], [], [], $inertia);
    $direct->setLaravelSession($app['session']->driver());
    $app->instance('request', $direct);
    $direct->setUserResolver(fn () => $people['owner']);
    $rendered = json_decode((string) (new Tey\Kit\Features\Mcp\Http\AuthorizationView)(['client' => $client, 'user' => $people['owner'], 'scopes' => [new Laravel\Passport\Scope('mcp:use', 'x'), new Laravel\Passport\Scope('vendor.custom', '')], 'request' => $direct, 'authToken' => 'a'])->getContent(), true);
    httpCheck(array_column($rendered['props']['scopes'], 'label') === ['Act as you in the workspaces you approve, with what your role there allows', 'vendor.custom'], 'a scope with no label is listed verbatim, a known one by its label');
    // CSRF: approve and deny need the session's token.
    httpCheck($send($people['owner'], 'POST', '/oauth/authorize', ['auth_token' => $props['auth_token'], 'workspace' => $globex->id])->getStatusCode() === 419 && $counts() === $before, 'approve without the CSRF token is refused (419) and issues nothing');
    httpCheck($send($people['owner'], 'DELETE', '/oauth/authorize', ['auth_token' => $props['auth_token']])->getStatusCode() === 419, 'deny without the CSRF token is refused (419)');

    // Deny: Passport's own deny path, back to the client with access_denied.
    $props = json_decode((string) $send($people['owner'], 'GET', $authorize(), [], $inertia)->getContent(), true)['props'];
    $denied = $send($people['owner'], 'DELETE', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token']]);
    httpCheck($denied->isRedirect() && str_starts_with((string) $denied->headers->get('Location'), 'https://assistant.test/callback') && ($query($denied)['error'] ?? null) === 'access_denied' && ($query($denied)['state'] ?? null) === 'st4te', 'deny redirects to the client with error=access_denied and its state');
    httpCheck($counts() === $before, 'deny makes no connection, code or token');

    // A workspace the person is not a member of is refused before any code is issued.
    $props = json_decode((string) $send($people['owner'], 'GET', $authorize(), [], $inertia)->getContent(), true)['props'];
    httpCheck($send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token'], 'workspace' => $initech->id])->getStatusCode() === 403 && $counts() === $before, 'approving into a workspace the person is not a member of is refused (403) and issues nothing');
    httpCheck($send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token']])->getStatusCode() === 403 && $counts() === $before, 'approving with no workspace is refused (403)');

    // Approve: the code goes to the client, and the connection waits in the chosen workspace.
    $approved = $send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token'], 'workspace' => $globex->id]);
    $code = $query($approved)['code'] ?? null;
    httpCheck($approved->isRedirect() && str_starts_with((string) $approved->headers->get('Location'), 'https://assistant.test/callback') && is_string($code) && ($query($approved)['state'] ?? null) === 'st4te', 'approve redirects to the client with a code and its state');
    $pending = DB::table('connections')->where('actor_id', $people['owner']->id)->orderByDesc('id')->first();
    httpCheck($pending && (int) $pending->workspace_id === $globex->id && $pending->oauth_token_id === null && $pending->oauth_client_id === (string) $client->id && json_decode($pending->scopes, true) === ['mcp:act'], 'approve makes an acts-as-me connection in the chosen workspace, waiting for its token');

    // The code exchange: the new access token takes the waiting connection, and MCP opens on it.
    $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'code' => $code, 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($exchange->getStatusCode() === 200 && is_string(json_decode((string) $exchange->getContent(), true)['access_token'] ?? null), 'the code is exchanged for an access token at Passport\'s token route');
    $token = Token::query()->where('user_id', $people['owner']->id)->where('client_id', $client->id)->latest('created_at')->firstOrFail();
    $bound = DB::table('connections')->where('id', $pending->id)->first();
    httpCheck($bound->oauth_token_id === $token->id && (int) $bound->workspace_id === $globex->id && $bound->expires_at !== null && $bound->revoked_at === null, 'the code exchange binds the connection to the new token, in the chosen workspace');
    $mcp = function (HttpUser $user, Token $token, int $connection) use ($app): int {
        $request = Request::create('/mcp/connections/'.$connection, 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer synthetic']);
        $request->setRouteResolver(fn () => (new Route('POST', '/mcp/connections/{connection}', []))->bind($request));
        $user = HttpUser::query()->findOrFail($user->id)->withAccessToken(new AccessToken(['oauth_access_token_id' => $token->id, 'oauth_client_id' => $token->client_id, 'oauth_user_id' => (string) $user->id, 'oauth_scopes' => $token->scopes]));
        $app->instance('request', $request);
        $request->setUserResolver(fn () => $user);
        try {
            return (new BindMcpConnection)->handle($request, fn () => response('ok'))->getStatusCode();
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            return $error->getStatusCode();
        }
    };
    httpCheck($mcp($people['owner'], $token, (int) $pending->id) === 200, 'MCP opens on the bound connection with the exchanged token');

    // Revoking. The member and the admin each have a connection in Acme; revoke runs as the AI assistants page's DELETE does.
    $connectAs = function (HttpUser $user) use ($client, $workspace): array {
        $token = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $user->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour()]);
        $id = DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $user->id, 'oauth_token_id' => $token->id, 'oauth_client_id' => (string) $client->id, 'scopes' => json_encode(['mcp:act']), 'expires_at' => $token->expires_at]);

        return [$token, $id];
    };
    $revoke = function (HttpUser $as, int $connection) use ($app, $workspace): string {
        try {
            $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->revoke(new Principal((int) $as->id), $connection));

            return 'ok';
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            return (string) $error->getStatusCode();
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
    };
    [$memberToken, $memberConnection] = $connectAs($people['member']);
    [$adminToken, $adminConnection] = $connectAs($people['admin']);
    [$spareToken, $spareConnection] = $connectAs($people['member']);

    // The page lists only your own connections, unless you may manage the workspace's.
    $listed = function (HttpUser $as) use ($app, $workspace): array {
        $request = Request::create('https://kit.test/workspaces/'.$workspace->id.'/settings/connections', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $app->instance('request', $request);
        $request->setUserResolver(fn () => $as);
        $model = json_decode((string) $app->make(Tey\Kit\Features\Mcp\Http\ConnectionController::class)->index($request, $workspace->id)->getContent(), true);

        return array_column($model['table']['rows'], 'connectedBy', 'id');
    };
    httpCheck(array_unique(array_values($listed($people['member']))) === ['Member'] && ! isset($listed($people['member'])[$adminConnection]), "a member's AI assistants page lists their own connections only, never another member's");
    httpCheck(isset($listed($people['owner'])[$memberConnection], $listed($people['owner'])[$adminConnection]), "an owner's AI assistants page lists every member's connection in the workspace");

    httpCheck($revoke($people['member'], $adminConnection) === 'permission_denied' && $app->make(Registry::class)->status('permission_denied') === 403, "a member revoking another member's connection is refused with permission_denied (403)");
    httpCheck(DB::table('connections')->where('id', $adminConnection)->value('revoked_at') === null && ! Token::query()->findOrFail($adminToken->id)->revoked, 'the refused revoke changes nothing');
    httpCheck($revoke($people['member'], $memberConnection) === 'ok' && DB::table('connections')->where('id', $memberConnection)->value('revoked_at') !== null, 'a member revokes their own connection');
    httpCheck(Token::query()->findOrFail($memberToken->id)->revoked && $mcp($people['member'], $memberToken, $memberConnection) === 403, 'the revoked connection\'s token is revoked and no longer opens MCP');
    httpCheck($revoke($people['owner'], $adminConnection) === 'ok' && Token::query()->findOrFail($adminToken->id)->revoked && $mcp($people['admin'], $adminToken, $adminConnection) === 403, "an owner revokes another member's connection, and its token no longer opens MCP");
    httpCheck($revoke($people['owner'], $spareConnection) === 'ok' && $revoke($people['member'], $spareConnection) === 'ok', 'revoking an already revoked connection is a no-op for its owner');
    httpCheck($revoke($people['owner'], (int) $pending->id) === '404', 'a connection in another workspace is not found from this one');
    httpCheck($mcp($people['owner'], $token, (int) $pending->id) === 200, 'revokes in Acme leave the Globex connection working');
    echo "MCP authorize checks on SQLite memory through Passport's real authorize, approve, deny and token routes (session, CSRF), with the kit's Inertia consent page as JSON; no browser render, no consumer app, no MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
