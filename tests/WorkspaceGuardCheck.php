<?php

// A routed or remembered workspace id is not authorization: names, slugs and links of a workspace the person is not an active member of never render. SQLite memory, synthetic people.
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

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
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-workspace-guard-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class,
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
    foreach ($stubs as $file) {
        (require $file)->up();
    }

    $app['router']->middlewareGroup('web', [Illuminate\Session\Middleware\StartSession::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();

    $registry = $app->make(Registry::class);
    $make = function (string $name, string $slug) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $join = fn (HttpUser $user, Workspace $workspace, MembershipStatus $status = MembershipStatus::Active) => ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => $status, 'joined_at' => now()]);
    $acme = $make('Acme Secret Name', 'acme');
    $globex = $make('Globex Private Name', 'globex');
    $hidden = $make('Hidden Corp Name', 'hidden');
    $solo = $make('Solo Co', 'solo');
    $mina = HttpUser::create(['name' => 'Mina', 'email' => 'mina@example.test', 'email_verified_at' => now()]);
    $sam = HttpUser::create(['name' => 'Sam', 'email' => 'sam@example.test', 'email_verified_at' => now()]);
    $lee = HttpUser::create(['name' => 'Lee', 'email' => 'lee@example.test', 'email_verified_at' => now()]);
    $join($mina, $acme);
    $join($mina, $globex);
    $join($sam, $solo);
    $join($lee, $acme);
    $join($lee, $hidden, MembershipStatus::Suspended);

    // A request routed to a workspace, as ResolveWorkspace leaves it: the slug already turned into an id.
    $routed = function (HttpUser $user, int $workspaceId) use ($app): Request {
        Auth::guard('web')->setUser($user);
        $request = Request::create('/@x/cases');
        $request->setUserResolver(fn () => $user);
        $route = new Illuminate\Routing\Route('GET', '/@{workspace}/cases', fn () => null);
        $route->bind($request);
        $route->setParameter('workspace', (string) $workspaceId);
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession($session = new Store('s', new ArraySessionHandler(120)));
        $app->instance('request', $request);
        Tey\Kit\Features\Core\RequestMemo::instance()->flush();

        return $request;
    };

    httpCheck(MemberWorkspaces::workspace($acme->id, $mina)?->name === 'Acme Secret Name', 'a member reads their own workspace');
    httpCheck(MemberWorkspaces::workspace($hidden->id, $mina) === null, 'a workspace the person is not a member of reads as null');
    httpCheck(MemberWorkspaces::workspace($hidden->id, $lee) === null, 'a suspended membership reads as null');
    httpCheck(MemberWorkspaces::workspace($acme->id, null) === null, 'a null person reads nothing');

    $request = $routed($mina, $hidden->id);
    httpCheck(WorkspaceUrl::homeName($request) !== 'Hidden Corp Name', 'the home crumb does not name a workspace the person is not in');
    httpCheck(! str_contains((new Tey\Kit\Features\SessionUi\PageTitle)->forRequest($request), 'Hidden Corp Name'), 'the document title does not name a workspace the person is not in');
    httpCheck(WorkspaceUrl::currentMemberId($request) === null, 'the member-checked current workspace is null for a routed non-member workspace');
    $refused = function (callable $build): bool {
        try {
            $build();
        } catch (RuntimeException $error) {
            return $error->getMessage() === 'workspace_not_found';
        }

        return false;
    };
    httpCheck($refused(fn () => WorkspaceUrl::slug($hidden->id)), 'a non-member workspace has no slug to link to');
    httpCheck($refused(fn () => WorkspaceUrl::full($hidden->id, 'cases')), 'a full URL for a non-member id is refused, not built with an empty slug');
    httpCheck($refused(fn () => WorkspaceUrl::shareable($hidden->id, 'cases')), 'a shareable URL for a non-member id is refused');
    httpCheck($refused(fn () => WorkspaceUrl::to($hidden->id, 'cases', null, $request)), 'a session URL for a non-member id is refused');
    httpCheck($refused(fn () => WorkspaceUrl::full($acme->id, 'cases')) === false && WorkspaceUrl::full($globex->id, 'cases') === '/@globex/cases', 'a member id still builds a URL with its slug');

    Auth::guard('web')->forgetUser();
    // A console run has no matched route: a bare request, as a mail job or command sees it.
    $app->instance('request', Request::create('https://kit.test'));
    httpCheck(str_ends_with(WorkspaceUrl::shareable($hidden->id, "cases"), "/@hidden/cases"), 'with no one signed in (mail, console) an int id builds its link');
    httpCheck($refused(fn () => WorkspaceUrl::shareable(999999, 'cases')), 'with no one signed in an unknown id is refused');
    // A long-lived worker reports the console but serves HTTP: a matched route means a request, so it refuses.
    $served = Request::create('/@x/cases');
    $served->setRouteResolver(fn () => new Illuminate\Routing\Route('GET', '/@{workspace}/cases', fn () => null));
    $app->instance('request', $served);
    httpCheck($refused(fn () => WorkspaceUrl::shareable($hidden->id, 'cases')), 'a matched HTTP route refuses even when the process reports the console');
    $app->instance('request', Request::create('https://kit.test'));
    // A guest HTTP request is not the console: it resolves nothing unscoped.
    $console = new ReflectionProperty(Application::class, 'isRunningInConsole');
    $console->setValue($app, false);
    try {
        httpCheck($refused(fn () => WorkspaceUrl::shareable($hidden->id, 'cases')), 'a guest HTTP request cannot build a link for an id');
        httpCheck($refused(fn () => WorkspaceUrl::slug($acme->id)), 'a guest HTTP request cannot read a slug');
    } finally {
        $console->setValue($app, true);
    }

    // MCP: a bearer guard authenticates the request, the web session has no one.
    $bearer = function (?HttpUser $user) use ($app): void {
        Auth::guard('web')->forgetUser();
        $request = Request::create('/mcp/x', 'POST');
        $request->setRouteResolver(fn () => new Illuminate\Routing\Route('POST', '/mcp/x', fn () => null));
        $app->instance('request', $request);
        // Rebinding the request installs the auth manager's resolver, so the bearer guard's user is set after it.
        $request->setUserResolver(fn () => $user);
        Tey\Kit\Features\Core\RequestMemo::instance()->flush();
    };
    $bearer($mina);
    httpCheck(WorkspaceUrl::full($globex->id, 'cases') === '/@globex/cases', 'a bearer-authenticated MCP call builds a URL for a member workspace id');
    httpCheck($refused(fn () => WorkspaceUrl::full($hidden->id, 'cases')), 'a bearer-authenticated MCP call is refused for a non-member workspace id');
    $bearer(null);
    httpCheck($refused(fn () => WorkspaceUrl::full($globex->id, 'cases')), 'a guest MCP call is refused for any workspace id');

    $request = $routed($mina, $globex->id);
    httpCheck(WorkspaceUrl::homeName($request) === 'Globex Private Name', 'a member still sees the routed workspace name in the home crumb');
    httpCheck(str_contains((new Tey\Kit\Features\SessionUi\PageTitle)->forRequest($request), 'Globex Private Name'), 'a member still sees the workspace name in the title');
    httpCheck(WorkspaceUrl::currentMemberId($request) === $globex->id, 'the member-checked current workspace is the routed one for a member');

    $request = $routed($sam, $acme->id);
    httpCheck(WorkspaceUrl::homeName($request) === 'Kit' && ! str_contains((new Tey\Kit\Features\SessionUi\PageTitle)->forRequest($request), 'Acme'), 'a single-workspace person never sees another workspace name');

    echo "Workspace guard checks on SQLite memory with real Concierge; read-path and title/crumb units, no browser, Inertia or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
