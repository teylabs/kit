<?php

// Every capability an ability is gated on is declared by an adopted feature: a host with workspace-ui and mcp but not settings has People for its
// owner (and its owner may manage MCP connections), the registry refuses an undeclared mapping at boot, a capability declared twice is kept once, and a deploy's migrate puts back a declared permission the
// database lost. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Events\NoPendingMigrations;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
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
use Tey\Concierge\Contracts\GrantPolicy;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Http\AuthorizationView;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
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
    $base = sys_get_temp_dir().'/kit-capability-declared-'.bin2hex(random_bytes(4));
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
        // No settings feature, and the capabilities the kit's own config/kit.php switches on: a host that adopted workspace-ui and mcp without settings.
        'kit' => ['app_id' => null, 'lifecycle' => ['workspace_delete' => true, 'account_delete' => true], 'features' => ['core', 'workspaces', 'access', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => (require dirname(__DIR__).'/config/kit.php')['capabilities'], 'mcp' => ['ui' => true], 'deploy' => ['sync_in_tests' => true]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
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

    $sync->run(new ArrayInput([]), new NullOutput);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    $owner = $people['owner'];
    $get = function (string $path) use ($app, $owner): array {
        Auth::guard('web')->setUser($owner);
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $owner);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };

    $workspaceGet = fn (string $suffix): array => $get('/@acme'.($suffix === '' ? '' : '/'.$suffix));
    $capabilities = $registry->workspaceCapabilities();
    httpCheck(in_array('workspace.settings', $capabilities, true), 'without settings: workspace.settings is declared (workspace-ui and mcp gate abilities on it)');
    httpCheck(count(array_keys($capabilities, 'workspace.settings', true)) === 1, 'without settings: workspace.settings is declared once though two features declare it');
    httpCheck($workspace->execute(fn (): bool => app(Capabilities::class)->active('workspace.settings', $workspace, $owner)), 'without settings: workspace.settings is defined and on by default (config/kit.php)');
    [$status] = $workspaceGet('settings/people');
    httpCheck($status === 200, 'without settings: the owner opens People ('.$status.')');
    [$status, $body] = $workspaceGet('settings');
    httpCheck($status === 200 && str_contains($body, 'People'), 'without settings: the settings index lists People');
    // Managing another member's MCP connection: owners and admins, gated on workspace.settings, which mcp now declares.
    $manages = fn (HttpUser $user): bool => $workspace->execute(fn (): bool => AuthorizationView::allows((int) $user->getKey(), (int) $workspace->getKey(), WorkspaceConnection::manageAbility()));
    httpCheck($manages($owner), 'without settings: the owner may manage the workspace\'s MCP connections');
    httpCheck(! $manages($people['member']), 'without settings: a member may not manage others\' MCP connections');

    // Invites offer member roles only: a scoped Concierge role (guest) is never offered by People or its Invite modal, and a posted one is refused.
    $config->set('concierge.roles.guest', ['label' => 'Guest', 'grantable_by' => ['owner', 'admin'], 'scoped' => true]);
    // A host's own GrantPolicy that also lists scoped roles (Concierge's configured policy leaves them out): the kit filters what it offers itself.
    $conciergePolicy = $app->make(GrantPolicy::class);
    $app->instance(GrantPolicy::class, new class($conciergePolicy) implements GrantPolicy
    {
        public function __construct(private GrantPolicy $inner) {}

        public function authorizeManagement(Member $actor, Tey\Concierge\Contracts\Workspace $workspace, ?Membership $target = null): Response
        {
            return $this->inner->authorizeManagement($actor, $workspace, $target);
        }

        // Permissive on grants, so only the kit's own check stands between a posted scoped role and the workspace.
        public function authorizeGrant(Member $actor, Tey\Concierge\Contracts\Workspace $workspace, GrantSet $grants, ?Membership $target = null): Response
        {
            return Response::allow();
        }

        public function grantableRoles(Member $actor, Tey\Concierge\Contracts\Workspace $workspace): array
        {
            return [...$this->inner->grantableRoles($actor, $workspace), 'guest'];
        }
    });
    $grantable = $workspace->execute(fn (): array => app(GrantPolicy::class)->grantableRoles($owner, $workspace));
    httpCheck(in_array('guest', $grantable, true), 'fixture: Concierge would let the owner grant the scoped guest role');
    foreach (['settings/people', 'settings/people/invitations'] as $page) {
        [$status, $body] = $workspaceGet($page);
        httpCheck($status === 200 && str_contains($body, '"value":"member"') && ! str_contains($body, '"value":"guest"'), $page.' offers member roles and never the scoped guest role');
    }
    $refusedRole = function (string $role) use ($workspace, $owner): ?string {
        try {
            $workspace->execute(fn () => ManagePeople::make()->invite($owner, 'someone@example.test', $role));
        } catch (AuthorizationException $error) {
            return (string) $error->getMessage();
        } catch (Throwable) {
            return null;
        }

        return null;
    };
    httpCheck(str_contains((string) $refusedRole('guest'), 'member role'), 'an invite posted with the scoped guest role is refused server-side');
    $refusedChange = null;
    try {
        $workspace->execute(fn () => ManagePeople::make()->changeRole($owner, (int) ConciergeModels::membership()::query()->where('user_id', $people['member']->id)->value('id'), 'guest'));
    } catch (AuthorizationException $error) {
        $refusedChange = $error->getMessage();
    } catch (Throwable) {
    }
    httpCheck(str_contains((string) $refusedChange, 'member role'), 'a role change to the scoped guest role is refused server-side');
    $app->instance(GrantPolicy::class, $conciergePolicy);
    $config->set('concierge.roles', array_diff_key($config->get('concierge.roles'), ['guest' => true]));

    // Declaring a capability twice is harmless: every adoption with settings, workspace-ui and mcp together keeps workspace.settings once.
    $both = FeaturesServiceProvider::registry(['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp']);
    httpCheck(count(array_keys($both->workspaceCapabilities(), 'workspace.settings', true)) === 1, 'with settings, workspace-ui and mcp: workspace.settings is declared once');
    httpCheck((new Registry)->capabilities(['workspace.a'])->capabilities(['workspace.a', 'workspace.b'])->workspaceCapabilities() === ['workspace.a', 'workspace.b'], 'a capability declared twice is kept once, in first-declared order');

    // The boot guard: every adoption the kit's features allow passes it, and an ability on an undeclared capability is refused, naming all three.
    foreach ([['core', 'workspaces', 'access', 'session-ui', 'workspace-ui'], ['core', 'workspaces', 'access', 'mcp'], ['core', 'workspaces', 'access', 'notes'], ['core', 'workspaces', 'access', 'settings']] as $set) {
        FeaturesServiceProvider::registry($set)->assertCapabilitiesDeclared();
        httpCheck(true, 'boot guard passes for '.implode(', ', $set));
    }
    $refused = null;
    try {
        (new Registry)->capabilities(['workspace.dashboard'])->abilities(['reports.read' => 'workspace.reports'])->assertCapabilitiesDeclared(['reports.read' => 'reports']);
    } catch (RuntimeException $error) {
        $refused = $error->getMessage();
    }
    httpCheck($refused !== null && str_contains($refused, 'reports.read') && str_contains($refused, 'workspace.reports') && str_contains($refused, 'reports feature'), 'boot guard refuses an ability on an undeclared capability, naming the ability, capability and feature');
    $refused = null;
    try {
        (new Registry)->permission('workspace.reports.view', ['owner'], 'workspace.reports')->assertCapabilitiesDeclared();
    } catch (RuntimeException $error) {
        $refused = $error->getMessage();
    }
    httpCheck($refused !== null && str_contains($refused, 'workspace.reports.view') && str_contains($refused, 'workspace.reports'), 'boot guard refuses a permission on an undeclared capability');

    // Permissions reach the database on a deploy's migrate (CatalogSync on NoPendingMigrations), and come back when a later migrate finds one missing.
    $permissionModel = app(PermissionRegistrar::class)->getPermissionClass();
    $permissionModel::query()->delete();
    DB::table('kit_permission_defaults')->delete();
    DB::table('kit_meta')->where('key', CatalogSync::KEY)->delete();
    httpCheck(in_array('workspace.members.manage', CatalogSync::unsynced(), true) && in_array('workspace.connections.manage', CatalogSync::unsynced(), true), 'unsynced lists the declared permissions the database lacks');
    // The console kernel would bootstrap the app again and reload its config: marked bootstrapped, it only collects the providers' commands.
    $app->bootstrapWith([]);
    $migrate = fn () => $app['events']->dispatch(new NoPendingMigrations('up'));
    $migrate();
    httpCheck(CatalogSync::unsynced() === [], 'a migrate syncs every declared permission');
    httpCheck($owner->fresh() !== null && $workspace->execute(fn (): bool => Gate::forUser($owner->fresh())->allows('manageMembers', $workspace)), 'after the migrate, the owner manages members');
    $permissionModel::query()->where('name', 'workspace.members.manage')->delete();
    httpCheck(CatalogSync::unsynced() === ['workspace.members.manage'], 'a lost permission is reported unsynced');
    $migrate();
    httpCheck(CatalogSync::unsynced() === [], 'the next migrate puts a lost permission back, though the catalog fingerprint is unchanged');

    echo "Covered: the registry and Pennant definition for a host without settings, People and the settings index through router dispatch (Inertia JSON, no global middleware), the connection manage ability through AuthorizationView, the boot guard on Registry, and CatalogSync on NoPendingMigrations with the real kit:permissions:sync and concierge:sync-roles. Not covered: the AI assistants page itself, which authorizes kit.mcp.connection_ability (settings.read by default, a settings ability); a real deploy, a Laravel Cloud build, or kit:status's rendering (test:status).\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
