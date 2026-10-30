<?php

// MCP explicit grants: a tool on #[Capability(explicit: true)] is listed and callable only under a connection whose consent named its scope, offered as an unticked
// checkbox on the kit's consent page, never covered by acts-as-me, never gained by an existing connection, removable on its own from the AI assistants page, with real
// Passport, Concierge and kit tables, on SQLite memory with synthetic users.
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

// A host's publish tool: explicit, so only a consent that named presentations:publish admits it.
#[Tey\Kit\Features\Mcp\Capability('workspace.publish', scope: 'presentations:publish', explicit: true, explains: 'Publish presentations to the public feed, where anyone can read them.')]
class PublishTool extends Tey\Kit\Features\Mcp\CapabilityTool
{
    protected string $description = 'Publish a presentation.';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [];
    }

    protected function call(Tey\Kit\Features\Core\Principal $principal, int $workspace, array $validated): array
    {
        return ['published' => true, 'workspace' => $workspace];
    }
}

#[Tey\Kit\Features\Mcp\Capability('workspace.publish', explicit: true, explains: 'x')]
class NoScopePublishTool extends PublishTool {}

#[Tey\Kit\Features\Mcp\Capability('workspace.publish', scope: 'presentations:publish', explicit: true)]
class NoExplainsPublishTool extends PublishTool {}

