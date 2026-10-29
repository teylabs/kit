<?php

// A workspace request runs with its workspace as the current tenant: ResolveWorkspace makes it current around the rest of the request and restores
// what was current before, even on failure. Inertia props, terminating callbacks and queued jobs see it; a non-member is refused before any switch.
// Also: the deprecated TenantBoundary delegate to Workspaces::run() (the one check that still calls it), the guard that kit code no longer uses it, and `kit:capability` on spatie's TenantAware trait.
// SQLite memory, a database queue on the same connection, synthetic data. Requests go through the router; terminating callbacks run through
// `Application::terminate()`, as the HTTP kernel calls it after the response is sent.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Log\Context\ContextServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Queue\WorkerOptions;
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
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\Events\MakingTenantCurrentEvent;
use Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\CapabilityCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Http\Middleware\ResolveWorkspace;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function tenantCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The current tenant and Concierge's current workspace, by id. */
function tenantNow(): array
{
    return [Workspaces::current()?->getKey(), app(Concierge::class)->context()->current()?->getKey()];
}

class TenantUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(TenantUser::class, 'App\Models\User');

final class QueuedProbe implements ShouldQueue, TenantAware
{
    use InteractsWithQueue, Queueable;

    public static array $seen = [];

    public function __construct(public string $label) {}

    public function handle(): void
    {
        self::$seen[$this->label] = tenantNow();
    }
}

/** Simulates a Laravel upgrade that renamed the property ResolveWorkspace reaches into. */
final class RenamedCallbacksResolveWorkspace extends ResolveWorkspace
{
    protected function callbacksProperty(): string
    {
        return 'renamedTerminatingCallbacks';
    }
}

final class ProbeInertia extends Middleware {}

/** Stand-in workspace pages: each records what it saw. */
final class TenantProbeController
{
    public static array $seen = [];

    public static bool $ran = false;

    public function show(Request $request, int $workspace): mixed
    {
        self::$ran = true;
        self::$seen['show'] = tenantNow();

        return response()->json(['workspace' => $workspace]);
    }

    public function boom(Request $request, int $workspace): mixed
    {
        self::$seen['boom'] = tenantNow();
        throw new RuntimeException('probe_boom');
    }

    public function page(Request $request, int $workspace): mixed
    {
        // Returned unrendered: the router turns it into a response, and the props resolve then.
        return Inertia::render('Probe', [
            'eager' => function () {
                return tenantNow();
            },
            'optional' => Inertia::optional(function () {
                return tenantNow();
            }),
            'deferred' => Inertia::defer(function () {
                return tenantNow();
            }),
        ]);
    }

    public function later(Request $request, int $workspace): mixed
    {
        dispatch(new QueuedProbe('in-request'));
        app()->terminating(function (): void {
            self::$seen['terminating'] = tenantNow();
            dispatch(new QueuedProbe('terminating'));
        });

        return response()->json(['queued' => true]);
    }

    public function explode(Request $request, int $workspace): mixed
    {
        app()->terminating(function (): void {
            self::$seen['exploding'] = tenantNow();
            throw new RuntimeException('probe_terminating');
        });

        return response()->json(['ok' => true]);
    }
}

