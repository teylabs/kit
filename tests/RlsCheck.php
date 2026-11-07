<?php

// PostgreSQL row-level security (kit.tenancy.rls): the second tenant lock under app scoping. PostgreSQL only: it exits 1 on any other DB_CONNECTION, as
// SQLite has no RLS. It creates its own schema (kit_rls) and two roles (kit_rls_check_runtime, kit_rls_check_bypass) in the database the DB_* variables
// name, as their superuser (the admin/migrator connection), and drops them at the end; point it only at a throwaway database.
// Covered: the runtime role command, the policy helper and guard, refusals with app scoping removed (select, update, delete, insert, move across workspaces),
// a missing setting, SET LOCAL leaving nothing behind, a workspace request through ResolveWorkspace, queued jobs (in their workspace, without one, not
// tenant-aware), the preflight problems (superuser, BYPASSRLS, owner, unprotected table, admin as runtime) and the no-op on another driver.
// Not covered: a real pooler (PgBouncer), Octane, Horizon or a long-lived worker process, Laravel Cloud, Inertia pages.
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
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\CallQueuedHandler;
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
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Kit\Features\Core\Deploy\PreflightCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Console\RlsRoleCommand;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Rls;
use Tey\Kit\Features\Workspaces\RlsQueuedHandler;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function rlsCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The exception message $fn throws, or null. */
function rlsRefusal(Closure $fn): ?string
{
    try {
        $fn();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
}

class RlsUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(RlsUser::class, 'App\Models\User');

/** Counts the protected rows a job can see. */
abstract class RlsProbeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public static array $seen = [];

    public function __construct(public string $label) {}

    public function handle(): void
    {
        self::$seen[$this->label] = [DB::table('rls_notes')->count(), Rls::current()];
    }
}

final class RlsAwareJob extends RlsProbeJob implements TenantAware {}

final class RlsPlainJob extends RlsProbeJob {}

final class RlsCentralJob extends RlsProbeJob implements NotTenantAware {}

/** A workspace page with its app scoping removed on purpose: only RLS stands between it and another workspace's rows. */
final class RlsProbeController
{
    public function index(Request $request, int $workspace): mixed
    {
        return response()->json(['setting' => Rls::current(), 'titles' => DB::table('rls_notes')->orderBy('id')->pluck('title')->all()]);
    }

    public function rename(Request $request, int $workspace, int $note): mixed
    {
        return response()->json(['updated' => DB::table('rls_notes')->where('id', $note)->update(['title' => 'renamed'])]);
    }

    public function queue(Request $request, int $workspace): mixed
    {
        dispatch(new RlsAwareJob('from-request'));

        return response()->json(['queued' => true]);
    }
}

if (getenv('DB_CONNECTION') !== 'pgsql') {
    fwrite(STDERR, "FAIL RlsCheck is PostgreSQL only (row-level security does not exist on SQLite): set DB_CONNECTION=pgsql and the DB_* variables.\n");
    exit(1);
}

