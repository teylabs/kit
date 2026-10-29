<?php

// Every workspace controller that no longer switches tenant itself (its TenantBoundary call was dropped) is reachable only through routes that run
// ResolveWorkspace, which makes the workspace current around the request. And, in general, every route with a {workspace} parameter that points at a
// kit controller runs ResolveWorkspace. Boots a host with every feature that registers workspace routes and reads the router's registered routes and
// their resolved middleware (groups and aliases expanded). A negative case registers a route without the middleware and expects the check to flag it.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
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
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\TestHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Notes\Http\NoteController;
use Tey\Kit\Features\Notes\Http\NoteMediaController;
use Tey\Kit\Features\SessionUi\Http\DashboardController;
use Tey\Kit\Features\SessionUi\Http\HomeController;
use Tey\Kit\Features\SessionUi\Http\MemberController;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\OwnershipController;
use Tey\Kit\Features\SessionUi\Http\PeopleController;
use Tey\Kit\Features\SessionUi\Http\PeopleInvitationController;
use Tey\Kit\Features\SessionUi\Http\PeopleModalController;
use Tey\Kit\Features\SessionUi\Http\PlaceholderController;
use Tey\Kit\Features\SessionUi\Http\SearchController;
use Tey\Kit\Features\SessionUi\Http\TransferOwnershipModalController;
use Tey\Kit\Features\SessionUi\Http\WelcomeCardController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceDeletionController;
use Tey\Kit\Features\SessionUi\Http\WorkspaceSectionsController;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Http\MembershipController;
use Tey\Kit\Features\Workspaces\Http\Middleware\ResolveWorkspace;
use Tey\Kit\Features\Workspaces\Http\MovedWorkspacePageController;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function scopeCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

final class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(HttpUser::class, 'App\Models\User');

/** The controllers whose own tenant switch was dropped: they rely on ResolveWorkspace. */
const UNSWITCHED_CONTROLLERS = [
    ConnectionController::class,
    NoteController::class,
    NoteMediaController::class,
    DashboardController::class,
    MemberController::class,
    OwnershipController::class,
    PeopleController::class,
    PeopleInvitationController::class,
    PeopleModalController::class,
    PlaceholderController::class,
    SearchController::class,
    TransferOwnershipModalController::class,
    WorkspaceDeletionController::class,
    WorkspaceSectionsController::class,
    SettingsController::class,
];

/**
 * Kit controllers that take {workspace} outside a workspace request, on purpose, each with why. Each acts on landlord data only and checks membership
 * itself; test:workspace-refusal proves a non-member is refused with nothing changed.
 */
const OUTSIDE_WORKSPACE = [
    MovedWorkspacePageController::class => 'a permanent redirect from an old /workspaces/{id or slug}/... address; it resolves the new address among the person\'s own workspaces, so a non-member gets a 404 and never learns it',
    MembershipController::class => 'leaving a workspace from /me: it ends the person\'s own membership row through Concierge, which refuses a non-member (a 404)',
    WelcomeCardController::class => 'dismissing the welcome card from /me: a per-person flag, written only after the controller checks the person is a member (else a 404)',
];

function scopeController(Route $route): ?string
{
    $class = $route->getControllerClass();

    return is_string($class) ? ltrim($class, '\\') : null;
}

function scopeResolves(Router $router, Route $route): bool
{
    foreach ($router->gatherRouteMiddleware($route) as $middleware) {
        if (is_string($middleware) && explode(':', $middleware, 2)[0] === ResolveWorkspace::class) {
            return true;
        }
    }

    return false;
}

/** @return list<string> one line per route that breaks the rules */
function scopeProblems(Router $router): array
{
    $routes = $router->getRoutes()->getRoutes();
    $uris = [];
    foreach ($routes as $route) {
        $uris[$route->uri().' '.scopeController($route)] = true;
    }
    $problems = [];
    foreach ($routes as $route) {
        $controller = scopeController($route);
        if ($controller === null) {
            continue;
        }
        $label = implode('|', $route->methods()).' /'.$route->uri().' -> '.$controller;
        $hasWorkspace = in_array('workspace', $route->parameterNames(), true);
        if (in_array($controller, UNSWITCHED_CONTROLLERS, true)) {
            if (! scopeResolves($router, $route)) {
                $problems[] = 'no ResolveWorkspace: '.$label;
            }
            // The short form has no {workspace} (it means the session's workspace): it must be the twin of a full /@{workspace} route.
            if (! $hasWorkspace && ! isset($uris[trim('@{workspace}/'.$route->uri(), '/').' '.$controller])) {
                $problems[] = 'no {workspace} and no /@{workspace} twin: '.$label;
            }
        }
        if ($hasWorkspace && str_starts_with($controller, 'Tey\\Kit\\') && ! array_key_exists($controller, OUTSIDE_WORKSPACE) && ! scopeResolves($router, $route)) {
            $problems[] = '{workspace} without ResolveWorkspace: '.$label;
        }
    }

    return array_values(array_unique($problems));
}

