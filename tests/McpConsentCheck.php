<?php

// MCP consent: the acts-as-me grant, optional narrow grants, in-chat confirmation for destructive tools, refusal codes and the "via assistant" audit tag, with real Passport, Concierge and kit tables and the kit providers booted, on SQLite memory with synthetic users.
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

#[IsDestructive]
final class DeleteThing extends Tool {}

final class ListThings extends Tool {}

#[IsDestructive(false)]
final class SafeThing extends Tool {}

#[Tey\Kit\Features\Mcp\Capability('workspace.settings', destructive: true)]
final class ArchiveThing extends Tool
{
    protected string $name = 'archive_thing';
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


    $gate = $app->make(ConsentGate::class);
    // Runs $fn and returns the RuntimeException code it threw, or 'ok'.
    $code = function (Closure $fn): string {
        try {
            $fn();

            return 'ok';
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
    };

    // Acts as me: one grant, and the role decides.
    httpCheck(WorkspaceConnection::grantableScopes()[0] === 'mcp:act' && in_array('settings.write', WorkspaceConnection::grantableScopes(), true), 'acts-as-me is the first grantable scope, with the narrow grants after it');
    $ownerToken = $issue($people['owner'], $claude);
    $approved = $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->approve(new Principal((int) $people['owner']->id), ['token_id' => $ownerToken->id, 'scopes' => ['mcp:act'], 'intent_key' => 'owner-act']));
    httpCheck($approved['created'] === true, 'a connection can be approved with only the acts-as-me grant');
    $ownerConnection = (int) $approved['id'];
    httpCheck($open($people['owner'], $ownerToken, $ownerConnection, fn () => $code(fn () => $gate->admit('settings.write'))) === 'ok', 'an acts-as-me connection admits a write the person may make');
    httpCheck($open($people['owner'], $ownerToken, $ownerConnection, fn () => $code(fn () => app(Tey\Kit\Features\Access\Access::class)->authorize(request()->attributes->get('mcp.principal'), 'settings.write'))) === 'ok', "Access lets the owner's acts-as-me connection write settings");
    [$principal, $bound] = $open($people['owner'], $ownerToken, $ownerConnection, fn () => $gate->admit('settings.read'));
    httpCheck($principal->connectionId === $ownerConnection && $bound === $workspace->id, 'admit returns the delegated principal and the bound workspace');

    $memberToken = $issue($people['member'], $claude);
    $memberConnection = $connect($people['member'], $memberToken);
    httpCheck($open($people['member'], $memberToken, $memberConnection, fn () => $code(fn () => app(Tey\Kit\Features\Access\Access::class)->authorize(request()->attributes->get('mcp.principal'), 'settings.write'))) === 'permission_denied', "acts-as-me never exceeds the person's role: a member's connection cannot write settings");

    // Narrow grants stay optional and narrow.
    $narrowToken = $issue($people['admin'], $claude, ['mcp:use', 'settings.read']);
    $narrow = $connect($people['admin'], $narrowToken, ['settings.read']);
    httpCheck($open($people['admin'], $narrowToken, $narrow, fn () => $code(fn () => $gate->admit('settings.read'))) === 'ok', 'a narrow settings.read connection admits a read');
    httpCheck($open($people['admin'], $narrowToken, $narrow, fn () => $code(fn () => $gate->admit('settings.write'))) === 'connection_denied', 'a narrow settings.read connection is refused a write, with connection_denied');
    httpCheck($open($people['admin'], $narrowToken, $narrow, fn () => $code(fn () => app(Tey\Kit\Features\Access\Access::class)->authorize(request()->attributes->get('mcp.principal'), 'settings.write'))) === 'connection_denied', "the connection guard refuses a write outside a narrow grant even for an admin");
    // A connection widened beyond its token is still refused: both must cover the ability.
    $wide = $connect($people['admin'], $issue($people['admin'], $other, ['mcp:use', 'settings.read']), ['mcp:act']);
    $wideToken = Token::query()->where('client_id', $other->id)->where('user_id', $people['admin']->id)->latest('created_at')->firstOrFail();
    httpCheck($open($people['admin'], $wideToken, $wide, fn () => $code(fn () => $gate->admit('settings.write'))) === 'connection_denied', 'a write is refused when the token itself lacks acts-as-me and the narrow scope');
    $bare = $issue($people['owner'], $other, ['settings.read']);
    httpCheck($open($people['owner'], $bare, $connect($people['owner'], $bare)) === 403, 'a token without mcp:use opens no connection');