$runtimeRole = 'kit_rls_check_runtime';
$bypassRole = 'kit_rls_check_bypass';
$admin = null;
try {
    $server = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_rls_check', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'kit_rls', 'sslmode' => 'prefer'];
    $password = bin2hex(random_bytes(16));
    $base = sys_get_temp_dir().'/kit-rls-'.bin2hex(random_bytes(4));
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
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => RlsUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        // The admin (migrator, owner, superuser here) runs the migrations; the app runs as the runtime role, created below by kit:rls:role.
        'database' => ['default' => 'pgsql_admin', 'connections' => [
            'pgsql_admin' => $server + ['username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: ''],
            'pgsql' => $server + ['username' => $runtimeRole, 'password' => $password],
            'bypass' => $server + ['username' => $bypassRole, 'password' => $password],
            'lite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings'], 'models' => ['user' => RlsUser::class],
            'tenancy' => ['rls' => ['enabled' => true, 'connection' => null, 'admin_connection' => 'pgsql_admin', 'except' => (require dirname(__DIR__).'/config/kit.php')['tenancy']['rls']['except']]]],
        'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'connection' => 'pgsql', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90], 'sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $routes = $base.'/probe-routes.php';
    file_put_contents($routes, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
        WorkspaceRoutes::define(function (bool $full): void {
            Route::get('rls-notes', [RlsProbeController::class, 'index']);
            Route::get('rls-notes/{note}/rename', [RlsProbeController::class, 'rename']);
            Route::get('rls-queue', [RlsProbeController::class, 'queue']);
        });
        PHP);
    $app->make(Registry::class)->routes($routes);
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $admin = DB::connection('pgsql_admin');
    echo 'Database: '.$admin->getDriverName().' '.$admin->selectOne('show server_version')->server_version."\n";

    // A clean slate: our schema and our two roles, from any earlier run.
    $drop = function () use ($runtimeRole, $bypassRole): void {
        $db = DB::connection('pgsql_admin');
        $db->statement('drop schema if exists kit_rls cascade');
        foreach ([$runtimeRole, $bypassRole] as $role) {
            if ($db->selectOne('select 1 as found from pg_roles where rolname = ?', [$role]) !== null) {
                $db->statement('drop owned by "'.$role.'"');
                $db->statement('drop role "'.$role.'"');
            }
        }
    };
    $drop();
    $admin->statement('create schema kit_rls');
    DB::purge('pgsql_admin');
    $admin = DB::connection('pgsql_admin');

    // The kit's and Concierge's tables, as the migrator.
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
        $table->unsignedSmallInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    // Foreign keys are enforced here, so the workspaces table comes before Concierge's tables that reference it.
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/resources/features/{core,workspaces}/migrations/*.stub', GLOB_BRACE) as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    $concierge = glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php');
    foreach ($stubs as $name => $file) {
        (require $file)->up();
        if (str_contains($name, '000200_create_kit_workspaces_table')) {
            foreach ($concierge as $migration) {
                (require $migration)->up();
            }
        }
    }
    // An app's workspace table, protected by the helper (twice: it is idempotent), and one left unprotected for the guard.
    Schema::create('rls_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id')->index();
        $table->string('title');
    });
    Rls::protect('rls_notes');
    Rls::protect('rls_notes');
    Schema::create('rls_orphans', function (Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id');
    });
    $flags = $admin->selectOne("select relrowsecurity as r, relforcerowsecurity as f, (select count(*) from pg_policy where polrelid = 'rls_notes'::regclass) as policies from pg_class where oid = 'rls_notes'::regclass");
    rlsCheck($flags->r && $flags->f && (int) $flags->policies === 1, 'Rls::protect() enables and forces RLS with one workspace policy, and running it again changes nothing');

    // The runtime role, through the command; the password comes from the environment, never an argument.
    putenv('KIT_RLS_CHECK_PASSWORD='.$password);
    // Commands run directly, as the console kernel's bootstrap would reload configuration from the environment.
    $artisan = function (string $command, array $parameters = []) use ($app): array {
        $instance = $app->make(['kit:rls:role' => RlsRoleCommand::class, 'kit:preflight' => PreflightCommand::class][$command]);
        $instance->setLaravel($app);
        $input = new ArrayInput($parameters);
        $input->setInteractive(false);
        $output = new BufferedOutput;
        $code = $instance->run($input, $output);

        return [$code, $output->fetch()];
    };
    [$code, $out] = $artisan('kit:rls:role', ['role' => $runtimeRole, '--password-env' => 'KIT_RLS_CHECK_PASSWORD']);
    rlsCheck($code === 0 && ! str_contains($out, $password), 'kit:rls:role creates the runtime role on the admin connection and prints no password');
    [$code] = $artisan('kit:rls:role', ['role' => $runtimeRole, '--password-env' => 'KIT_RLS_CHECK_PASSWORD']);
    rlsCheck($code === 0, 'running it again reshapes the existing role');
    [$code, $out] = $artisan('kit:rls:role', ['role' => 'bad"name', '--password-env' => 'KIT_RLS_CHECK_PASSWORD']);
    rlsCheck($code === 1 && str_contains($out, 'Not a plain identifier'), 'a role name that is not a plain identifier is refused');
    $role = $admin->selectOne('select rolsuper, rolbypassrls, rolinherit, rolcreaterole, rolcreatedb, rolcanlogin from pg_roles where rolname = ?', [$runtimeRole]);
    rlsCheck(! $role->rolsuper && ! $role->rolbypassrls && ! $role->rolinherit && ! $role->rolcreaterole && ! $role->rolcreatedb && $role->rolcanlogin, 'the runtime role logs in with NOSUPERUSER, NOBYPASSRLS, NOINHERIT, no CREATEROLE or CREATEDB');
    // A table the migrator creates after the role exists: default privileges grant it.
    Schema::create('rls_later', function (Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id');
    });
    Rls::protect('rls_later');

    // The app now runs as the runtime role.
    $config->set('database.default', 'pgsql');
    DB::purge('pgsql');
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'member' => ['label' => 'Member', 'grantable_by' => ['owner']]]);
    $who = DB::selectOne('select current_user as name')->name;
    rlsCheck($who === $runtimeRole && Rls::enabled(), 'the default connection logs in as the runtime role, and RLS is enabled');

    // Fixtures, as the migrator (the way seeders and operators would).
    $fixtures = function () use ($admin): array {
        $admin->table('rls_notes')->delete();
        $ids = [];
        foreach (['Northwind Farms' => 'northwind', 'Acme Travel' => 'acme'] as $name => $slug) {
            $id = $admin->table('workspaces')->where('slug', $slug)->value('id') ?? $admin->table('workspaces')->insertGetId(['name' => $name, 'slug' => $slug]);
            $admin->table('rls_notes')->insert([['workspace_id' => $id, 'title' => $slug.'-1'], ['workspace_id' => $id, 'title' => $slug.'-2']]);
            $ids[$slug] = (int) $id;
        }

        return $ids;
    };
    ['northwind' => $a, 'acme' => $b] = $fixtures();
    $bNote = (int) $admin->table('rls_notes')->where('workspace_id', $b)->orderBy('id')->value('id');

    // Missing setting: nothing visible, nothing insertable.
    rlsCheck(DB::table('rls_notes')->count() === 0 && Rls::current() === null, 'with no workspace set, a protected table shows no rows');
    $refused = rlsRefusal(fn () => DB::table('rls_notes')->insert(['workspace_id' => $a, 'title' => 'x']));
    rlsCheck($refused !== null && str_contains($refused, 'row-level security'), 'and refuses an insert');
    rlsCheck($admin->table('rls_notes')->count() === 4, 'the admin connection sees every workspace');

    // Within a workspace: its rows only; another's are refused with app scoping removed.
    $titles = Rls::within($a, fn () => DB::table('rls_notes')->orderBy('id')->pluck('title')->all());
    rlsCheck($titles === ['northwind-1', 'northwind-2'], 'Rls::within() shows only the workspace\'s rows');
    Rls::within($a, function () use ($bNote, $b): void {
        rlsCheck(DB::table('rls_notes')->where('id', $bNote)->first() === null, 'another workspace\'s row cannot be read by id');
        rlsCheck(DB::table('rls_notes')->where('id', $bNote)->update(['title' => 'x']) === 0, 'updating it affects 0 rows (silently, not an error)');
        rlsCheck(DB::table('rls_notes')->where('id', $bNote)->delete() === 0 && DB::table('rls_notes')->where('workspace_id', $b)->delete() === 0, 'deleting it, or all of that workspace\'s rows, affects 0 rows');
    });
    rlsCheck($admin->table('rls_notes')->where('id', $bNote)->value('title') === 'acme-1', 'and the row is untouched');
    $refused = rlsRefusal(fn () => Rls::within($a, fn () => DB::table('rls_notes')->insert(['workspace_id' => $b, 'title' => 'x'])));
    rlsCheck($refused !== null && str_contains($refused, 'row-level security'), 'inserting into another workspace is refused');
    $refused = rlsRefusal(fn () => Rls::within($a, fn () => DB::table('rls_notes')->update(['workspace_id' => $b])));
    rlsCheck($refused !== null && str_contains($refused, 'row-level security') && $admin->table('rls_notes')->where('workspace_id', $a)->count() === 2, 'moving a row to another workspace is refused (WITH CHECK) and nothing moves');
    rlsCheck(DB::transactionLevel() === 0 && Rls::current() === null && DB::table('rls_notes')->count() === 0, 'after within(), the transaction is closed and nothing is set on the connection (SET LOCAL only)');
    rlsCheck(DB::selectOne("select setting from pg_settings where name = 'kit.workspace_id'") === null || DB::selectOne("select current_setting('kit.workspace_id', true) as v")->v === '', 'no session-level value of kit.workspace_id is left behind');

    // Nested: an inner workspace inside an open transaction, then the outer one back, also after a throw.
    $nested = Rls::within($a, function () use ($b): array {
        $inner = Rls::within($b, fn () => [Rls::current(), DB::table('rls_notes')->count()]);
        rlsRefusal(fn () => Rls::within($b, fn () => throw new RuntimeException('inner')));

        return [$inner, Rls::current()];
    });
    rlsCheck($nested === [[$b, 2], $a], 'a nested within() switches the id for its closure and restores the outer one, also when it throws');

    // Workspaces::run, which ResolveWorkspace uses for the whole request, carries the id.
    rlsCheck(Workspaces::run($b, fn () => [Rls::current(), DB::table('rls_notes')->count()]) === [$b, 2] && Rls::current() === null, 'Workspaces::run() runs its closure in the workspace\'s RLS transaction');

    // The runtime role cannot turn RLS off or step around it.
    $refused = rlsRefusal(fn () => DB::statement('alter table rls_notes disable row level security'));
    rlsCheck($refused !== null && str_contains($refused, 'must be owner'), 'the runtime role cannot disable RLS (not the owner)');
    $refused = rlsRefusal(fn () => DB::statement('truncate rls_notes'));
    rlsCheck($refused !== null && str_contains($refused, 'permission denied') && $admin->table('rls_notes')->count() === 4, 'nor TRUNCATE (which skips RLS): it has no TRUNCATE grant');
    rlsCheck(Rls::within($a, fn () => DB::table('rls_later')->insert(['workspace_id' => $a])) === true && $admin->table('rls_later')->count() === 1, 'a table created after kit:rls:role is granted to it by default privileges');

    // A workspace request, through ResolveWorkspace, with the controller's scoping removed.
    $admin->table('users')->insert(['name' => 'Member', 'email' => 'member@example.test', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $user = RlsUser::query()->where('email', 'member@example.test')->firstOrFail();
    $admin->table('concierge_memberships')->insert(['workspace_id' => $a, 'user_id' => $user->id, 'status' => MembershipStatus::Active->value, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $request = function (string $path) use ($app, $user): array {
        Auth::guard('web')->setUser($user);
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    [$status, $body] = $request('/@northwind/rls-notes');
    rlsCheck($status === 200 && $body === ['setting' => $a, 'titles' => ['northwind-1', 'northwind-2']], 'a workspace request runs in one transaction carrying its workspace id and sees only its rows');
    rlsCheck(DB::transactionLevel() === 0 && Rls::current() === null && DB::table('rls_notes')->count() === 0, 'after the request nothing is left on the connection');
    [$status, $body] = $request('/@northwind/rls-notes/'.$bNote.'/rename');
    rlsCheck($status === 200 && $body === ['updated' => 0] && $admin->table('rls_notes')->where('id', $bNote)->value('title') === 'acme-1', 'renaming another workspace\'s note over HTTP with app scoping removed updates 0 rows');

    // Jobs: each runs in one transaction carrying the workspace spatie restored; without one it fails closed.
    $worker = $app->make('queue.worker');
    $options = new WorkerOptions(sleep: 0, maxTries: 1);
    $failed = null;
    $app['events']->listen(JobExceptionOccurred::class, function ($event) use (&$failed): void {
        $failed = $event->exception;
    });
    $work = function () use ($worker, $options, &$failed): ?Throwable {
        $failed = null;
        try {
            $worker->runNextJob('database', 'default', $options);
        } catch (Throwable $error) {
            $failed ??= $error;
        }

        return $failed;
    };
    rlsCheck($app->make(CallQueuedHandler::class) instanceof RlsQueuedHandler, 'with RLS on, queued jobs run through RlsQueuedHandler');
    RlsProbeJob::$seen = [];
    $request('/@northwind/rls-queue');
    Workspaces::run($b, function (): void {
        dispatch(new RlsAwareJob('in-acme'));
    });
    Workspace::forgetCurrent();
    $work();
    $work();
    rlsCheck(RlsProbeJob::$seen === ['from-request' => [2, $a], 'in-acme' => [2, $b]] && DB::table('jobs')->count() === 0, 'a job queued in a workspace (from a request or Workspaces::run) sees only that workspace\'s rows');
    rlsCheck(Rls::current() === null && DB::transactionLevel() === 0, 'after the jobs nothing is left on the worker\'s connection');
    dispatch(new RlsAwareJob('orphan'));
    rlsCheck($work() instanceof CurrentTenantCouldNotBeDeterminedInTenantAwareJob && ! isset(RlsProbeJob::$seen['orphan']), 'a tenant-aware job queued with no workspace fails before handle() runs');
    $config->set('multitenancy.queues_are_tenant_aware_by_default', false);
    dispatch(new RlsPlainJob('plain'));
    $error = $work();
    rlsCheck($error instanceof RuntimeException && str_starts_with($error->getMessage(), 'rls_workspace_required') && ! isset(RlsProbeJob::$seen['plain']), 'a job spatie does not restore a workspace for fails closed with rls_workspace_required before handle() runs');
    dispatch(new RlsCentralJob('central'));
    rlsCheck($work() === null && RlsProbeJob::$seen['central'] === [0, null], 'a NotTenantAware job runs with no workspace id and sees no protected rows');
    $config->set('multitenancy.queues_are_tenant_aware_by_default', true);

    // The guard and the preflight.
    $unprotected = Rls::unprotected();
    rlsCheck(in_array('rls_orphans', $unprotected, true) && ! in_array('rls_notes', $unprotected, true) && ! in_array('rls_later', $unprotected, true) && ! in_array('concierge_memberships', $unprotected, true), 'the guard lists a workspace table without the policy, not protected or excepted ones ('.implode(', ', $unprotected).')');
    $problems = Rls::problems();
    rlsCheck(count($problems) === 1 && str_contains($problems[0], 'without the RLS policy') && str_contains($problems[0], 'rls_orphans'), 'the preflight problems name the unprotected tables');
    [$code, $out] = $artisan('kit:preflight');
    rlsCheck($code === 1 && str_contains($out, 'Row-level security:') && str_contains($out, 'rls_orphans'), 'kit:preflight prints the RLS problem and fails');
    $config->set('kit.tenancy.rls.except', array_merge($config->get('kit.tenancy.rls.except'), $unprotected));
    rlsCheck(Rls::problems() === [] && Rls::unprotected() === [], 'with every remaining table protected or excepted, there are no problems');
    [, $out] = $artisan('kit:preflight');
    rlsCheck(! str_contains($out, 'Row-level security:'), 'and kit:preflight prints none');
    $config->set('kit.tenancy.rls.connection', 'pgsql_admin');
    $problems = Rls::problems();
    rlsCheck(count($problems) === 1 && str_contains($problems[0], 'is the admin connection'), 'the admin connection as the runtime one is a problem');
    $config->set('kit.tenancy.rls.admin_connection', 'unused');
    $problems = implode("\n", Rls::problems());
    rlsCheck(str_contains($problems, 'is a superuser') && str_contains($problems, 'owns ') && str_contains($problems, 'rls_notes'), 'a superuser that owns the tables is a problem: '.str_replace("\n", '; ', $problems));
    $admin->statement('create role "'.$bypassRole.'" login bypassrls password '.$admin->getPdo()->quote($password));
    $admin->statement('grant usage on schema kit_rls to "'.$bypassRole.'"');
    $config->set('kit.tenancy.rls.connection', 'bypass');
    $problems = Rls::problems();
    rlsCheck(count($problems) === 1 && str_contains($problems[0], 'has BYPASSRLS'), 'a runtime role with BYPASSRLS is a problem');
    $config->set('kit.tenancy.rls.connection', null);
    $config->set('kit.tenancy.rls.admin_connection', 'pgsql_admin');

    // Off, or on another driver: a no-op.
    $config->set('kit.tenancy.rls.enabled', false);
    rlsCheck(! Rls::enabled() && Rls::within($a, fn () => DB::transactionLevel()) === 0 && Rls::problems() === [] && DB::table('rls_notes')->count() === 0, 'off: within() opens no transaction and sets nothing (the policy still hides every row), and there are no problems');
    $config->set('kit.tenancy.rls.enabled', true);
    $config->set('kit.tenancy.rls.connection', 'lite');
    rlsCheck(! Rls::enabled() && Rls::within($a, fn () => 'ran') === 'ran' && Rls::current() === null && Rls::problems() === [], 'on SQLite: enabled() is false, within() just runs, problems() is empty');
    $config->set('database.default', 'lite');
    Rls::protect('anything');
    rlsCheck(Rls::unprotected('lite') === [], 'protect() and the guard do nothing on SQLite');
    $config->set('database.default', 'pgsql');
    $config->set('kit.tenancy.rls.connection', null);

    echo "Row-level security on PostgreSQL: runtime role, policy, guard, request, jobs and preflight; no pooler, Octane, Horizon or Laravel Cloud run.\n";
    $code = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    $code = 1;
} finally {
    try {
        DB::disconnect('pgsql');
        DB::disconnect('bypass');
        if (isset($drop)) {
            $drop();
        }
    } catch (Throwable $error) {
        fwrite(STDERR, 'Cleanup: '.$error->getMessage()."\n");
    }
}
exit($code);
