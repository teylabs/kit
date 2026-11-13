<?php

// Workspace pages refuse a non-member with a 404 before anything runs in the workspace, and an active member who lacks the permission with a 403: People,
// its two modals and the general settings save. SQLite memory, real Concierge, spatie teams and Pennant, router dispatch, synthetic users.
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
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
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\ChangeMemberRoles;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
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

// A stand-in write that a plan caps: create a project, at most the plan's `projects` limit of them.
class ProjectsStubController
{
    public function store(Request $request, int $workspace): mixed
    {
        // A workspace route: ResolveWorkspace has already made the workspace current.
        $tenant = app(Access::class)->authorize(new Principal((int) $request->user()->id), 'projects.write');
        app(Entitlements::class)->enforce('workspace.projects', 'projects', $tenant, DB::table('stub_projects')->where('workspace_id', $tenant->id)->count());
        DB::table('stub_projects')->insert(['workspace_id' => $tenant->id]);

        return response()->json(['created' => true], 201);
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

// An app's tool on the attribute, for the capability out of the plan.
#[Capability('workspace.reports')]
class ReportsTool extends CapabilityTool
{
    public const ABILITY = 'reports.read';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return [];
    }
}

try {
    $base = sys_get_temp_dir().'/kit-entitlements-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.reports' => true, 'workspace.projects' => true], 'entitlements' => ['workspace.projects' => ['included' => true, 'limits' => ['projects' => 2]]], 'mcp' => ['ui' => true]],
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
        ->navigation('projects.read', 'Projects', 'projects')->placeholder('workspaces.projects.index', 'projects', 'projects.read', 'Projects', 'The workspace\'s projects.')
        ->capabilityTools([ReportsTool::class]);
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
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
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
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
    Schema::create('stub_projects', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
    });
    $entitlements = app(Entitlements::class);
    $request = function (string $who, string $method, string $path, bool $json = false) use ($app, &$people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, server: $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
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
    $membership = (int) ConciergeModels::membership()::query()->where('user_id', $people['admin']->id)->value('id');
    $pages = [
        'People' => ['GET', '/@acme/settings/people'],
        'the invite modal' => ['GET', '/@acme/settings/people/invitations'],
        'the change-role modal' => ['GET', '/@acme/settings/people/members/'.$membership],
        'removing a member' => ['DELETE', '/@acme/settings/people/members/'.$membership],
    ];
    foreach ($pages as $label => [$method, $path]) {
        [$owner] = $request('owner', $method, $path, $method !== 'GET');
        [$member] = $request('member', $method, $path, $method !== 'GET');
        [$outsider] = $request('outsider', $method, $path, $method !== 'GET');
        httpCheck($owner === 200 && $member === 403 && $outsider === 404, $label.": the owner gets through ($owner), a member without the permission gets 403, a non-member 404");
    }
    // The two controllers that take {workspace} outside a workspace request check membership themselves: a signed-in person who is not a member
    // (`outsider`, in no workspace, and `elsewhere`, a member of another one) is refused and changes nothing.
    $beta = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    $people['elsewhere'] = HttpUser::create(['name' => 'Elsewhere', 'email' => 'elsewhere@example.test', 'email_verified_at' => now()]);
    ConciergeModels::membership()::query()->create(['workspace_id' => $beta->id, 'user_id' => $people['elsewhere']->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    $memberships = fn (): array => ConciergeModels::membership()::query()->orderBy('id')->get(['id', 'workspace_id', 'user_id', 'status'])->map(fn ($row) => [$row->id, $row->workspace_id, $row->user_id, (string) ($row->status->value ?? $row->status)])->all();
    $states = fn (): int => DB::table('user_state')->where('key', 'welcome.dismissed')->count();
    foreach (['outsider', 'elsewhere'] as $who) {
        foreach (['/workspaces/'.$workspace->id.'/settings', '/workspaces/acme/settings'] as $old) {
            [$status, $body] = $request($who, 'GET', $old);
            httpCheck($status === 404 && (json_decode($body, true)['component'] ?? null) === 'Workspace/Error/Page', "an old /workspaces/... address ($old) is a 404 for $who, with no redirect to the workspace's address");
        }
        $before = $memberships();
        [$status] = $request($who, 'DELETE', '/me/workspaces/acme/membership', true);
        httpCheck(in_array($status, [403, 404], true) && $memberships() === $before, "leaving a workspace $who is not a member of is refused ($status), and no membership row changes");
    }
    // A member still gets the redirect, so the refusals above are about membership.
    [$status] = $request('member', 'GET', '/workspaces/'.$workspace->id.'/settings', true);
    httpCheck($status === 301, 'a member following an old address is redirected');
    // The welcome card's dismiss was removed in 0.17: the address is no route for anyone, and nothing is written.
    [$status] = $request('member', 'DELETE', '/me/welcome/'.$workspace->id, true);
    httpCheck(in_array($status, [404, 405], true) && $states() === 0, "the removed welcome card's dismiss is no route ($status), even for a member, and writes nothing");

    // A 403 page words Concierge's refusal only for an allowlisted code (not_active): changing the role of a suspended member shows Concierge's own message,
    // with the recovery link. Every other 403 keeps the generic copy, and no exception detail reaches the page.
    // The change-role save validates its input; Foundation's provider (not booted here) is what adds Request::validate.
    Request::macro('validate', fn (array $rules, ...$params) => validator()->validate($this->all(), $rules, ...$params));
    $generic = 'This request is unavailable. Your access or the resource may have changed.';
    $props = fn (string $body): array => (array) (json_decode($body, true)['props'] ?? []);
    $roles = fn (): array => DB::table('model_has_roles')->orderBy('model_id')->orderBy('role_id')->get()->map(fn ($row) => (array) $row)->all();
    // Concierge refuses a suspended member's role change with `not_active` from 0.11; an older Concierge in the lowest-dependency run has no such refusal.
    if (str_contains((string) file_get_contents((new ReflectionClass(ChangeMemberRoles::class))->getFileName()), "'not_active'")) {
        ConciergeModels::membership()::query()->whereKey($membership)->update(['status' => MembershipStatus::Suspended, 'suspended_at' => now()]);
        $before = $roles();
        [$status, $body] = $request('owner', 'PUT', '/@acme/settings/people/members/'.$membership.'?role=member');
        $page = $props($body);
        httpCheck($status === 403 && (json_decode($body, true)['component'] ?? null) === 'Workspace/Error/Page' && ($page['message'] ?? null) === 'Reinstate this member before changing their role.'
            && ($page['recovery_url'] ?? null) === '/me/workspaces' && ($page['recovery_label'] ?? null) === 'Return to your workspaces' && $roles() === $before,
            "changing a suspended member's role is a 403 error page with Concierge's not_active message and the recovery link, and no role changes");
        ConciergeModels::membership()::query()->whereKey($membership)->update(['status' => MembershipStatus::Active, 'suspended_at' => null]);
    } else {
        // An older Concierge (the lowest-dependency run) has no not_active refusal: its role change must not be refused with a worded 403 page either.
        [$status, $body] = $request('owner', 'PUT', '/@acme/settings/people/members/'.$membership.'?role=member');
        httpCheck(! ($status === 403 && ($props($body)['message'] ?? $generic) !== $generic), "a Concierge without not_active never words its 403 ($status)");
    }
    // On every Concierge: a refusal shaped like Concierge's (an AuthorizationException with its code) is worded only when the code is allowlisted.
    $app['router']->middleware('web')->get('/denied/{code}', fn (string $code) => Response::deny('Words for code '.$code.'.', $code)->authorize());
    [$status, $body] = $request('member', 'GET', '/denied/not_active');
    httpCheck($status === 403 && ($props($body)['message'] ?? null) === 'Words for code not_active.' && ($props($body)['recovery_url'] ?? null) === '/me/workspaces', 'a not_active refusal is worded on the 403 page with the recovery link');
    [$status, $body] = $request('member', 'GET', '/denied/not_member');
    httpCheck($status === 403 && ($props($body)['message'] ?? null) === $generic && ! str_contains($body, 'Words for code'), 'a refusal with any other code keeps the generic message and never shows its own');
    // Another Concierge refusal with its own message (the owner managing themselves): the generic copy, never Concierge's words or the exception's.
    $ownMembership = (int) ConciergeModels::membership()::query()->where('user_id', $people['owner']->id)->value('id');
    [$status, $body] = $request('owner', 'PUT', '/@acme/settings/people/members/'.$ownMembership.'?role=member');
    $page = $props($body);
    httpCheck($status === 403 && ($page['message'] ?? null) === $generic && ($page['recovery_url'] ?? null) === '/me/workspaces' && ! str_contains($body, 'Exception') && ! str_contains($body, 'unauthorized'),
        'any other Concierge 403 shows the generic message and the recovery link, with no exception detail');
    // A permission 403 (a member opening People): the generic copy too.
    [$status, $body] = $request('member', 'GET', '/@acme/settings/people');
    $page = $props($body);
    httpCheck($status === 403 && ($page['message'] ?? null) === $generic && ! str_contains($body, 'Exception') && ! str_contains($body, 'unauthorized'), 'a permission 403 shows the generic message, with no exception detail');

    echo "Refusal checks on SQLite memory: JSON through router dispatch without the global middleware stack, no browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
