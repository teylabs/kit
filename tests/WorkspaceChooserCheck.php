<?php

// The workspace list (/me/workspaces): only the person's own workspaces, each with their role, the member count and a Current marker; New workspace only while
// provisioning allows it; an empty state with no workspace; open invitations to their verified email; and nothing from a workspace they are not in, in a few queries.
// SQLite memory, real Concierge, Fortify and Inertia, synthetic users and workspaces (Northwind Farms, Acme Travel, Globex Labs); no network.
use Carbon\CarbonInterface;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
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

class HttpUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-chooser-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true]],
        'concierge' => ['invitations' => ['deliver' => true]], 'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FoundationServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    // The kit's lang, as KitServiceProvider loads it: the plain breach message comes from kit::validation.
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
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
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $provision = function (string $name, string $slug) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $join = function (HttpUser $user, Workspace $workspace, string $role, MembershipStatus $status = MembershipStatus::Active) use ($sync): void {
        $membership = ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => $status, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $user->assignRole($role), [$user]);
        if ($role === 'owner') {
            ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $membership->id]);
        }
        $sync->run(new ArrayInput([]), new NullOutput);
    };
    $invite = function (Workspace $workspace, string $email, ?HttpUser $inviter, string $status = 'pending', ?CarbonInterface $expires = null): void {
        ConciergeModels::invitation()::query()->forceCreate(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'email' => $email, 'grants' => '[]', 'grants_fingerprint' => str_repeat('0', 64), 'inviter_id' => $inviter?->id, 'token_hash' => bin2hex(random_bytes(32)), 'status' => $status, 'expires_at' => $expires ?? now()->addDays(7)]);
    };
    $northwind = $provision('Northwind Farms', 'northwind');
    $acme = $provision('Acme Travel', 'acme');
    $globex = $provision('Globex Labs', 'globex');
    $ada = HttpUser::create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now()]);
    $ben = HttpUser::create(['name' => 'Ben Sample', 'email' => 'ben@example.test', 'email_verified_at' => now()]);
    $cy = HttpUser::create(['name' => 'Cy Outsider', 'email' => 'cy@example.test', 'email_verified_at' => now()]);
    $dee = HttpUser::create(['name' => 'Dee Former', 'email' => 'dee@example.test', 'email_verified_at' => now()]);
    $nell = HttpUser::create(['name' => 'Nell Newcomer', 'email' => 'nell@example.test', 'email_verified_at' => now()]);
    $una = HttpUser::create(['name' => 'Una Unverified', 'email' => 'una@example.test']);
    // Ada owns Northwind (with Ben, an admin, and Dee, suspended: not counted) and is an admin in Acme (Ben owns it). Globex is Cy's alone: Ada is not in it.
    $join($ada, $northwind, 'owner');
    $join($ben, $northwind, 'admin');
    $join($dee, $northwind, 'admin', MembershipStatus::Suspended);
    $join($ben, $acme, 'owner');
    $join($ada, $acme, 'admin');
    $join($cy, $globex, 'owner');
    $invite($globex, 'ada@example.test', $cy);
    $invite($globex, 'ada@example.test', $cy, 'revoked');
    $invite($globex, 'ada@example.test', $cy, 'pending', now()->subDay());
    $invite($globex, 'nell@example.test', $cy);
    $invite($globex, 'una@example.test', $cy);

    // One Inertia GET through the router, with a session the array handler keeps (cookie `s`): [status, the decoded page].
    $handler = $app['session']->driver()->getHandler();
    $send = function (?object $user, string $path, array $session = []) use ($app, $handler): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $app['session']->driver()->flush();
        $id = bin2hex(random_bytes(20));
        $handler->write($id, serialize(['_token' => 'token', ...$session]));
        $request = Request::create('https://kit.test'.$path, 'GET', [], ['s' => $id], server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => Auth::guard('web')->user());
        $app->instance('request', $request);
        RequestMemo::instance()->flush();
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };
    $row = fn (array $page, string $name): ?array => collect($page['props']['workspaces'] ?? [])->firstWhere('name', $name);

    // Ada: her two workspaces, never Globex; her role in each, the active member count (Dee, suspended, is not counted), and Leave only where she is not the owner.
    [$status, $page] = $send($ada, '/me/workspaces');
    httpCheck($status === 200 && ($page['component'] ?? null) === 'Workspace/Index/Page', 'Ada opens /me/workspaces: the list ('.$status.')');
    httpCheck(array_column($page['props']['workspaces'], 'name') === ['Acme Travel', 'Northwind Farms'], 'only her own workspaces, by name: never Globex Labs ('.json_encode(array_column($page['props']['workspaces'], 'name')).')');
    $nw = $row($page, 'Northwind Farms');
    $ac = $row($page, 'Acme Travel');
    httpCheck($nw['role'] === 'Owner' && $nw['members'] === 2 && $nw['leave'] === null, 'Northwind: Owner, 2 active members (the suspended one not counted), no Leave ('.json_encode([$nw['role'], $nw['members']]).')');
    httpCheck($ac['role'] === 'Admin' && $ac['members'] === 2 && ($ac['leave']['url'] ?? null) === '/me/workspaces/acme/membership', 'Acme: Admin (her role there, not Northwind\'s), 2 members, Leave ('.json_encode([$ac['role'], $ac['members']]).')');
    httpCheck($nw['initials'] === 'NF' && is_string($nw['url']) && str_contains($nw['url'], 'northwind'), 'each workspace carries its initials mark and the link into it');
    httpCheck(! str_contains(json_encode($page['props']['workspaces']), 'Globex') && ! str_contains(json_encode($page['props']['workspaces']), 'Cy Outsider'), 'no other workspace\'s data in the list');
    httpCheck($nw['current'] === false && $ac['current'] === false, 'no workspace in use: none is Current');
    [, $page] = $send($ada, '/me/workspaces', ['kit' => ['workspace' => $acme->id]]);
    httpCheck($row($page, 'Acme Travel')['current'] === true && $row($page, 'Northwind Farms')['current'] === false, 'the workspace last used (the session\'s) is Current, only it');
    [, $page] = $send($ada, '/me/workspaces', ['kit' => ['workspace' => $globex->id]]);
    httpCheck(collect($page['props']['workspaces'])->where('current', true)->isEmpty() && ! str_contains(json_encode($page['props']['workspaces']), 'Globex'), 'a session pointing at a workspace she is not in marks none Current and lists nothing of it');

    // Invitations: only open ones to her own verified email, never another person's, a revoked or an expired one.
    httpCheck(count($page['props']['invitations']) === 1 && $page['props']['invitations'][0]['workspace'] === 'Globex Labs' && $page['props']['invitations'][0]['inviter'] === 'Cy Outsider', 'her one open invitation is listed, with its inviter ('.json_encode($page['props']['invitations']).')');

    // New workspace: absent by default (invite_only), present with self_serve, absent again for anything unknown.
    httpCheck($page['props']['create'] === null, 'invite_only (the default): no New workspace action');
    $config->set('kit.workspaces.provisioning', 'self_serve');
    [, $page] = $send($ada, '/me/workspaces');
    httpCheck(($page['props']['create']['label'] ?? null) === 'New workspace' && ($page['props']['create']['url'] ?? null) === '/me/workspaces/create', 'self_serve: New workspace is offered');
    $config->set('kit.workspaces.provisioning', 'something_else');
    [, $page] = $send($ada, '/me/workspaces');
    httpCheck($page['props']['create'] === null, 'an unknown provisioning value offers nothing (fail closed)');
    $config->set('kit.workspaces.provisioning', 'invite_only');

    // No workspace: the page renders its empty state (no redirect), with her invitation; with self_serve, the action too.
    [$status, $page] = $send($nell, '/me/workspaces');
    httpCheck($status === 200 && $page['props']['workspaces'] === [] && count($page['props']['invitations']) === 1 && $page['props']['create'] === null, 'no workspace: the list renders empty, with the open invitation ('.$status.')');
    $config->set('kit.workspaces.provisioning', 'self_serve');
    [, $page] = $send($nell, '/me/workspaces');
    httpCheck($page['props']['create'] !== null, 'no workspace, self_serve: New workspace is offered');
    $config->set('kit.workspaces.provisioning', 'invite_only');

    // A suspended membership is not a workspace of yours; an unverified email sees no invitations; a guest gets nothing.
    [, $page] = $send($dee, '/me/workspaces');
    httpCheck(($page['props']['workspaces'] ?? null) === [], 'suspended in Northwind: it is not listed');
    [$status, $page] = $send($una, '/me/workspaces');
    httpCheck(($page['props']['invitations'] ?? []) === [], 'an unverified email lists no invitations ('.$status.')');
    httpCheck($send(null, '/me/workspaces')[0] !== 200, 'a guest gets no list');

    // No N+1: the list's own reads (memberships, member counts, ownerships, roles) are one query each, for six workspaces.
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    foreach (range(1, 4) as $i) {
        $join($ada, $provision('Many Works '.$i, 'many-'.$i), 'admin');
    }
    $queries = [];
    [, $page] = $send($ada, '/me/workspaces');
    $patterns = ['memberships' => '/from "concierge_memberships" where "user_id" = \? and "status" = \? and "workspace_id" in/', 'counts' => '/count\(\*\) as aggregate from "concierge_memberships"/', 'owners' => '/from "concierge_ownerships" where "workspace_id" in/', 'roles' => '/from "model_has_roles" inner join "roles"/'];
    $seen = array_map(fn (string $pattern): int => count(preg_grep($pattern, $queries)), $patterns);
    httpCheck(count($page['props']['workspaces']) === 6 && $seen === ['memberships' => 1, 'counts' => 1, 'owners' => 1, 'roles' => 1], 'six workspaces: role, count and ownership are one query each, not one per workspace ('.json_encode($seen).')');
    echo "Workspace list checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