try {
    $base = sys_get_temp_dir().'/kit-route-scope-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp', 'notes'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => TestHandler::class]]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // A placeholder page, so PlaceholderController's routes are registered too.
    $app->make(Registry::class)->placeholder('workspaces.reports.index', 'reports', null, 'Reports', 'Reports on the workspace.');
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

    $router = $app['router'];
    $router->getRoutes()->refreshNameLookups();
    $routes = $router->getRoutes()->getRoutes();

    // The list is exactly the kit controllers that serve workspace routes and no longer switch tenant themselves, so a new one cannot hide.
    $reached = [];
    foreach ($routes as $route) {
        $controller = scopeController($route);
        if ($controller !== null && str_starts_with($controller, 'Tey\\Kit\\') && scopeResolves($router, $route)) {
            $reached[$controller] = true;
        }
    }
    $unswitched = array_values(array_filter(array_keys($reached), fn (string $class): bool => ! str_contains((string) file_get_contents((new ReflectionClass($class))->getFileName()), 'Workspaces::run(')));
    sort($unswitched);
    $listed = UNSWITCHED_CONTROLLERS;
    sort($listed);
    scopeCheck($unswitched === $listed, 'the 15 listed controllers are exactly the kit controllers on ResolveWorkspace routes that do not switch tenant themselves'.($unswitched === $listed ? '' : ': reached '.implode(', ', array_diff($unswitched, $listed)).'; listed but not reached '.implode(', ', array_diff($listed, $unswitched))));
    $checked = array_values(array_filter($routes, fn (Route $route): bool => in_array(scopeController($route), UNSWITCHED_CONTROLLERS, true)));
    $covered = array_unique(array_map('scopeController', $checked));
    scopeCheck(count($covered) === 15, 'every listed controller has a registered route ('.count($checked).' routes to them)');
    $general = array_filter($routes, fn (Route $route): bool => in_array('workspace', $route->parameterNames(), true) && str_starts_with((string) scopeController($route), 'Tey\\Kit\\'));
    $problems = scopeProblems($router);
    scopeCheck($problems === [], 'each route to them runs ResolveWorkspace and carries {workspace} or is the short-form twin of one that does, and every {workspace} route to a kit controller ('.count($general).', bar the listed exceptions) runs ResolveWorkspace'.($problems === [] ? '' : ":\n".implode("\n", $problems)));

    // Negative cases: a route to one of them without the middleware is flagged, with and without {workspace}.
    $router->get('rogue/@{workspace}/people', [PeopleController::class, 'index']);
    $router->get('rogue-short/people', [PeopleController::class, 'index']);
    $router->get('rogue/@{workspace}/sections', [HomeController::class, '__invoke']);
    $router->getRoutes()->refreshNameLookups();
    $flagged = scopeProblems($router);
    scopeCheck(in_array('no ResolveWorkspace: GET|HEAD /rogue/@{workspace}/people -> Tey\Kit\Features\SessionUi\Http\PeopleController', $flagged, true), 'a {workspace} route to a listed controller without ResolveWorkspace is flagged');
    scopeCheck(in_array('no ResolveWorkspace: GET|HEAD /rogue-short/people -> Tey\Kit\Features\SessionUi\Http\PeopleController', $flagged, true) && in_array('no {workspace} and no /@{workspace} twin: GET|HEAD /rogue-short/people -> Tey\Kit\Features\SessionUi\Http\PeopleController', $flagged, true), 'a route to it with neither the middleware nor {workspace} is flagged twice');
    scopeCheck(in_array('{workspace} without ResolveWorkspace: GET|HEAD /rogue/@{workspace}/sections -> Tey\Kit\Features\SessionUi\Http\HomeController', $flagged, true), 'any {workspace} route to a kit controller without ResolveWorkspace is flagged by the general rule');
    scopeCheck(count($flagged) === 5, 'and nothing else is flagged (the first rogue route breaks both rules)');
    $outside = array_unique(array_filter(array_map('scopeController', $general), fn (?string $class): bool => $class !== null && array_key_exists($class, OUTSIDE_WORKSPACE)));
    sort($outside);
    $allowed = array_keys(OUTSIDE_WORKSPACE);
    sort($allowed);
    scopeCheck($outside === $allowed, 'each controller allowed outside a workspace request still has a {workspace} route, so the allowance is not stale');

    echo "OK workspace route scope\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