#[Tey\Kit\Features\Mcp\Capability('workspace.publish', scope: 'presentations:nope', explicit: true, explains: 'x')]
class UnknownScopePublishTool extends PublishTool {}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-mcp-explicit-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.publish' => true]],
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
    // The host's publish capability, its ability scope (owners only) and the explicit tool; Passport learns the new scope too.
    $registry->capabilities(['workspace.publish'])->abilities(['presentations:publish' => 'workspace.publish'])->scopes(['presentations:publish' => 'Publish presentations'])
        ->permission('workspace.publish', ['owner'], null, ['presentations:publish'])->capabilityTools([PublishTool::class]);
    Laravel\Passport\Passport::tokensCan($registry->tokenScopes());
    // Pennant learned the kit's capabilities at boot; a host declares its own the same way, so the check defines this one as AccessProvider does.
    Laravel\Pennant\Feature::define('workspace.publish', fn (Workspace $workspace): bool => (bool) (config('kit.capabilities', [])['workspace.publish'] ?? false));

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
    // --- Explicit grants: a publish tool admitted only under a connection whose consent named presentations:publish.
    $registry = $app->make(Registry::class);
    // Provisioning admitted the workspace on its own copy; this instance is read back so it reports the admission.
    $workspace->refresh();
    $explicitScopes = $registry->explicitScopes();
    httpCheck(array_keys($explicitScopes) === ['presentations:publish'] && $explicitScopes['presentations:publish']['label'] === 'Publish presentations' && $explicitScopes['presentations:publish']['explains'] === 'Publish presentations to the public feed, where anyone can read them.' && $explicitScopes['presentations:publish']['tool'] === PublishTool::class, 'the registry lists the explicit scope with its label, its explains text and its tool');
    foreach (['no scope' => NoScopePublishTool::class, 'no explains' => NoExplainsPublishTool::class, 'a scope that is not a token scope' => UnknownScopePublishTool::class] as $case => $wrong) {
        try {
            $registry->capabilityTools([$wrong]);
            throw new LogicException('no refusal');
        } catch (RuntimeException $error) {
            httpCheck(str_contains($error->getMessage(), 'needs'), 'an explicit tool with '.$case.' is refused at registration ('.$error->getMessage().')');
        }
    }
    httpCheck(WorkspaceConnection::covers(['mcp:act'], 'presentations:publish') === false && WorkspaceConnection::covers(['mcp:act', 'presentations:publish'], 'presentations:publish') && WorkspaceConnection::covers(['presentations:publish'], 'presentations:publish') && WorkspaceConnection::covers(['mcp:act'], 'settings.write'), 'acts-as-me never covers the explicit scope; only naming it does; other abilities are covered as before');

    // The full consent flow for the Assistant client in Acme: the page's grants, approve with the ticked grants, the code exchange; the connection and token it ends in.
    $connectOnce = function (array $grants, array $override = []) use ($send, $people, $workspace, $authorize, $inertia, $query, $verifier, $client): array {
        $page = $send($people['owner'], 'GET', $authorize($override), [], $inertia);
        $props = json_decode((string) $page->getContent(), true)['props'] ?? [];
        if ($page->isRedirect()) {
            return ['status' => 'reused', 'location' => (string) $page->headers->get('Location')];
        }
        $approved = $send($people['owner'], 'POST', '/oauth/authorize', ['_token' => $props['csrf_token'] ?? '', 'auth_token' => $props['auth_token'] ?? '', 'workspace' => $workspace->id, 'grants' => $grants]);
        if (! $approved->isRedirect()) {
            return ['status' => $approved->getStatusCode(), 'props' => $props];
        }
        $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'code' => $query($approved)['code'] ?? '', 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);
        // Tokens issued within one second tie on created_at: the newest connection of this person and client, then its token.
        $connection = DB::table('connections')->where('actor_id', $people['owner']->id)->where('oauth_client_id', (string) $client->id)->whereNotNull('oauth_token_id')->orderByDesc('id')->first();
        $token = $connection ? Token::query()->findOrFail($connection->oauth_token_id) : null;

        return ['status' => 'connected', 'props' => $props, 'token' => $token, 'connection' => $connection, 'scopes' => $connection ? json_decode($connection->scopes, true) : null, 'refresh' => json_decode((string) $exchange->getContent(), true)['refresh_token'] ?? null];
    };
    // What the publish tool looks like from a bound connection: 'listed' when present, else the reason it is absent; and a call to it through the kit's tools/call.
    $viewTool = function (Token $token, int $connection, bool $call = false, string $class = PublishTool::class, string $name = 'workspace_publish') use ($app, $workspace, $people): string {
        return $workspace->execute(function () use ($app, $token, $connection, $call, $people, $class, $name): string {
            $request = Request::create('/mcp/connections/'.$connection, 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer synthetic']);
            $request->setRouteResolver(fn () => (new Route('POST', '/mcp/connections/{connection}', []))->bind($request));
            $user = HttpUser::query()->findOrFail($people['owner']->id)->withAccessToken(new AccessToken(['oauth_access_token_id' => $token->id, 'oauth_client_id' => $token->client_id, 'oauth_user_id' => (string) $people['owner']->id, 'oauth_scopes' => $token->scopes]));
            $app->instance('request', $request);
            $request->setUserResolver(fn () => $user);
            $seen = null;
            (new BindMcpConnection)->handle($request, function () use ($app, $call, $class, $name, &$seen) {
                if (! $call) {
                    $seen = $app->make($class)->unavailable() ?? 'listed';

                    return response('ok');
                }
                $tools = array_values(array_filter($app->make(Registry::class)->mcpTools(), fn (string $tool): bool => $app->make($tool)->eligibleForRegistration()));
                $context = new Laravel\Mcp\Server\ServerContext(['2025-06-18'], [], new Laravel\Mcp\Schema\Implementation('Kit', '0.1.0'), '', 50, 15, $tools, [], []);
                try {
                    $response = $app->make(Tey\Kit\Features\Mcp\Methods\CallTool::class)->handle(new Laravel\Mcp\Transport\JsonRpcRequest(1, 'tools/call', ['name' => $name, 'arguments' => []]), $context);
                    $result = $response->toArray()['result'] ?? [];
                    $seen = ($result['isError'] ?? false) ? ($result['content'][0]['text'] ?? 'error') : 'called:'.json_encode($result['structuredContent'] ?? null);
                } catch (Laravel\Mcp\Exceptions\JsonRpcException $e) {
                    $seen = 'not found';
                }

                return response('ok');
            });

            return (string) $seen;
        });
    };

    // The consent page offers the grant, unticked, and a plain approval never includes it.
    $plain = $connectOnce([]);
    httpCheck($plain['status'] === 'connected' && ($plain['props']['grants'] ?? null) === [['scope' => 'presentations:publish', 'label' => 'Publish presentations', 'explains' => 'Publish presentations to the public feed, where anyone can read them.', 'checked' => false]], 'the consent page offers the explicit grant as one unticked choice with its label and explains text');
    $stub = (string) file_get_contents(dirname(__DIR__).'/resources/features/mcp/pages/Mcp/Authorize/Page.vue.stub');
    httpCheck(str_contains($stub, 'name="grants[]"') && str_contains($stub, ':checked="grant.checked"') && str_contains($stub, 'data-consent-grants') && str_contains($stub, 'grant.explains'), 'the consent page stub renders each grant as a checkbox bound to its (false) checked state, naming what it allows');
    httpCheck($plain['scopes'] === ['mcp:act'], 'approving without ticking leaves the connection at acts-as-me alone');
    httpCheck($viewTool($plain['token'], (int) $plain['connection']->id) === 'scope_required', 'under that connection the publish tool is absent: scope_required');
    httpCheck($viewTool($plain['token'], (int) $plain['connection']->id, true) === 'scope_required', 'a call to it anyway is refused as scope_required, not as an unknown tool');
    httpCheck(in_array('scope_required', Tey\Kit\Features\Mcp\ConsentGate::CODES, true) && ((fn () => $this->statuses['scope_required'] ?? null)->call($registry)) === 403, 'scope_required is a known refusal code, mapped to 403');

    // Reconnecting the same client in the remembered workspace reuses the live connection (consent skipped) and gains nothing.
    $remember = function (int $workspace) use ($app, &$cookies): void {
        $handler = $app['session']->driver()->getHandler();
        $data = (array) @unserialize((string) $handler->read($cookies['s']));
        $handler->write($cookies['s'], serialize([...$data, 'kit.workspace' => $workspace]));
    };
    $remember($workspace->id);
    $reused = $connectOnce(['presentations:publish']);
    httpCheck($reused['status'] === 'reused' && str_contains($reused['location'], 'code='), 'with a live connection, consent is skipped: the posted grant never reaches a page');
    $exchange = $send(null, 'POST', '/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => 'https://assistant.test/callback', 'code' => $query(new Illuminate\Http\RedirectResponse($reused['location']))['code'], 'code_verifier' => $verifier], ['HTTP_ACCEPT' => 'application/json']);
    $reusedToken = Token::query()->findOrFail(DB::table('connections')->where('id', $plain['connection']->id)->value('oauth_token_id'));
    httpCheck($exchange->getStatusCode() === 200 && json_decode(DB::table('connections')->where('id', $plain['connection']->id)->value('scopes'), true) === ['mcp:act'] && $reusedToken->id !== $plain['token']->id && ! $reusedToken->revoked && DB::table('connections')->count() === 1, 'the reused connection moved onto the new token and keeps acts-as-me only: an existing connection never gains a grant without a consent page');
    $refreshed = $send(null, 'POST', '/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->id, 'refresh_token' => json_decode((string) $exchange->getContent(), true)['refresh_token'] ?? ''], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($refreshed->getStatusCode() === 200 && json_decode(DB::table('connections')->where('id', $plain['connection']->id)->value('scopes'), true) === ['mcp:act'], 'a refresh keeps the connection\'s scopes as they were');
    // Revoke it so the next consent shows a page.
    DB::table('connections')->where('id', $plain['connection']->id)->update(['revoked_at' => now()]);

    // Ticking the grant names the scope on the connection; the tool is then listed and callable.
    $granted = $connectOnce(['presentations:publish']);
    httpCheck($granted['status'] === 'connected' && $granted['scopes'] === ['mcp:act', 'presentations:publish'], 'approving with the grant ticked writes acts-as-me plus the explicit scope');
    $audit = DB::table('audit_events')->where('event', 'workspace.connection.approved')->orderByDesc('id')->first();
    $details = $audit ? json_decode($audit->details, true) : [];
    httpCheck(($details['grants'] ?? null) === ['presentations:publish'], 'the approval audit entry records the grants');
    httpCheck($viewTool($granted['token'], (int) $granted['connection']->id) === 'listed', 'under that connection the publish tool is listed');
    httpCheck(str_starts_with($viewTool($granted['token'], (int) $granted['connection']->id, true), 'called:'), 'and a call runs');
    $grantedId = (int) $granted['connection']->id;

    // Removing the one grant from the AI assistants page keeps the connection and its token.
    $removal = $send($people['owner'], 'DELETE', '/@'.$workspace->slug.'/settings/connections/'.$grantedId.'/grants/presentations:publish', ['_token' => $granted['props']['csrf_token']], ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($removal->getStatusCode() === 200 && json_decode(DB::table('connections')->where('id', $grantedId)->value('scopes'), true) === ['mcp:act'] && DB::table('connections')->where('id', $grantedId)->whereNull('revoked_at')->exists() && ! Token::query()->findOrFail($granted['token']->id)->revoked, 'removing the grant leaves the connection live, its token live, and acts-as-me alone');
    $removed = DB::table('audit_events')->where('event', 'workspace.connection.grant_removed')->orderByDesc('id')->first();
    httpCheck($removed !== null && (json_decode($removed->details, true)['scope'] ?? null) === 'presentations:publish', 'the removal is audited');
    httpCheck($viewTool($granted['token'], $grantedId) === 'scope_required', 'the tool is absent again straight away');
    httpCheck($send($people['owner'], 'DELETE', '/@'.$workspace->slug.'/settings/connections/'.$grantedId.'/grants/presentations:publish', ['_token' => $granted['props']['csrf_token']], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode() === 404, 'removing it again is not found');
    httpCheck($send($people['owner'], 'DELETE', '/@'.$workspace->slug.'/settings/connections/'.$grantedId.'/grants/mcp:act', ['_token' => $granted['props']['csrf_token']], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode() === 404, 'acts-as-me is not a grant that can be removed this way');
    DB::table('connections')->where('id', $grantedId)->update(['revoked_at' => now()]);

    // A grant the person may not hold is refused, as is one that is not an explicit scope; the OAuth scope parameter alone grants nothing.
    $forged = $connectOnce(['settings.write']);
    httpCheck($forged['status'] === 403, 'posting a scope that is not an explicit grant is refused (403) and issues nothing');
    $forgedExplicit = $connectOnce(['presentations:publish', 'nope']);
    httpCheck($forgedExplicit['status'] === 403, 'an unknown grant among valid ones refuses the whole approval');
    $asked = $connectOnce([], ['scope' => 'mcp:use presentations:publish']);
    httpCheck($asked['status'] === 'connected' && $asked['scopes'] === ['mcp:act'] && $viewTool($asked['token'], (int) $asked['connection']->id) === 'scope_required', 'a client that asks for the explicit scope in its OAuth request gets it only by the tick: unticked, the connection does not hold it');
    DB::table('connections')->where('id', $asked['connection']->id)->update(['revoked_at' => now()]);
    // An admin (no publish permission) sees no grant to tick and cannot post it.
    $adminPage = $send($people['admin'], 'GET', $authorize(), [], $inertia);
    $adminProps = json_decode((string) $adminPage->getContent(), true)['props'] ?? [];
    httpCheck($adminPage->getStatusCode() === 200 && ($adminProps['grants'] ?? null) === [], 'a person whose role may not publish is offered no publish grant');
    $adminApproved = $send($people['admin'], 'POST', '/oauth/authorize', ['_token' => $adminProps['csrf_token'], 'auth_token' => $adminProps['auth_token'], 'workspace' => $workspace->id, 'grants' => ['presentations:publish']]);
    httpCheck($adminApproved->getStatusCode() === 403 && DB::table('connections')->where('actor_id', $people['admin']->id)->doesntExist(), 'and posting it anyway is refused (403), with no connection made');

    // Off means absent for narrow connections too: a connection covering settings.read only sees the settings read tool, not the write tool, and a call to the write tool anyway is connection_denied.
    $read = Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings::class;
    $write = Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings::class;
    $narrowToken = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $people['owner']->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use', 'settings.read'], 'revoked' => false, 'expires_at' => now()->addHour()]);
    $narrowConnection = (int) DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $people['owner']->id, 'oauth_token_id' => $narrowToken->id, 'oauth_client_id' => (string) $client->id, 'scopes' => json_encode(['settings.read']), 'intent_key' => 'n1', 'fingerprint' => 'n', 'expires_at' => $narrowToken->expires_at]);
    httpCheck($viewTool($narrowToken, $narrowConnection, false, $read, 'workspace_settings_read') === 'listed' && $viewTool($narrowToken, $narrowConnection, false, $write, 'workspace_settings_write') === 'connection_denied', 'a narrow settings.read connection lists the settings read tool and not the write tool (connection_denied)');
    httpCheck($viewTool($narrowToken, $narrowConnection, true, $write, $app->make($write)->name()) === 'connection_denied', 'a call to the write tool anyway is refused connection_denied, the same code as before');
    httpCheck($viewTool($narrowToken, $narrowConnection, false) === 'scope_required', 'and the explicit publish tool is scope_required for it');
    $wideToken = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $people['owner']->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour()]);
    $wideConnection = (int) DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $people['owner']->id, 'oauth_token_id' => $wideToken->id, 'oauth_client_id' => (string) $client->id, 'scopes' => json_encode(['mcp:act']), 'intent_key' => 'w1', 'fingerprint' => 'w', 'expires_at' => $wideToken->expires_at]);
    httpCheck($viewTool($wideToken, $wideConnection, false, $read, 'r') === 'listed' && $viewTool($wideToken, $wideConnection, false, $write, 'w') === 'listed' && $viewTool($wideToken, $wideConnection, false) === 'scope_required', 'an acts-as-me connection lists every non-explicit tool, and still not the explicit one');
    DB::table('connections')->whereIn('id', [$narrowConnection, $wideConnection])->update(['revoked_at' => now()]);

    // The settings page's own approval (WorkspaceConnection::approve) can never bind an explicit scope, however the token was issued: grantable scopes leave it out, and posting it is refused.
    httpCheck(! in_array('presentations:publish', WorkspaceConnection::grantableScopes(), true) && in_array('mcp:act', WorkspaceConnection::grantableScopes(), true), 'grantable scopes for the settings page leave the explicit scope out');
    $askedToken = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $people['owner']->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use', 'mcp:act', 'presentations:publish'], 'revoked' => false, 'expires_at' => now()->addHour()]);
    $before = DB::table('connections')->count();
    try {
        $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->approve(new Tey\Kit\Features\Core\Principal((int) $people['owner']->id), ['token_id' => $askedToken->id, 'scopes' => ['mcp:act', 'presentations:publish'], 'intent_key' => 'probe-431']));
        throw new LogicException('bound');
    } catch (Illuminate\Validation\ValidationException $error) {
        httpCheck(DB::table('connections')->count() === $before, 'a token that asked for the explicit scope cannot be bound with it on the settings page: refused, no connection (the review\'s probe)');
    }
    $plainBind = $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->approve(new Tey\Kit\Features\Core\Principal((int) $people['owner']->id), ['token_id' => $askedToken->id, 'scopes' => ['mcp:act'], 'intent_key' => 'probe-431b']));
    httpCheck(($plainBind['created'] ?? false) === true && $viewTool($askedToken, (int) $plainBind['id']) === 'scope_required', 'bound as acts-as-me instead, the publish tool is still absent under that token');
    DB::table('connections')->where('id', $plainBind['id'])->update(['revoked_at' => now()]);

    // A host running its own consent writes its own connections: without the scope named, the tool stays absent.
    $hostToken = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $people['owner']->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour()]);
    $hostConnection = (int) DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $people['owner']->id, 'oauth_token_id' => $hostToken->id, 'oauth_client_id' => (string) $client->id, 'scopes' => json_encode(['mcp:act']), 'intent_key' => 'h1', 'fingerprint' => 'h', 'expires_at' => $hostToken->expires_at]);
    httpCheck($viewTool($hostToken, $hostConnection) === 'scope_required', 'a host-written acts-as-me connection does not hold the explicit scope');
    DB::table('connections')->where('id', $hostConnection)->update(['scopes' => json_encode(['mcp:act', 'presentations:publish'])]);
    httpCheck($viewTool($hostToken, $hostConnection) === 'listed', 'one that names it does');
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n".implode("\n", array_slice(explode("\n", $error->getTraceAsString()), 0, 10))."\n");
    exit(1);
}
echo "MCP explicit grant checks on SQLite memory through Passport's real authorize, approve and token routes, the kit's consent page as JSON, the MCP binding and tools/call; no browser render, no consumer app, no MCP transport.\n";
