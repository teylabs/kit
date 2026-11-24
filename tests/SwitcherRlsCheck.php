<?php

// The shell's lazy switcher under PostgreSQL row-level security (kit.tenancy.rls), on Inertia pages and its JSON links (GET /me/switcher) through the
// router, as a NOBYPASSRLS runtime role. PostgreSQL only: it exits 1 on any other DB_CONNECTION. It creates its own schema (kit_switcher_rls) and role
// (kit_switcher_rls_runtime) in the database the DB_* variables name, as their superuser (the admin/migrator connection), and drops them at the end; point it
// only at a throwaway database. Covered: the page lists the workspaces and reads no other workspace's rows, at the same queries with one workspace or five;
// the switcher's links read protected tables (capability overrides and plan rows) only inside the workspace they belong to, and decide each workspace by its
// own roles, grants, plan rows and overrides, also with a protected table listed in `except` or a protected permission pivot; the tenant, the Concierge
// context, the permission team, the RLS setting and the request's memo are restored after each workspace, also when a check throws; and switching
// workspace mid-request, also after a role or grant changes there, answers from that workspace, never a stale memo.
// Synthetic users and workspaces only. Not covered: a pooler, Octane, a browser, Pennant's database store.
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
use Illuminate\Database\Events\QueryExecuted;
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
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Rls;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function switcherCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class SwitcherUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(SwitcherUser::class, 'App\Models\User');

/** What a request has current: the tenant, Concierge's context, spatie's permission team, the transaction's RLS setting and its depth. */
function switcherState(): array
{
    return [
        'tenant' => Workspace::current()?->getKey(),
        'context' => app(Concierge::class)->context()->current()?->getKey(),
        'team' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        'rls' => Rls::current(),
        'level' => DB::transactionLevel(),
    ];
}

/** Workspace pages the check opens inside the request, with the workspace's app scoping and RLS transaction already set by ResolveWorkspace. */
final class SwitcherProbeController
{
    /** Builds the shell and the switcher's links inside the page, then the links again; the state before, after the first build (which may throw) and the second build's links. */
    public function shell(Request $request): mixed
    {
        $before = switcherState();
        $memo = RequestMemo::instance();
        $memo->get('probe', fn (): int => 1);
        $error = null;
        try {
            app(PageShell::class)->forRequest($request);
            app(PageShell::class)->landings($request);
        } catch (Throwable $thrown) {
            $error = $thrown->getMessage();
        }
        $after = switcherState();
        // The request's memo is still on and still this request's: an answer it holds is kept.
        $kept = $memo === RequestMemo::instance() && $memo->get('probe', fn (): int => 2) === 1;
        $landings = app(PageShell::class)->landings($request);
        $choices = collect(MemberWorkspaces::workspaces($request->user()))->mapWithKeys(fn ($workspace): array => [$workspace->name => $landings[(int) $workspace->getKey()] ?? null])->all();

        return response()->json(['before' => $before, 'after' => $after, 'error' => $error, 'kept' => $kept, 'choices' => $choices, 'final' => switcherState()]);
    }

    /** The page's own gate in its workspace: allowed, or the refusal's code. */
    public function gate(Request $request): mixed
    {
        try {
            app(Access::class)->authorize(new Principal((int) $request->user()->getKey()), (string) $request->query('ability'));

            return response()->json(['gate' => 'allowed']);
        } catch (RuntimeException $refusal) {
            return response()->json(['gate' => $refusal->getMessage()]);
        }
    }

    /** Switches workspace mid-request, changing a role and a grant on the way, and asks the kit's gate and spatie's own check in each. */
    public function switch(Request $request): mixed
    {
        $user = $request->user();
        $access = app(Access::class);
        $ids = collect(MemberWorkspaces::workspaces($user))->keyBy(fn ($workspace) => $workspace->slug);
        $ask = function (string $slug) use ($access, $user, $ids): array {
            return Workspaces::run($ids[$slug], function () use ($access, $user, $ids, $slug): array {
                try {
                    $access->authorize(new Principal((int) $user->getKey()), 'members.manage');
                    $gate = true;
                } catch (RuntimeException) {
                    $gate = false;
                }
                $spatie = app(Concierge::class)->context()->run($ids[$slug], fn (): bool => $user->hasPermissionTo('workspace.members.manage', 'web'), [$user]);

                return [$gate, $spatie, Rls::current() === (int) $ids[$slug]->getKey()];
            });
        };
        $steps = [];
        $steps['home'] = $ask('switch-home');
        $steps['member'] = $ask('switch-member');
        $steps['home again'] = $ask('switch-home');
        // Admin, not asked yet, loses its admin role; Member, asked above, is given the grant. Each in its own workspace.
        Workspaces::run($ids['switch-admin'], function () use ($user, $ids): void {
            app(Concierge::class)->context()->run($ids['switch-admin'], fn () => $user->removeRole('admin'), [$user]);
        });
        Workspaces::run($ids['switch-member'], function () use ($user, $ids): void {
            app(Concierge::class)->context()->run($ids['switch-member'], fn () => $user->givePermissionTo('workspace.members.manage'), [$user]);
        });
        $steps['admin after the role is removed'] = $ask('switch-admin');
        $steps['member after the grant'] = $ask('switch-member');
        $steps['home at the end'] = $ask('switch-home');

        return response()->json(['steps' => $steps, 'final' => switcherState()]);
    }
}