try {
    $base = sys_get_temp_dir().'/kit-workspace-tenant-'.bin2hex(random_bytes(4));
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
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => TenantUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings'], 'models' => ['user' => TenantUser::class], 'capabilities' => ['workspace.settings' => true]],
        'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90], 'sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']],
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
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class, ContextServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $routes = $base.'/probe-routes.php';
    file_put_contents($routes, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
        WorkspaceRoutes::define(function (bool $full): void {
            Route::get('probe', [TenantProbeController::class, 'show']);
            Route::get('boom', [TenantProbeController::class, 'boom']);
            Route::get('page', [TenantProbeController::class, 'page']);
            Route::get('later', [TenantProbeController::class, 'later']);
            Route::get('explode', [TenantProbeController::class, 'explode']);
        });
        PHP);
    $app->make(Registry::class)->routes($routes);
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, ProbeInertia::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
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
    $app['router']->getRoutes()->refreshNameLookups();
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

        return $workspace->refresh();
    };
    [$one, $two] = [$make('Northwind Farms', 'northwind'), $make('Acme Travel', 'acme')];
    $people = [];
    foreach (['member' => [$one, $two], 'outsider' => [$two]] as $name => $in) {
        $people[$name] = TenantUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        foreach ($in as $workspace) {
            ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }
    }
    $switches = 0;
    $app['events']->listen(MakingTenantCurrentEvent::class, function () use (&$switches): void {
        $switches++;
    });
    $request = function (string $who, string $path, array $headers = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, 'GET', server: $headers + ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    $terminate = function () use ($app): void {
        $app->terminate();
        // A fresh list for the next request, as each request in a real app starts with one.
        (new ReflectionProperty($app, 'terminatingCallbacks'))->setValue($app, []);
    };

    // (a) The workspace is current for the request, and what was current before comes back.
    [$status] = $request('member', '/@northwind/probe');
    tenantCheck($status === 200 && TenantProbeController::$seen['show'] === [$one->id, $one->id], 'a /@slug request runs its controller with the workspace current and Concierge in it');
    tenantCheck(tenantNow() === [null, null], 'with no tenant before the request, none is current after it');
    $terminate();
    $two->makeCurrent();
    $request('member', '/@northwind/probe');
    tenantCheck(TenantProbeController::$seen['show'] === [$one->id, $one->id] && tenantNow() === [$two->id, $two->id], 'the tenant current before the request is restored after it returns');
    $terminate();
    [$status] = $request('member', '/@northwind/boom');
    tenantCheck($status === 500 && TenantProbeController::$seen['boom'] === [$one->id, $one->id] && tenantNow() === [$two->id, $two->id], 'the previous tenant is restored when the controller throws');
    $terminate();
    Workspace::forgetCurrent();
    [$status] = $request('member', '/@northwind/boom');
    tenantCheck($status === 500 && tenantNow() === [null, null], 'and with none before, none is current after a throw');
    $terminate();

    // (b) Inertia props resolve while the response is built, inside the middleware.
    $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
    [$status, $page] = $request('member', '/@northwind/page', $inertia);
    tenantCheck($status === 200 && $page['props']['eager'] === [$one->id, $one->id] && ! isset($page['props']['optional']) && ! isset($page['props']['deferred']), 'a closure prop resolves inside the workspace; optional and deferred props wait for their partial request');
    $terminate();
    [$status, $page] = $request('member', '/@northwind/page', $inertia + ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Probe', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'optional,deferred']);
    tenantCheck($status === 200 && $page['props']['optional'] === [$one->id, $one->id] && $page['props']['deferred'] === [$one->id, $one->id] && tenantNow() === [null, null], 'optional and deferred props resolve inside the workspace in their partial request');
    $terminate();

    // An upgrade must break this check, never silently run terminating callbacks outside the workspace.
    $property = new ReflectionProperty(Application::class, 'terminatingCallbacks');
    tenantCheck($property->isProtected() && is_array($property->getValue($app)), 'Laravel still has the protected terminatingCallbacks property ResolveWorkspace relies on');
    // Without it, the request is still served, the callbacks are left to Laravel and a warning says so, once.
    $warnings = [];
    $app['events']->listen(MessageLogged::class, function ($event) use (&$warnings): void {
        if ($event->level === 'warning') {
            $warnings[] = $event->message;
        }
    });
    $app->bind(ResolveWorkspace::class, RenamedCallbacksResolveWorkspace::class);
    TenantProbeController::$seen['terminating'] = 'unset';
    [$status] = $request('member', '/@northwind/later');
    tenantCheck($status === 200 && TenantProbeController::$seen['show'] !== null && tenantNow() === [null, null], 'with the property missing, a workspace request is still served without an exception');
    $terminate();
    [$status] = $request('member', '/@northwind/probe');
    $terminate();
    tenantCheck($status === 200 && TenantProbeController::$seen['show'] === [$one->id, $one->id] && TenantProbeController::$seen['terminating'] === [null, null], 'the controller still runs in the workspace; terminating callbacks fall back to running outside it');
    tenantCheck(count($warnings) === 1 && str_contains($warnings[0], 'Application::$renamedTerminatingCallbacks') && str_contains($warnings[0], 'run outside the workspace'), 'the fallback logs one warning per process naming the property and the consequence');
    $app->offsetUnset(ResolveWorkspace::class);
    DB::table('jobs')->delete();
    QueuedProbe::$seen = [];

    // (b) A terminating callback and queued jobs.
    QueuedProbe::$seen = [];
    $request('member', '/@northwind/later');
    tenantCheck(tenantNow() === [null, null] && QueuedProbe::$seen === [], 'after the response, no tenant is current and the queued job has not run');
    $terminate();
    tenantCheck((TenantProbeController::$seen['terminating'] ?? null) === [$one->id, $one->id] && tenantNow() === [null, null], 'a terminating callback the request registered runs with the workspace current, and the tenant is restored after it');
    $explode = function () use ($request, $terminate): ?string {
        $request('member', '/@northwind/explode');
        try {
            $terminate();
        } catch (RuntimeException $error) {
            (new ReflectionProperty(app(), 'terminatingCallbacks'))->setValue(app(), []);

            return $error->getMessage();
        }

        return null;
    };
    tenantCheck($explode() === 'probe_terminating' && TenantProbeController::$seen['exploding'] === [$one->id, $one->id] && tenantNow() === [null, null], 'a terminating callback that throws ran in the workspace, and with none before, none is current after');
    $two->makeCurrent();
    TenantProbeController::$seen['exploding'] = null;
    tenantCheck($explode() === 'probe_terminating' && TenantProbeController::$seen['exploding'] === [$one->id, $one->id] && tenantNow() === [$two->id, $two->id], 'and the tenant current before is restored after it throws');
    Workspace::forgetCurrent();
    $worker = $app->make('queue.worker');
    $options = new WorkerOptions(sleep: 0, maxTries: 1);
    $worker->runNextJob('database', 'default', $options);
    $worker->runNextJob('database', 'default', $options);
    tenantCheck(QueuedProbe::$seen === ['in-request' => [$one->id, $one->id], 'terminating' => [$one->id, $one->id]] && DB::table('jobs')->count() === 0, 'jobs queued in the request and in its terminating callback run in the workspace');
    tenantCheck(tenantNow() === [null, null], 'after the jobs, no tenant is current and Concierge is cleared');

    // A TenantAware job queued with no tenant current fails loudly and never runs.
    QueuedProbe::$seen = [];
    dispatch(new QueuedProbe('orphan'));
    $failed = null;
    $app['events']->listen(JobExceptionOccurred::class, function ($event) use (&$failed): void {
        $failed = $event->exception;
    });
    try {
        $worker->runNextJob('database', 'default', $options);
    } catch (Throwable $error) {
        $failed ??= $error;
    }
    tenantCheck($failed instanceof CurrentTenantCouldNotBeDeterminedInTenantAwareJob && QueuedProbe::$seen === [] && DB::table('jobs')->count() === 0, 'a TenantAware job queued with no tenant fails with CurrentTenantCouldNotBeDeterminedInTenantAwareJob, never runs, and is deleted');

    // (c) A non-member is refused before the controller runs or any tenant switch.
    TenantProbeController::$ran = false;
    $before = $switches;
    [$status] = $request('outsider', '/@northwind/probe');
    tenantCheck($status === 404 && ! TenantProbeController::$ran && $switches === $before && tenantNow() === [null, null], 'a /@slug request from someone who is not a member is a 404 before the controller runs or the tenant switches');
    $terminate();
    ConciergeModels::membership()::query()->where('user_id', $people['outsider']->id)->update(['status' => MembershipStatus::Suspended]);
    $before = $switches;
    [$status] = $request('outsider', '/@acme/probe');
    tenantCheck($status === 404 && ! TenantProbeController::$ran && $switches === $before, 'a suspended member is refused the same way');
    $terminate();

    // The deprecated delegate still works: it is Workspaces::run() under the old name.
    Feature::define('beta', fn () => true);
    Feature::for('probe')->active('beta');
    $store = Feature::store();
    $cached = fn (): int => count((new ReflectionProperty($store, 'cache'))->getValue($store));
    $seen = app(TenantBoundary::class)->run($one->id, fn () => [...tenantNow(), app(PermissionRegistrar::class)->getPermissionsTeamId(), $cached()]);
    tenantCheck($seen === [$one->id, $one->id, $one->id, 0] && tenantNow() === [null, null] && app(PermissionRegistrar::class)->getPermissionsTeamId() === null, 'TenantBoundary::run still makes the workspace current with its context and team, flushes Pennant, and restores after');
    try {
        app(TenantBoundary::class)->run(999999, fn () => null);
        $missing = false;
    } catch (ModelNotFoundException) {
        $missing = true;
    }
    tenantCheck($missing && tenantNow() === [null, null], 'TenantBoundary::run on a missing workspace throws ModelNotFoundException');

    // Kit code no longer uses it.
    $users = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php' && $file->getFilename() !== 'TenantBoundary.php' && str_contains((string) file_get_contents($file->getPathname()), 'TenantBoundary')) {
            $users[] = $file->getPathname();
        }
    }
    tenantCheck($users === [], 'nothing under src/ but TenantBoundary.php names TenantBoundary'.($users === [] ? '' : ': '.implode(', ', $users)));

    // kit:capability on spatie's TenantAware trait.
    DB::table('operators')->insert(['actor_id' => $people['member']->id]);
    $capability = function (array $input, ?string $answer = null, bool $interactive = true): array {
        $command = new CapabilityCommand;
        $command->setLaravel(app());
        $output = new BufferedOutput;
        $arguments = new ArrayInput($input);
        $arguments->setInteractive($interactive);
        if ($answer !== null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $answer."\n");
            rewind($stream);
            $arguments->setStream($stream);
        }
        $code = $command->run($arguments, $output);

        return [$code, $output->fetch()];
    };
    $state = fn (Workspace $at): ?bool => ($row = DB::table('kit_capability_overrides')->where('workspace_id', $at->id)->where('capability', 'workspace.settings')->where('user_id', 0)->first()) === null ? null : (bool) $row->enabled;
    $change = ['--operator' => (string) $people['member']->id, '--reason' => 'synthetic check'];

    [$code, $out] = $capability(['workspace' => 'northwind', 'capability' => 'workspace.settings', 'state' => 'off'] + $change);
    tenantCheck($code === 0 && str_contains($out, 'The workspace argument is deprecated; use --tenant='.$one->id.'. It is removed in 0.7.') && $state($one) === false && $state($two) === null && tenantNow() === [null, null], 'kit:capability <slug> still works for that workspace only, with a deprecation line');
    [$code, $out] = $capability(['workspace' => 'nowhere']);
    tenantCheck($code === 1 && str_contains($out, 'There is no workspace with that slug.'), 'an unknown slug fails');
    [$code, $out] = $capability(['--tenant' => [(string) $two->id], 'workspace' => 'workspace.settings', 'capability' => 'off'] + $change);
    tenantCheck($code === 0 && ! str_contains($out, 'deprecated') && $state($two) === false && $state($one) === false, 'kit:capability --tenant=<id> <capability> <state> changes that workspace');
    [$code, $out] = $capability(['--tenant' => [(string) $one->id, (string) $two->id], 'workspace' => 'workspace.settings', 'capability' => 'clear'] + $change);
    tenantCheck($code === 0 && $state($one) === null && $state($two) === null && str_contains($out, 'Done in northwind.') && str_contains($out, 'Done in acme.'), 'several --tenant ids change each of them');
    [$code, $out] = $capability(['--tenant' => [(string) $one->id]]);
    tenantCheck($code === 0 && str_contains($out, 'on  workspace.settings') && ! str_contains($out, 'Workspace '), 'kit:capability --tenant=<id> lists that workspace');
    [$code, $out] = $capability([]);
    tenantCheck($code === 0 && str_contains($out, 'Workspace northwind ('.$one->id.'):') && str_contains($out, 'Workspace acme ('.$two->id.'):'), "the overview with neither runs in every workspace (the trait's default), each listing headed by its workspace");
    $overrides = fn (): int => DB::table('kit_capability_overrides')->count();
    [$code, $out] = $capability(['workspace' => 'workspace.settings', 'capability' => 'off'] + $change);
    tenantCheck($code === 1 && str_contains($out, 'Changing a capability needs --tenant=<id>, or --all-tenants') && $overrides() === 0, 'a change with neither --tenant nor a slug is refused and changes nothing');
    [$code, $out] = $capability(['--all-tenants' => true, 'workspace' => 'workspace.settings', 'capability' => 'off'] + $change, 'no');
    tenantCheck($code === 1 && str_contains($out, 'in all 2 workspaces') && str_contains($out, 'Cancelled.') && $overrides() === 0, '--all-tenants states the count and, declined, changes nothing');
    [$code, $out] = $capability(['--all-tenants' => true, 'workspace' => 'workspace.settings', 'capability' => 'off'] + $change, interactive: false);
    tenantCheck($code === 1 && str_contains($out, 'pass --force') && $overrides() === 0, '--all-tenants in a non-interactive run without --force is refused');
    [$code, $out] = $capability(['--all-tenants' => true, 'workspace' => 'workspace.settings', 'capability' => 'off'] + $change, 'yes');
    tenantCheck($code === 0 && $state($one) === false && $state($two) === false, '--all-tenants, confirmed, changes every workspace');
    [$code, $out] = $capability(['--all-tenants' => true, '--force' => true, 'workspace' => 'workspace.settings', 'capability' => 'clear'] + $change, interactive: false);
    tenantCheck($code === 0 && $state($one) === null && $state($two) === null, '--all-tenants --force changes every workspace without asking');
    [$code] = $capability(['--all-tenants' => true, '--tenant' => [(string) $one->id]]);
    tenantCheck($code === 1, '--tenant and --all-tenants together are refused');
    [$code, $out] = $capability(['--tenant' => [(string) $one->id], 'workspace' => 'workspace.settings', 'capability' => 'off', 'state' => 'on']);
    tenantCheck($code === 1 && str_contains($out, 'Too many arguments'), 'with --tenant, a third positional argument fails');
    [$code, $out] = $capability(['--tenant' => ['999999']]);
    tenantCheck($code === 1 && str_contains($out, 'No tenant(s) found.'), 'an unknown --tenant id fails');
    tenantCheck(tenantNow() === [null, null], 'no tenant is current after the commands');

    echo "OK workspace tenant\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
