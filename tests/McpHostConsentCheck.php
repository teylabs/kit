<?php

// MCP consent run by the host (kit.mcp.consent=host): the host's own consent page and approve field, Passport's approve and token routes as Passport ships them, and no kit
// connection row; plus the refusal of an unknown kit.mcp.consent. Real Passport, Concierge and kit tables, on SQLite memory with synthetic users.
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Mcp\Server\Tool;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Passport\Token;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Http\KitAuthorizationController;
use Tey\Kit\Features\Mcp\Http\Middleware\BindConsentWorkspace;
use Tey\Kit\Features\Mcp\Http\Middleware\BindMcpConnection;
use Tey\Kit\Features\Mcp\McpProvider;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Mod\ModServiceProvider;

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
final class EnforcedCsrf extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}

// The host's approve middleware: it reads its own workspace_id field and keeps its own grant.
final class HostConsentApproval
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->filled('workspace_id'), 422);
        DB::table('host_grants')->insert(['user_id' => $request->user()->getAuthIdentifier(), 'workspace_id' => (int) $request->input('workspace_id')]);

        return $next($request);
    }
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-mcp-host-consent-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true], 'mcp' => ['consent' => 'host']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // The host's own consent page, bound before the providers boot, as a host's AppServiceProvider would: the kit must leave it in place.
    Passport::authorizationView(fn (array $parameters) => new JsonResponse(['page' => 'host-consent', 'client' => $parameters['client']->name, 'auth_token' => $parameters['authToken'], 'csrf_token' => $parameters['request']->session()->token()]));
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
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

    $app['router']->middlewareGroup('web', [StartSession::class, EnforcedCsrf::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
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
        app(Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    $sync->run(new ArrayInput([]), new NullOutput);

    $client = function (string $name): Client {
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
        } catch (HttpExceptionInterface $error) {
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
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    RateLimiter::for('api', fn () => Limit::none());

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
    app(Concierge::class)->context()->run($globex, fn () => $people['owner']->assignRole('owner'), [$people['owner']]);
    $sync->run(new ArrayInput([]), new NullOutput);

    // One browser: the session cookie is carried from request to request, and the guard is signed in as $as (or signed out).
    $cookies = [];
    $send = function (?HttpUser $as, string $method, string $uri, array $input = [], array $headers = []) use ($app, &$cookies): Symfony\Component\HttpFoundation\Response {
        $guard = $app['auth']->guard('web');
        $as ? $guard->setUser($as) : $guard->forgetUser();
        $request = Request::create('https://kit.test'.$uri, $method, $input, $cookies, [], array_merge(['HTTP_ACCEPT' => 'text/html'], $headers));
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (HttpExceptionInterface $error) {
            $response = new Response('', $error->getStatusCode());
        } catch (TokenMismatchException) {
            $response = new Response('', 419);
        } catch (OAuthServerException $error) {
            $response = $error->render($request);
        } catch (AuthenticationException|Laravel\Passport\Exceptions\AuthenticationException) {
            $response = new Response('', 401);
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
    // The host's own approve: its consent posts workspace_id (not the kit's `workspace`) and keeps its own grant, as its ConsentApproval would.
    Schema::create('host_grants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('workspace_id');
    });
    $app['router']->aliasMiddleware('host.consent', HostConsentApproval::class);
    Illuminate\Support\Facades\Route::getRoutes()->getByName('passport.authorizations.approve')->middleware('host.consent');

    $approve = Illuminate\Support\Facades\Route::getRoutes()->getByName('passport.authorizations.approve');
    httpCheck(McpProvider::consent() === 'host' && ! in_array(BindConsentWorkspace::class, $approve->gatherMiddleware(), true), 'in host mode the kit attaches no BindConsentWorkspace to Passport\'s approve');
    httpCheck(! ($app->make(AuthorizationController::class) instanceof KitAuthorizationController), 'in host mode Passport\'s AuthorizationController is not replaced by the kit\'s');

    $page = $send($people['owner'], 'GET', $authorize());
    $props = json_decode((string) $page->getContent(), true) ?? [];
    httpCheck($page->getStatusCode() === 200 && ($props['page'] ?? null) === 'host-consent' && is_string($props['auth_token'] ?? null), 'the consent page is the host\'s own, not the kit\'s Authorize page');
    $query = function (Symfony\Component\HttpFoundation\Response $response): array {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    };
    $before = DB::table('connections')->count();
    $approved = $send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'], 'auth_token' => $props['auth_token'], 'workspace_id' => $globex->id]);
    $code = $query($approved)['code'] ?? null;
    httpCheck($approved->isRedirect() && str_starts_with((string) $approved->headers->get('Location'), 'https://assistant.test/callback') && is_string($code) && ($query($approved)['state'] ?? null) === 'st4te', 'approve with the host\'s workspace_id field succeeds: Passport redirects to the client with a code (no 403)');
    httpCheck(DB::table('host_grants')->where('user_id', $people['owner']->id)->where('workspace_id', $globex->id)->count() === 1, 'the host\'s own approve middleware ran and kept its grant');
    httpCheck(DB::table('connections')->count() === $before, 'approve writes no kit connection row');
    $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'code' => $code, 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($exchange->getStatusCode() === 200 && is_string(json_decode((string) $exchange->getContent(), true)['access_token'] ?? null), 'the code is exchanged for an access token at Passport\'s token route');
    // A kit connection waiting for a token, as kit mode's consent leaves one: in host mode a later approve and code exchange never take it.
    $waiting = DB::table('connections')->insertGetId(['workspace_id' => $globex->id, 'actor_id' => $people['owner']->id, 'oauth_client_id' => (string) $client->id, 'oauth_token_id' => null, 'scopes' => json_encode(['mcp:act']), 'expires_at' => now()->addHour()]);
    $second = json_decode((string) $send($people['owner'], 'GET', $authorize(['prompt' => 'consent']))->getContent(), true) ?? [];
    $code = $query($send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $second['csrf_token'] ?? '', 'auth_token' => $second['auth_token'] ?? '', 'workspace_id' => $globex->id]))['code'] ?? null;
    $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'code' => $code, 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck(is_string($code) && $exchange->getStatusCode() === 200 && DB::table('connections')->where('id', $waiting)->value('oauth_token_id') === null && DB::table('connections')->whereNotNull('oauth_token_id')->count() === 0, 'in host mode a code exchange binds no kit connection, even one waiting for its token');

    // An unknown value is refused, never guessed.
    foreach (['Host', 'none', '', true, 0] as $bad) {
        $config->set('kit.mcp.consent', $bad);
        try {
            McpProvider::consent();
            $refused = false;
        } catch (RuntimeException $error) {
            $refused = str_contains($error->getMessage(), "kit.mcp.consent must be 'kit' or 'host'");
        }
        httpCheck($refused, 'kit.mcp.consent '.var_export($bad, true).' is refused with a clear error');
    }
    $config->set('kit.mcp.consent', null);
    httpCheck(McpProvider::consent() === 'kit', 'a null kit.mcp.consent is kit');
    $config->set('kit.mcp', array_diff_key($config->get('kit.mcp'), ['consent' => true]));
    httpCheck(McpProvider::consent() === 'kit', 'an unset (null or absent) kit.mcp.consent is kit');
    $config->set('kit.mcp.consent', 'host');
    echo "MCP host-consent checks on SQLite memory through Passport's real authorize, approve and token routes (session, CSRF), with a synthetic host consent page and approve middleware; no browser render, no consumer app.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
