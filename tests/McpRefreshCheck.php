<?php

// MCP connections across an OAuth token refresh, with real Passport, Concierge and kit tables and the kit's providers booted, on SQLite memory with synthetic users.
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
use Tey\Kit\Features\Mcp\Http\Middleware\BindMcpConnection;
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

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-permissions-http-'.bin2hex(random_bytes(4));
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class,
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

    $app['router']->middlewareGroup('web', [Illuminate\Session\Middleware\StartSession::class]);
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
    $issue = function (HttpUser $user, Client $client): Token {
        $token = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $user->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use', 'settings.read'], 'revoked' => false, 'expires_at' => now()->addHour()]);
        DB::table('oauth_refresh_tokens')->insert(['id' => bin2hex(random_bytes(40)), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDays(30)]);
        event(new AccessTokenCreated($token->id, (string) $user->id, (string) $client->id));

        return $token;
    };
    // A refresh as league's refresh-token grant runs it: Passport's repository revokes the previous access token (the refresh token's access_token_id, firing
    // AccessTokenRevoked), the refresh token is revoked, then the new access token is issued.
    $refresh = function (Token $old) use ($issue): Token {
        app(Laravel\Passport\Bridge\AccessTokenRepository::class)->revokeAccessToken($old->id);
        DB::table('oauth_refresh_tokens')->where('access_token_id', $old->id)->update(['revoked' => true]);

        return $issue(HttpUser::query()->findOrFail($old->user_id), Client::query()->findOrFail($old->client_id));
    };
    $connect = function (HttpUser $user, Token $token) use ($workspace): int {
        return DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $user->id, 'oauth_token_id' => $token->id, 'scopes' => json_encode(['settings.read']), 'intent_key' => bin2hex(random_bytes(4)), 'fingerprint' => 'x', 'expires_at' => $token->expires_at]);
    };
    $open = function (HttpUser $user, Token $token, int $connection) use ($app): int {
        $request = Request::create('/mcp/connections/'.$connection, 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer synthetic']);
        $request->setRouteResolver(fn () => (new Route('POST', '/mcp/connections/{connection}', []))->bind($request));
        $user = HttpUser::query()->findOrFail($user->id)->withAccessToken(new AccessToken(['oauth_access_token_id' => $token->id, 'oauth_client_id' => $token->client_id, 'oauth_user_id' => (string) $user->id, 'oauth_scopes' => $token->scopes]));
        // Binding the request resets its user resolver to the auth manager, so the token's user is set after.
        $app->instance('request', $request);
        $request->setUserResolver(fn () => $user);
        try {
            return (new BindMcpConnection)->handle($request, fn () => response('ok'))->getStatusCode();
        } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            return $error->getStatusCode();
        }
    };

    $first = $issue($people['owner'], $claude);
    $connection = $connect($people['owner'], $first);
    httpCheck($open($people['owner'], $first, $connection) === 200, 'a connection opens with the token it was approved with');
    $second = $refresh($first);
    httpCheck(DB::table('connections')->where('id', $connection)->value('oauth_token_id') === $second->id, 'a refresh moves the connection to the new access token');
    httpCheck($open($people['owner'], $second, $connection) === 200, 'the same connection opens with the refreshed token');
    httpCheck($open($people['owner'], $first, $connection) === 403, 'the old token is refused after the refresh');
    $third = $refresh($second);
    httpCheck($open($people['owner'], $third, $connection) === 200 && $open($people['owner'], $second, $connection) === 403, 'the connection follows every later refresh');

    // Another user's or another client's token never adopts the connection, even once its own token is spent.
    $third->revoke();
    $issue($people['admin'], $claude);
    $issue($people['owner'], $other);
    httpCheck(DB::table('connections')->where('id', $connection)->value('oauth_token_id') === $third->id, "another user's or another client's new token leaves the connection where it was");
    // Nor does a new token for the same user and client that is not a refresh of its token: a spent connection stays spent.
    $fourth = $issue($people['owner'], $claude);
    httpCheck(DB::table('connections')->where('id', $connection)->value('oauth_token_id') === $third->id && $open($people['owner'], $fourth, $connection) === 403, 'a new token for the same user and client that is not a refresh of its token does not revive a spent connection');

    // A revoked connection stays revoked across a refresh.
    $revokedToken = $issue($people['owner'], $claude);
    $revokedConnection = $connect($people['owner'], $revokedToken);
    DB::table('connections')->where('id', $revokedConnection)->update(['revoked_at' => now()]);
    $fifth = $refresh($revokedToken);
    httpCheck(DB::table('connections')->where('id', $revokedConnection)->value('oauth_token_id') === $revokedToken->id && $open($people['owner'], $fifth, $revokedConnection) === 403, 'a revoked connection is not moved or reopened by a refresh');

    // One connection per workspace for the same user and client: a code exchange binds only its own consent's connection, a refresh moves only its own token's.
    $globex = Workspace::create(['name' => 'Globex', 'slug' => 'globex']);
    $initech = Workspace::create(['name' => 'Initech', 'slug' => 'initech']);
    $assistant = $client('Third assistant');
    $inWorkspace = function (HttpUser $user, Token $token, Workspace $in) use ($assistant): int {
        return DB::table('connections')->insertGetId(['workspace_id' => $in->id, 'actor_id' => $user->id, 'oauth_token_id' => $token->id, 'oauth_client_id' => (string) $assistant->id, 'scopes' => json_encode(['mcp:act']), 'expires_at' => $token->expires_at]);
    };
    // A consent on the consent page (BindConsentWorkspace): a connection in the chosen workspace waiting for the code exchange's token.
    $consent = function (HttpUser $user, Workspace $in) use ($assistant): int {
        return DB::table('connections')->insertGetId(['workspace_id' => $in->id, 'actor_id' => $user->id, 'oauth_token_id' => null, 'oauth_client_id' => (string) $assistant->id, 'scopes' => json_encode(['mcp:act']), 'expires_at' => now()->addMinutes(10)]);
    };
    $expire = fn (Token $token) => Token::query()->whereKey($token->id)->update(['expires_at' => now()->subMinute()]);

    // (a) Workspace A's token expired; consent for B and the code exchange: the new token is B's only, and A is unchanged.
    $t1 = $issue($people['admin'], $assistant);
    $a = $inWorkspace($people['admin'], $t1, $workspace);
    $expire($t1);
    $b = $consent($people['admin'], $globex);
    $t3 = $issue($people['admin'], $assistant);
    httpCheck(DB::table('connections')->where('id', $b)->value('oauth_token_id') === $t3->id, '(a) the code exchange binds the new token to the consent\'s workspace B');
    httpCheck(DB::table('connections')->where('id', $a)->value('oauth_token_id') === $t1->id && $open($people['admin'], $t3, $a) === 403, '(a) workspace A keeps its expired token: the code exchange does not move it');

    // (b) Two workspaces' tokens both spent; a refresh of one moves only that connection.
    $tA = $issue($people['member'], $assistant);
    $tB = $issue($people['member'], $assistant);
    $inA = $inWorkspace($people['member'], $tA, $workspace);
    $inB = $inWorkspace($people['member'], $tB, $globex);
    $expire($tA);
    $expire($tB);
    $tA2 = $refresh($tA);
    httpCheck(DB::table('connections')->where('id', $inA)->value('oauth_token_id') === $tA2->id, "(b) a refresh moves the refreshed token's connection");
    httpCheck(DB::table('connections')->where('id', $inB)->value('oauth_token_id') === $tB->id, "(b) the other workspace's spent connection stays on its own token");

    // (c) An expired, unrefreshed connection, then a fresh consent elsewhere: the expired one stays expired.
    $old = $issue($people['owner'], $assistant);
    $expired = $inWorkspace($people['owner'], $old, $workspace);
    $expire($old);
    $elsewhere = $consent($people['owner'], $initech);
    $fresh = $issue($people['owner'], $assistant);
    httpCheck(DB::table('connections')->where('id', $expired)->value('oauth_token_id') === $old->id && DB::table('connections')->where('id', $elsewhere)->value('oauth_token_id') === $fresh->id, '(c) a fresh consent elsewhere binds its own connection and leaves the expired one expired');

    echo "MCP refresh checks on SQLite memory with real Passport tables, Concierge and spatie teams; tokens are inserted, revoked through Passport's repository and AccessTokenCreated fired as league's grants do, with no OAuth HTTP exchange, PSR token parsing or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
