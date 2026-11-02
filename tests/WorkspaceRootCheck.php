<?php

// The workspace root without a dashboard: with workspace.dashboard off, /@{workspace} (and "/" in the short form) opens the viewer's first primary navigation page
// they may open, else the workspace list; on, the dashboard as before. Any other refusal of dashboard.read stands. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
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
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
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
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
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

class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-root-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.reports' => true, 'workspace.projects' => true], 'mcp' => ['ui' => true]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // Two pages an app adds to the primary navigation, Reports before Projects; Reports and the dashboard are for owners and admins, Reports for owners only.
    $app->make(Registry::class)->capabilities(['workspace.reports', 'workspace.projects'])->abilities(['reports.read' => 'workspace.reports', 'projects.read' => 'workspace.projects'])
        ->navigation('reports.read', 'Reports', 'reports', order: 5)->placeholder('workspaces.reports.index', 'reports', 'reports.read', 'Reports', 'Reports on the workspace.')
        ->navigation('projects.read', 'Projects', 'projects', order: 10)->placeholder('workspaces.projects.index', 'projects', 'projects.read', 'Projects', 'The workspace\'s projects.')
        ->permission('reports.view', ['owner'], 'workspace.reports', ['reports.read'])
        ->permission('dashboard.view', ['owner', 'admin'], 'workspace.dashboard', ['dashboard.read']);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
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

    // The admin is in a second workspace too, so their links are in the full form.
    $beta = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    ConciergeModels::membership()::query()->create(['workspace_id' => $beta->id, 'user_id' => $people['admin']->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Concierge::class)->context()->run($beta, fn () => $people['admin']->assignRole('admin'), [$people['admin']]);

    $sync->run(new ArrayInput([]), new NullOutput);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    $request = function (string $who, string $method, string $path, array $cookies = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, cookies: $cookies, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };

    $request = function (string $who, string $path, bool $json = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $request = Request::create('https://kit.test'.$path, 'GET', server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();
        $props = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), $response->isRedirection() ? $response->headers->get('Location') : ($props['component'] ?? null)];
    };
    $capabilities = app(Capabilities::class);
    $dashboard = $request('owner', '/@acme');
    httpCheck($dashboard[0] === 200 && $dashboard[1] === 'Workspace/Dashboard/Page', 'dashboard on: the workspace root is the dashboard ('.json_encode($dashboard).')');

    $capabilities->setForWorkspace('workspace.dashboard', $workspace, false);
    try {
        httpCheck(($seen = $request('owner', '/@acme')) === [302, 'https://kit.test/reports'], 'dashboard off: the root opens the first primary navigation page ('.json_encode($seen).')');
        httpCheck(($seen = $request('admin', '/@acme')) === [302, 'https://kit.test/@acme/projects'], 'dashboard off: a viewer who may not open the first page goes to the first one they may open, in the full form for someone in two workspaces ('.json_encode($seen).')');
        httpCheck(($seen = $request('owner', '/@acme/reports'))[0] === 200, 'the page the root opens answers ('.json_encode($seen).')');
        httpCheck(($seen = $request('member', '/@acme'))[0] === 403, 'dashboard off: a viewer whose role lacks dashboard.read is still refused, not redirected ('.json_encode($seen).')');
        httpCheck(($seen = $request('outsider', '/@acme'))[0] === 404, 'dashboard off: a non-member is still refused ('.json_encode($seen).')');
        httpCheck(($seen = $request('owner', '/@acme', json: true))[0] === 404, 'dashboard off: a JSON client is refused as before, not redirected ('.json_encode($seen).')');
        // The owner has one workspace, so the pages the root opens are in the short form; "/" is that workspace's root too.
        httpCheck(($seen = $request('owner', '/')) === [302, 'https://kit.test/reports'], 'dashboard off: the short form "/" opens the first page, in the short form ('.json_encode($seen).')');

        $capabilities->setForWorkspace('workspace.reports', $workspace, false);
        $capabilities->setForWorkspace('workspace.projects', $workspace, false);
        httpCheck(($seen = $request('owner', '/@acme')) === [302, 'https://kit.test/me/workspaces'], 'dashboard off and no navigation page to open: the root opens the workspace list ('.json_encode($seen).')');
        httpCheck(($seen = $request('owner', '/')) === [302, 'https://kit.test/me/workspaces'], 'the short form "/" with no page to open: the workspace list ('.json_encode($seen).')');
    } finally {
        foreach (['workspace.dashboard', 'workspace.reports', 'workspace.projects'] as $capability) {
            $capabilities->setForWorkspace($capability, $workspace, null);
        }
    }
    httpCheck(($seen = $request('owner', '/@acme')) === [200, 'Workspace/Dashboard/Page'], 'dashboard back on: the root is the dashboard again ('.json_encode($seen).')');
    httpCheck(($seen = $request('owner', '/')) === [200, 'Workspace/Dashboard/Page'], 'dashboard on: the short form "/" is the dashboard ('.json_encode($seen).')');
    httpCheck(($seen = $request('member', '/@acme'))[0] === 403, 'dashboard on: a viewer whose role lacks dashboard.read is refused ('.json_encode($seen).')');
    echo "Workspace root checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack, no browser. Placeholder pages stand in for an app's own.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