if (getenv('DB_CONNECTION') !== 'pgsql') {
    fwrite(STDERR, "FAIL SwitcherRlsCheck is PostgreSQL only (row-level security does not exist on SQLite): set DB_CONNECTION=pgsql and the DB_* variables.\n");
    exit(1);
}

$runtimeRole = 'kit_switcher_rls_runtime';
try {
    $server = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_rls_check', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'kit_switcher_rls', 'sslmode' => 'prefer'];
    $password = bin2hex(random_bytes(16));
    $base = sys_get_temp_dir().'/kit-switcher-rls-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $except = (require dirname(__DIR__).'/config/kit.php')['tenancy']['rls']['except'];
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => SwitcherUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        // The admin (migrator, owner, superuser here) migrates and seeds; the app runs as the runtime role, created below.
        'database' => ['default' => 'pgsql_admin', 'connections' => [
            'pgsql_admin' => $server + ['username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: ''],
            'pgsql' => $server + ['username' => $runtimeRole, 'password' => $password],
        ]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => SwitcherUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true],
            'tenancy' => ['rls' => ['enabled' => true, 'connection' => null, 'admin_connection' => 'pgsql_admin', 'except' => $except]]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
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
    $routes = $base.'/probe-routes.php';
    file_put_contents($routes, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
        WorkspaceRoutes::define(function (bool $full): void {
            Route::get('switcher-shell', [SwitcherProbeController::class, 'shell']);
            Route::get('switcher-switch', [SwitcherProbeController::class, 'switch']);
            Route::get('switcher-gate', [SwitcherProbeController::class, 'gate']);
        });
        PHP);
    $registry = $app->make(Registry::class);
    $registry->routes($routes);
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);
    $admin = DB::connection('pgsql_admin');
    echo 'Database: '.$admin->getDriverName().' '.$admin->selectOne('show server_version')->server_version."\n";

    // A clean slate: our schema and our role, from any earlier run.
    $drop = function () use ($runtimeRole): void {
        $db = DB::connection('pgsql_admin');
        $db->statement('drop schema if exists kit_switcher_rls cascade');
        if ($db->selectOne('select 1 as found from pg_roles where rolname = ?', [$runtimeRole]) !== null) {
            $db->statement('drop owned by "'.$runtimeRole.'"');
            $db->statement('drop role "'.$runtimeRole.'"');
        }
    };
    $drop();
    $admin->statement('create schema kit_switcher_rls');
    DB::purge('pgsql_admin');
    $admin = DB::connection('pgsql_admin');

    // The host's users, then the kit's stubs with Concierge's tables right after the workspaces table (foreign keys are enforced), Passport's and medialibrary's.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach ($stubs as $name => $file) {
        (require $file)->up();
        if (str_contains($name, '000200_create_kit_workspaces_table')) {
            foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $migration) {
                (require $migration)->up();
            }
        }
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $app['router']->getRoutes()->refreshNameLookups();
    // The two tables the switcher's checks read that hold one workspace's rows each: protected, as an app protects them in its own migration.
    $protected = ['capability_overrides', 'plan_entitlements'];
    foreach ($protected as $table) {
        Rls::protect($table);
    }
    Rls::grantRuntimeRole($runtimeRole, $password, $admin);

    // Fixtures, as the migrator (the way seeders and operators would).
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry->navigation('members.manage', 'People', 'people', order: -20);
    $registry->entitlement('workspace.dashboard');
    $provision = function (string $name, string $slug) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $join = function (SwitcherUser $user, Workspace $workspace, string $role) use ($sync): void {
        $membership = ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $user->assignRole($role), [$user]);
        if ($role === 'owner') {
            ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $membership->id]);
        }
        $sync->run(new ArrayInput([]), new NullOutput);
    };
    $plan = fn (Workspace $workspace) => app(Entitlements::class)->set('workspace.dashboard', $workspace, true);
    $sam = SwitcherUser::create(['name' => 'Sam Switcher', 'email' => 'sam@example.test', 'email_verified_at' => now()]);
    $cy = SwitcherUser::create(['name' => 'Cy Outsider', 'email' => 'cy@example.test', 'email_verified_at' => now()]);
    // Home: a member with a direct members.manage grant there (People), on the plan.
    $home = $provision('Switch Home', 'switch-home');
    $join($sam, $home, 'member');
    app(Concierge::class)->context()->run($home, fn () => $sam->givePermissionTo('workspace.members.manage'), [$sam]);
    $plan($home);
    // Outside: Cy's alone, with an override and a plan row naming Sam. Nothing of it may reach Sam's switcher.
    $outside = $provision('Switch Outside', 'switch-outside');
    $join($cy, $outside, 'owner');
    $plan($outside);
    app(Capabilities::class)->setForPerson('workspace.settings', $outside, (int) $sam->id, true);

    // The app runs as the runtime role from here.
    $seed = function (Closure $fn) use ($config): void {
        $config->set('database.default', 'pgsql_admin');
        try {
            $fn();
        } finally {
            $config->set('database.default', 'pgsql');
        }
    };
    $config->set('database.default', 'pgsql');
    DB::purge('pgsql');
    switcherCheck(DB::selectOne('select current_user as name')->name === $runtimeRole && Rls::enabled() && ! DB::selectOne('select rolbypassrls as b from pg_roles where rolname = current_user')->b, 'the app connects as the runtime role (NOBYPASSRLS), with RLS on');
    switcherCheck(DB::table('capability_overrides')->count() === 0 && DB::table('plan_entitlements')->count() === 0 && $admin->table('plan_entitlements')->count() === 2, 'outside a workspace, the protected tables show the runtime role no rows (the admin sees them)');

    // Every query the runtime connection runs, with the transaction's workspace id at that moment. `$trip` throws once from the query it matches.
    $log = [];
    $trip = null;
    $probing = false;
    DB::listen(function (QueryExecuted $query) use (&$log, &$trip, &$probing): void {
        if ($query->connectionName !== 'pgsql' || $probing) {
            return;
        }
        $probing = true;
        try {
            $setting = $query->connection->selectOne("select nullif(current_setting('kit.workspace_id', true), '') as id")->id;
        } finally {
            $probing = false;
        }
        $log[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'setting' => $setting === null ? null : (int) $setting];
        if ($trip !== null && $trip($query->sql, $setting === null ? null : (int) $setting)) {
            $trip = null;
            throw new RuntimeException('tripped');
        }
    });
    // One Inertia GET through the router (or, `$json`, a plain JSON one, as the switcher loads its links): [status, the decoded body].
    $handler = $app['session']->driver()->getHandler();
    $send = function (string $path, bool $json = false) use ($app, $handler, $sam, &$log): array {
        Auth::guard('web')->logout();
        Auth::guard('web')->setUser($sam);
        $app['session']->driver()->flush();
        $id = bin2hex(random_bytes(20));
        $handler->write($id, serialize(['_token' => 'token']));
        $headers = $json ? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $request = Request::create('https://kit.test'.$path, 'GET', [], ['s' => $id], server: $headers);
        $request->setUserResolver(fn () => Auth::guard('web')->user());
        $app->instance('request', $request);
        RequestMemo::instance()->flush();
        $log = [];
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };
    $names = fn (): array => Workspace::on('pgsql_admin')->pluck('name', 'id')->all();
    // The page's switcher: the workspaces it lists, by name (with no link). The switcher, opened: [status, the page each workspace opens on, by name].
    $listed = fn (array $page): array => array_column($page['props']['shell']['switcher']['choices'] ?? [], 'slug', 'name');
    $opened = function () use ($send, $names): array {
        [$status, $body] = $send('/me/switcher', json: true);
        $name = $names();

        return [$status, collect($body['workspaces'] ?? [])->mapWithKeys(fn (array $landing): array => [$name[$landing['id']] => $landing['url']])->all()];
    };
    // The switcher's links with no request memo: each check spatie's own, read in its own workspace.
    $unmemoized = function () use ($app, $sam, $names): array {
        $app->forgetScopedInstances();
        $request = Request::create('https://kit.test/me/switcher');
        $request->setUserResolver(fn () => $sam);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        $name = $names();

        return collect(app(PageShell::class)->landings($request))->mapWithKeys(fn (?string $url, int $id): array => [$name[$id] => $url])->all();
    };
    // Queries on a protected table, and those among them not confined to the workspace the transaction carries: a batch (`workspace_id in`), a read outside
    // any workspace, or a read of one workspace while another is current.
    $protectedReads = function () use (&$log, $protected): array {
        return array_values(array_filter($log, fn (array $query): bool => preg_match('/"('.implode('|', $protected).')"/', $query['sql']) === 1));
    };
    $misplaced = fn (array $reads): array => array_values(array_filter($reads, function (array $query): bool {
        $at = strpos($query['sql'], '"workspace_id" = ?');
        if ($at === false || str_contains($query['sql'], '"workspace_id" in (') || $query['setting'] === null) {
            return true;
        }

        return (int) $query['bindings'][substr_count(substr($query['sql'], 0, $at), '?')] !== $query['setting'];
    }));
    // A page's queries, after the same page once (so caches the first request fills are not counted).
    $pageQueries = function (string $path) use ($send, &$log): array {
        $send($path);
        [$status, $page] = $send($path);

        return [$status, $page, count($log)];
    };

    // One workspace.
    [$status, $page, $onePage] = $pageQueries('/@switch-home');
    switcherCheck($status === 200 && $listed($page) === ['Switch Home' => 'switch-home'] && ($page['props']['shell']['workspace']['url'] ?? null) === '/people' && $misplaced($protectedReads()) === [], 'one workspace under RLS: the page renders, names Home with its page, and its protected rows are read only inside it ('.$status.')');

    // Four more: an admin on the plan (People); a member on the plan (Dashboard: Home's grant is Home's alone); a member off the plan (Settings); an admin on
    // the plan with the workspace's settings capability off and the dashboard off for Sam alone (unavailable). Overrides and plan rows are protected rows.
    $seed(function () use ($join, $plan, $provision, $sam, &$admin_, &$member, &$unplanned, &$off): void {
        $join($sam, $admin_ = $provision('Switch Admin', 'switch-admin'), 'admin');
        $plan($admin_);
        $join($sam, $member = $provision('Switch Member', 'switch-member'), 'member');
        $plan($member);
        $join($sam, $unplanned = $provision('Switch Unplanned', 'switch-unplanned'), 'member');
        $join($sam, $off = $provision('Switch Off', 'switch-off'), 'admin');
        $plan($off);
        app(Capabilities::class)->setForWorkspace('workspace.settings', $off, false);
        app(Capabilities::class)->setForPerson('workspace.dashboard', $off, (int) $sam->id, false);
    });
    $expected = ['Switch Home' => '/@switch-home/people', 'Switch Admin' => '/@switch-admin/people', 'Switch Member' => '/@switch-member', 'Switch Unplanned' => '/@switch-unplanned/settings', 'Switch Off' => null];
    $ids = [$home->id, $admin_->id, $member->id, $unplanned->id, $off->id];

    // (1) The page lists the five by name and slug, enters only Home, and costs the same queries as with one workspace.
    [$status, $page, $fivePage] = $pageQueries('/@switch-home');
    $reads = $protectedReads();
    switcherCheck($status === 200 && $listed($page) === ['Switch Home' => 'switch-home', 'Switch Admin' => 'switch-admin', 'Switch Member' => 'switch-member', 'Switch Unplanned' => 'switch-unplanned', 'Switch Off' => 'switch-off'] && ! str_contains(json_encode($page['props']['shell']['switcher']), '"url"'), 'five workspaces under RLS: the page\'s switcher lists each by name and slug, with no link ('.json_encode($listed($page)).')');
    switcherCheck($misplaced($reads) === [] && collect($reads)->pluck('setting')->unique()->values()->all() === [$home->id], 'the page reads protected rows only in Home, the workspace it shows ('.json_encode(collect($reads)->pluck('setting')->unique()->values()->all()).')');
    // The tenant setting is set and read once per page, not at every Workspaces::current() and nested within() (Hubcommons, query tuning round 2).
    $tenant = collect($log)->filter(fn (array $q): bool => str_contains($q['sql'], 'set_config') || str_contains($q['sql'], 'current_setting(?'))->countBy(fn (array $q): string => str_contains($q['sql'], 'set_config') ? 'set:'.json_encode($q['bindings']) : 'read')->all();
    switcherCheck(max($tenant ?: [0]) <= 1, 'the page sets the tenant setting at most once per value and reads it at most once ('.json_encode($tenant).')');
    switcherCheck($fivePage === $onePage, 'the page costs the same queries under RLS with one workspace and five ('.$onePage.' and '.$fivePage.')');

    // (2) The switcher, opened: each workspace decided inside it. Protected tables never batched, never read outside the workspace they belong to, and
    // nothing of a workspace Sam is not in.
    [$status, $links] = $opened();
    $reads = $protectedReads();
    $settings = collect($reads)->pluck('setting')->unique()->sort()->values()->all();
    switcherCheck($status === 200 && $links === $expected, 'five workspaces under RLS, the switcher opened: each lands by its own role, grant, plan row and overrides ('.json_encode($links).')');
    switcherCheck($reads !== [] && $misplaced($reads) === [], 'every read of a protected table ('.count($reads).') names one workspace, the one the transaction carries: none batched, none outside a workspace');
    switcherCheck($settings === collect($ids)->sort()->values()->all(), 'the protected rows are read in each of her five workspaces, and never in one she is not in ('.json_encode($settings).')');
    switcherCheck(! array_key_exists('Switch Outside', $links) && ! str_contains(json_encode($page['props']), 'Switch Outside') && ! str_contains(json_encode($page['props']), 'switch-outside'), 'nothing of a workspace she is not in reaches the page or the links, though its override and plan row name her');
    switcherCheck($unmemoized() === $expected, 'the links read without the request memo, workspace by workspace under RLS, decide the same');

    // A table `except` lists that is protected anyway, or a protected permission pivot: every read is still made inside its own workspace, so each decides as before.
    [$status, $body] = $send('/@switch-off/switcher-gate?ability=settings.read');
    switcherCheck($status === 200 && $body === ['gate' => 'capability_unavailable'], 'in Switch Off, the page\'s own gate refuses settings (its settings capability is off there) ('.json_encode($body).')');
    $config->set('kit.tenancy.rls.except', [...$except, ...$protected]);
    [$status, $links] = $opened();
    switcherCheck($status === 200 && $links === $expected, 'with the protected tables also listed in except, the switcher decides the same ('.json_encode($links).')');
    switcherCheck($misplaced($protectedReads()) === [], 'and each protected table is still read only inside its own workspace');
    [$status, $body] = $send('/@switch-off/switcher-gate?ability=settings.read');
    switcherCheck($status === 200 && $body === ['gate' => 'capability_unavailable'], 'and Switch Off\'s own gate still refuses settings ('.json_encode($body).')');
    $config->set('kit.tenancy.rls.except', $except);
    $seed(fn () => Rls::protect('model_has_permissions'));
    [$status, $links] = $opened();
    switcherCheck($status === 200 && $links === $expected, 'with a permission pivot protected while except still lists it, Home\'s direct grant still shows People ('.json_encode($links).')');
    $seed(fn () => Rls::unprotect('model_has_permissions'));

    // (3) Each workspace's checks leave the request as they found it: the tenant, Concierge's context, the permission team, the RLS setting, the transaction
    // depth and the request's memo. Also when one throws, and the next build answers as before (nothing half-read was kept).
    $inHome = ['tenant' => $home->id, 'context' => $home->id, 'team' => $home->id, 'rls' => $home->id, 'level' => 1];
    [$status, $body] = $send('/@switch-home/switcher-shell');
    switcherCheck($status === 200 && $body['error'] === null && $body['before'] === $inHome && $body['after'] === $inHome && $body['final'] === $inHome && $body['kept'] && $body['choices'] === $expected, 'after the shell and the switcher\'s links, built inside Home across five workspaces, the page is back in Home: tenant, context, team, RLS setting and memo ('.json_encode($body['after']).')');
    $trip = fn (string $sql, ?int $setting): bool => str_contains($sql, '"capability_overrides"') && $setting === $admin_->id;
    [$status, $body] = $send('/@switch-home/switcher-shell');
    switcherCheck($status === 200 && $body['error'] === 'tripped' && $body['after'] === $inHome && $body['kept'], 'when a check throws inside another workspace (Switch Admin), the page is back in Home: tenant, context, team, RLS setting and memo ('.json_encode($body['after']).')');
    switcherCheck($body['choices'] === $expected && $body['final'] === $inHome, 'and the links built next decide as before');
    // The links' own request throws in a workspace: it fails (never a half-built list), and nothing is left behind.
    $trip = fn (string $sql, ?int $setting): bool => str_contains($sql, '"capability_overrides"') && $setting === $member->id;
    [$status] = $send('/me/switcher', json: true);
    $after = switcherState();
    switcherCheck($status === 500 && $trip === null && $after === ['tenant' => null, 'context' => null, 'team' => null, 'rls' => null, 'level' => 0], 'the switcher\'s links request, throwing inside Switch Member, fails and leaves no tenant, context, team, RLS setting or transaction ('.json_encode($after).')');
    [$status, $links] = $opened();
    switcherCheck($status === 200 && $links === $expected, 'the next request decides as before');

    // (4) Switching workspace mid-request answers from the workspace switched to, also after a role or a grant changes there in the same request: the
    // request's memo for a workspace is never reused once what it read has changed. Each answer is [the kit's gate, spatie's own check, the RLS setting is that workspace].
    [$status, $body] = $send('/@switch-home/switcher-switch');
    $steps = $body['steps'] ?? [];
    switcherCheck($status === 200 && array_slice($steps, 0, 3) === ['home' => [true, true, true], 'member' => [false, false, true], 'home again' => [true, true, true]], 'Home, Member and Home again in one request: each answers members.manage from its own roles and grants ('.json_encode(array_slice($steps, 0, 3)).')');
    switcherCheck(($steps['admin after the role is removed'] ?? null) === [false, false, true], 'Admin\'s admin role removed mid-request, before anything asked there: People is refused at once ('.json_encode($steps['admin after the role is removed'] ?? null).')');
    switcherCheck(($steps['member after the grant'] ?? null) === [true, true, true] && ($steps['home at the end'] ?? null) === [true, true, true], 'Member given the grant mid-request, after it was asked: allowed there at once, and Home unchanged ('.json_encode([$steps['member after the grant'] ?? null, $steps['home at the end'] ?? null]).')');
    switcherCheck(($body['final'] ?? null) === $inHome, 'and the request is back in Home at the end');
    $seed(function () use ($sam, $admin_, $member): void {
        app(Concierge::class)->context()->run($admin_, fn () => $sam->assignRole('admin'), [$sam]);
        app(Concierge::class)->context()->run($member, fn () => $sam->revokePermissionTo('workspace.members.manage'), [$sam]);
    });
    [$status, $links] = $opened();
    switcherCheck($status === 200 && $links === $expected, 'the next request reads the restored roles ('.json_encode($links).')');

    // (5) The same switches with spatie's tables configured schema-qualified (kit_switcher_rls.roles and so on), as spatie then writes them
    // ("kit_switcher_rls"."model_has_permissions"): Member, asked first, is given the grant and answers at once, not from the set read before.
    $tables = (array) $config->get('permission.table_names');
    $config->set('permission.table_names', array_map(fn (string $table): string => 'kit_switcher_rls.'.$table, $tables));
    [$status, $body] = $send('/@switch-home/switcher-switch');
    $config->set('permission.table_names', $tables);
    $steps = $body['steps'] ?? [];
    switcherCheck($status === 200 && ($steps['member'] ?? null) === [false, false, true] && ($steps['member after the grant'] ?? null) === [true, true, true] && ($steps['admin after the role is removed'] ?? null) === [false, false, true], 'with schema-qualified table names, a grant and a role removed mid-request are answered at once ('.json_encode($steps).')');
    $seed(function () use ($sam, $admin_, $member): void {
        app(Concierge::class)->context()->run($admin_, fn () => $sam->assignRole('admin'), [$sam]);
        app(Concierge::class)->context()->run($member, fn () => $sam->revokePermissionTo('workspace.members.manage'), [$sam]);
    });

    echo "Lazy switcher under PostgreSQL RLS: the page's cost, protected tables read per workspace, restoration after a throw, mid-request switches; no pooler, Octane or browser.\n";
    $code = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    $code = 1;
} finally {
    try {
        DB::disconnect('pgsql');
        if (isset($drop)) {
            $drop();
        }
    } catch (Throwable $error) {
        fwrite(STDERR, 'Cleanup: '.$error->getMessage()."\n");
    }
}
exit($code);