    // A token with only mcp:use (what assistants request, since laravel/mcp advertises only it) acts as the person; one that asked for narrow scopes is held to them.
    $plain = $issue($people['owner'], $claude, ['mcp:use']);
    $plainConnection = (int) $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->approve(new Principal((int) $people['owner']->id), ['token_id' => $plain->id, 'scopes' => ['mcp:act'], 'intent_key' => 'owner-plain']))['id'];
    httpCheck($open($people['owner'], $plain, $plainConnection, fn () => $code(fn () => $gate->admit('settings.write'))) === 'ok', 'a plain mcp:use token approved as acts-as-me admits a write');
    httpCheck($code(fn () => $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->approve(new Principal((int) $people['admin']->id), ['token_id' => $narrowToken->id, 'scopes' => ['mcp:act'], 'intent_key' => 'admin-widen']))) === 'connection_denied', 'a token that asked for narrow scopes cannot be approved as acts-as-me');
    // Upgrade safety: an existing narrow connection row stays narrow even when its token holds only mcp:use (which acts as the person).
    $legacyToken = $issue($people['admin'], $claude, ['mcp:use']);
    $legacy = $connect($people['admin'], $legacyToken, ['settings.read']);
    httpCheck($open($people['admin'], $legacyToken, $legacy, fn () => $code(fn () => $gate->admit('settings.read'))) === 'ok', 'an existing settings.read connection on an mcp:use-only token still reads');
    httpCheck($open($people['admin'], $legacyToken, $legacy, fn () => $code(fn () => $gate->admit('settings.write'))) === 'connection_denied', 'the gate itself refuses a write on that connection');
    httpCheck($open($people['admin'], $legacyToken, $legacy, fn () => $code(fn () => app(Tey\Kit\Features\Access\Access::class)->authorize(request()->attributes->get('mcp.principal'), 'settings.write'))) === 'connection_denied', 'an existing settings.read connection on an mcp:use-only token is refused a write: the connection stays narrow');
    httpCheck(! WorkspaceConnection::tokenCovers(['settings.read'], 'settings.read') && ! WorkspaceConnection::tokenCovers([], 'settings.read'), 'a token without mcp:use covers nothing');

    // Revoking: the connection stops working at once, and a refresh does not bring it back.
    $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace->id, fn () => $app->make(WorkspaceConnection::class)->revoke(new Principal((int) $people['owner']->id), $ownerConnection));
    httpCheck($open($people['owner'], $ownerToken, $ownerConnection) === 403, 'a revoked acts-as-me connection is refused');

    // Acts-as-me follows a token refresh like any connection.
    $before = $issue($people['owner'], $claude);
    $live = $connect($people['owner'], $before);
    $before->revoke();
    $after = $issue($people['owner'], $claude);
    httpCheck($open($people['owner'], $after, $live, fn () => $code(fn () => $gate->admit('settings.write'))) === 'ok', 'an acts-as-me connection keeps working after a refresh');

    // In-chat confirmation for destructive tools.
    httpCheck(ConsentGate::destructive(DeleteThing::class) && ! ConsentGate::destructive(ListThings::class) && ! ConsentGate::destructive(SafeThing::class), "laravel/mcp's #[IsDestructive] marks a tool destructive, and #[IsDestructive(false)] or no annotation does not");
    httpCheck(ConsentGate::destructive(ArchiveThing::class), "#[Capability(destructive: true)] marks a tool destructive");
    $registry->tools([ArchiveThing::class]);
    httpCheck(ConsentGate::destructive('archive_thing') && $code(fn () => ConsentGate::destructive('no_such_tool')) === 'operation_failed', 'a registered tool is found by its MCP name, and an unknown name fails closed');
    $me = new Principal((int) $people['owner']->id, $live);
    $mcp = fn (array $arguments) => new Laravel\Mcp\Request($arguments);
    httpCheck($gate->confirm(ListThings::class, $me, $mcp(['id' => 1]), 'List things') === null, 'a tool that is not destructive runs on the first call');
    $first = $gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7, 'reason' => 'x']), 'Delete thing 7');
    $payload = $first?->getStructuredContent();
    httpCheck(($payload['status'] ?? null) === 'confirmation_required' && ($payload['summary'] ?? null) === 'Delete thing 7' && is_string($payload['confirm_token'] ?? null), 'a destructive tool first returns confirmation_required with a summary and a confirm token');
    $token = $payload['confirm_token'];
    $isInvalid = fn ($response): bool => $response instanceof Laravel\Mcp\Response && $response->isError() && (string) $response->content() === 'confirmation_invalid';
    httpCheck($isInvalid($gate->confirm(DeleteThing::class, $me, $mcp(['id' => 8, 'reason' => 'x', 'confirm_token' => $token]), 'Delete thing 8')), 'a confirm token is refused for different arguments');
    $token = $gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7]), 'Delete thing 7')->getStructuredContent()['confirm_token'];
    httpCheck($gate->confirm(DeleteThing::class, $me, $mcp(['confirm_token' => $token, 'id' => 7]), 'Delete thing 7') === null, 'the same call carrying its confirm token runs');
    httpCheck($isInvalid($gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7, 'confirm_token' => $token]), 'Delete thing 7')), 'a confirm token is single-use');
    $token = $gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7]), 'Delete thing 7')->getStructuredContent()['confirm_token'];
    httpCheck($isInvalid($gate->confirm(DeleteThing::class, new Principal((int) $people['admin']->id, $narrow), $mcp(['id' => 7, 'confirm_token' => $token]), 'Delete thing 7')), "another person's or connection's call cannot spend the token");
    httpCheck($isInvalid($gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7, 'confirm_token' => 'forged']), 'Delete thing 7')) && $isInvalid($gate->confirm(DeleteThing::class, $me, $mcp(['id' => 7, 'confirm_token' => '']), 'Delete thing 7')), 'a forged or empty confirm token is refused');
    $stale = $gate->confirm(DeleteThing::class, $me, $mcp(['id' => 9]), 'x')->getStructuredContent()['confirm_token'];
    Illuminate\Support\Carbon::setTestNow(now()->addSeconds(ConsentGate::CONFIRM_TTL + 1));
    httpCheck($isInvalid($gate->confirm(DeleteThing::class, $me, $mcp(['id' => 9, 'confirm_token' => $stale]), 'x')), 'an expired confirm token is refused');
    Illuminate\Support\Carbon::setTestNow();

    // Refusal codes.
    $validation = Illuminate\Validation\ValidationException::withMessages(['x' => 'bad']);
    httpCheck($gate->refusal(new RuntimeException('permission_denied')) === ['error' => 'permission_denied'] && $gate->refusal(new RuntimeException('confirmation_invalid')) === ['error' => 'confirmation_invalid'] && $gate->refusal($validation) === ['error' => 'validation_failed']
        && $gate->refusal(new Illuminate\Database\Eloquent\ModelNotFoundException) === ['error' => 'not_found'] && $gate->refusal(new RuntimeException('SQLSTATE secret detail')) === ['error' => 'operation_failed'] && $gate->refusal(new LogicException('x')) === ['error' => 'operation_failed'], 'refusals pass known codes through and hide everything else');

    // Audit: "<person> via <assistant>".
    $open($people['owner'], $after, $live, fn () => app(Audit::class)->write((int) $people['owner']->id, $workspace->id, 'test.via', ['n' => 1]));
    $details = json_decode((string) DB::table('audit_events')->where('event', 'test.via')->value('details'), true);
    httpCheck(($details['via'] ?? null) === ['kind' => 'assistant', 'connection_id' => $live, 'client' => 'Assistant'] && $details['n'] === 1, 'an audit entry made through a connection records the person via their assistant');
    $app['request']->attributes->remove('mcp.principal');
    app(Audit::class)->write((int) $people['owner']->id, $workspace->id, 'test.direct', []);
    httpCheck(! array_key_exists('via', (array) json_decode((string) DB::table('audit_events')->where('event', 'test.direct')->value('details'), true)), 'an audit entry made in the app carries no via tag');
    // The consent screen's approve and deny end in a redirect to the external client: each button shows a busy state on submit and resets when restored from the back-forward cache.
    $view = (string) file_get_contents(dirname(__DIR__).'/resources/views/mcp/authorize.blade.php');
    httpCheck(substr_count($view, 'data-kit-consent') >= 2 && str_contains($view, 'data-busy-label="Connecting…"') && str_contains($view, "setAttribute('aria-busy', 'true')"), 'the consent buttons show a busy state (label, aria-busy) while the approve or deny posts');
    httpCheck(str_contains($view, "addEventListener('pageshow'") && str_contains($view, 'event.persisted') && str_contains($view, "form.dataset.sent === '1'"), 'the consent buttons reset on a back-forward cache restore and refuse a second submit');
    echo "MCP consent checks on SQLite memory with real Passport tables, Concierge and spatie teams; tokens are inserted as Passport's repository does, with no OAuth HTTP exchange, consent screen rendering or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
