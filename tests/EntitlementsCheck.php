<?php

// Entitlements per feature: two features with different plans on one workspace, a limit enforced on the server with a clear error, and entitlements kept apart from
// capabilities and permissions. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
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

// A stand-in write that a plan caps: create a project, at most the plan's `projects` limit of them.
class ProjectsStubController
{
    public function store(Request $request, int $workspace): mixed
    {
        return app(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($workspace, function () use ($request): mixed {
            $tenant = app(Access::class)->authorize(new Principal((int) $request->user()->id), 'projects.write');
            app(Entitlements::class)->enforce('workspace.projects', 'projects', $tenant, DB::table('stub_projects')->where('workspace_id', $tenant->id)->count());
            DB::table('stub_projects')->insert(['workspace_id' => $tenant->id]);

            return response()->json(['created' => true], 201);
        });
    }
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
    $base = sys_get_temp_dir().'/kit-entitlements-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.reports' => true, 'workspace.projects' => true], 'entitlements' => ['workspace.projects' => ['included' => true, 'limits' => ['projects' => 2]]], 'mcp' => ['ui' => true]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // Two stand-in features on plans: Reports (in no plan by default, absent when left out) and Projects (included with a cap of two, and an upgrade action when left out).
    $routes = $base.'/projects-routes.php';
    file_put_contents($routes, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
        WorkspaceRoutes::define(function (bool $full): void {
            Route::post('projects', [ProjectsStubController::class, 'store']);
        });
        PHP);
    $app->make(Registry::class)->routes($routes)
        ->capabilities(['workspace.reports', 'workspace.projects'])->abilities(['reports.read' => 'workspace.reports', 'projects.read' => 'workspace.projects', 'projects.write' => 'workspace.projects'])
        ->permission('workspace.projects.create', ['owner', 'admin'], 'workspace.projects', ['projects.write'])
        ->entitlement('workspace.reports')
        ->entitlement('workspace.projects', ['projects'], ['label' => 'Upgrade to add projects', 'suffix' => 'settings/billing'])
        ->navigation('reports.read', 'Reports', 'reports')->placeholder('workspaces.reports.index', 'reports', 'reports.read', 'Reports', 'Reports on the workspace.')
        ->navigation('projects.read', 'Projects', 'projects')->placeholder('workspaces.projects.index', 'projects', 'projects.read', 'Projects', 'The workspace\'s projects.');
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
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
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

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
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    Schema::create('stub_projects', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
    });
    $entitlements = app(Entitlements::class);
    $request = function (string $who, string $method, string $path, bool $json = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, server: $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };
    $refusal = function (string $who, string $ability) use ($workspace, $people): ?string {
        try {
            $workspace->execute(fn () => app(Access::class)->authorize(new Principal((int) $people[$who]->id), $ability));

            return null;
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
    };
    $navigation = function () use ($request): array {
        [, $body] = $request('owner', 'GET', '/@acme');

        return array_column(json_decode($body, true)['props']['shell']['navigation'] ?? [], 'label');
    };

    // Two features, one workspace, different plans: Reports is left out (no default names it: closed), Projects is included.
    httpCheck(! $entitlements->included('workspace.reports', $workspace) && $entitlements->included('workspace.projects', $workspace), 'one workspace: Reports is not in its plan, Projects is');
    httpCheck($entitlements->included('workspace.dashboard', $workspace), 'a capability no entitlement gates is always included');
    httpCheck($request('owner', 'GET', '/@acme/reports')[0] === 404 && $request('owner', 'GET', '/@acme/projects')[0] === 200, 'Reports is a 404 and Projects opens');
    httpCheck(! in_array('Reports', $navigation(), true) && in_array('Projects', $navigation(), true), 'the shell lists Projects and not Reports');
    httpCheck($refusal('owner', 'reports.read') === 'entitlement_unavailable' && $refusal('owner', 'projects.read') === null, 'Access refuses Reports as unavailable and allows Projects');
    httpCheck($entitlements->upgrade('workspace.reports', $workspace) === null, 'Reports did not opt into an upgrade action, so it stays absent');

    // The workspace's own row, as a billing integration would write it, wins over the default; clearing it goes back to the default.
    $entitlements->set('workspace.reports', $workspace, true);
    httpCheck($request('owner', 'GET', '/@acme/reports')[0] === 200 && in_array('Reports', $navigation(), true), 'Reports added to the workspace\'s plan opens and is listed');
    $entitlements->set('workspace.projects', $workspace, false);
    [$status, $body] = $request('owner', 'POST', '/@acme/projects', true);
    httpCheck($status === 403 && str_contains($body, 'entitlement_required') && DB::table('stub_projects')->count() === 0, 'a write to Projects left out of the plan is refused with 403 entitlement_required (it opted into an upgrade)');
    httpCheck($request('owner', 'GET', '/@acme/projects')[0] === 404 && ! in_array('Projects', $navigation(), true), 'its planned page stays absent (placeholders refuse as 404) and the shell leaves it out');
    $upgrade = $entitlements->upgrade('workspace.projects', $workspace);
    httpCheck($upgrade !== null && $upgrade->label === 'Upgrade to add projects' && str_ends_with($upgrade->url, '/settings/billing') && $upgrade->method === 'get', 'the upgrade action is a server-built descriptor, not a plan check in the page');
    $entitlements->set('workspace.reports', $workspace, null);
    $entitlements->set('workspace.projects', $workspace, null);
    httpCheck(! $entitlements->included('workspace.reports', $workspace) && $entitlements->included('workspace.projects', $workspace) && $entitlements->upgrade('workspace.projects', $workspace) === null, 'cleared rows go back to the app default');

    // A limit enforced on the server: the default caps Projects at two.
    httpCheck($entitlements->limit('workspace.projects', 'projects', $workspace) === 2, 'the default caps projects at two');
    httpCheck($request('owner', 'POST', '/@acme/projects', true)[0] === 201 && $request('admin', 'POST', '/@acme/projects', true)[0] === 201, 'the first two projects are created');
    [$status, $body] = $request('owner', 'POST', '/@acme/projects', true);
    httpCheck($status === 403 && json_decode($body, true) === ['error' => 'limit_reached'] && DB::table('stub_projects')->count() === 2, 'a third is refused with 403 limit_reached and nothing is written');
    httpCheck($registry->message('limit_reached') === 'Your workspace has reached its plan\'s limit for this.', 'limit_reached carries a plain message for the error page');
    $entitlements->set('workspace.projects', $workspace, true, ['projects' => 3]);
    httpCheck($request('owner', 'POST', '/@acme/projects', true)[0] === 201 && $request('owner', 'POST', '/@acme/projects', true)[0] === 403, 'a plan raised to three allows one more, then refuses again');
    $entitlements->set('workspace.projects', $workspace, true, ['projects' => null]);
    httpCheck($entitlements->limit('workspace.projects', 'projects', $workspace) === null && $request('owner', 'POST', '/@acme/projects', true)[0] === 201, 'a null limit is unlimited');
    $entitlements->set('workspace.projects', $workspace, false);
    httpCheck($entitlements->limit('workspace.projects', 'projects', $workspace) === 0, 'a plan without the feature has a limit of zero');
    $entitlements->set('workspace.projects', $workspace, null);

    // Kept apart: entitled but switched off is a capability refusal; entitled and on but not permitted is a permission refusal.
    app(Capabilities::class)->setForWorkspace('workspace.projects', $workspace, false);
    [$status, $body] = $request('admin', 'POST', '/@acme/projects', true);
    httpCheck($status === 404 && str_contains($body, 'capability_unavailable') && $entitlements->included('workspace.projects', $workspace), 'entitled but switched off: 404 capability_unavailable, the entitlement untouched');
    app(Capabilities::class)->setForWorkspace('workspace.projects', $workspace, null);
    [$status, $body] = $request('member', 'POST', '/@acme/projects', true);
    httpCheck($status === 403 && str_contains($body, 'permission_denied'), 'entitled and on, but a member without the permission: 403 permission_denied');
    $entitlements->set('workspace.projects', $workspace, false);
    httpCheck(app(Capabilities::class)->active('workspace.projects', $workspace) && $workspace->execute(fn () => app(Tey\Kit\Features\Access\Permissions::class)->granted($people['admin'], 'workspace.projects.create', $workspace)), 'out of the plan, the capability stays on and the admin keeps the permission');
    $entitlements->set('workspace.projects', $workspace, null);

    // Memoized for a request like capabilities, and a write empties the memo.
    $entitlements->remember();
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $entitlements->included('workspace.reports', $workspace);
    $entitlements->included('workspace.projects', $workspace);
    $entitlements->limit('workspace.projects', 'projects', $workspace);
    httpCheck($queries === 1, 'in a request, the workspace\'s entitlement rows are read once');
    $entitlements->set('workspace.reports', $workspace, true);
    httpCheck($entitlements->included('workspace.reports', $workspace), 'a write in the request is seen straight away');
    $entitlements->set('workspace.reports', $workspace, null);

    // Fail closed on what nobody declared.
    foreach ([
        'an undeclared limit' => fn () => $entitlements->limit('workspace.projects', 'seats', $workspace),
        'an entitlement on an undeclared capability' => fn () => (new Registry)->entitlement('workspace.unknown'),
        'setting an undeclared entitlement' => fn () => $entitlements->set('workspace.dashboard', $workspace, true),
    ] as $label => $call) {
        try {
            $call();
            $thrown = false;
        } catch (RuntimeException) {
            $thrown = true;
        }
        httpCheck($thrown, $label.' is refused');
    }
    echo "Entitlement checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON and JSON through router dispatch without the global middleware stack, stand-in features, no browser, MCP transport or billing provider.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
