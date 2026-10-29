<?php

// Workspaces::run() and the kit's workspace test helpers. run() makes a workspace the current tenant through spatie's execute() and restores what
// was current before, on success, on a throw and when nested; an unknown id is refused before anything switches. Kit code passes it block closures.
// The InWorkspace trait and the Pest functions reset between tests and opt out cleanly (Pest is not installed here, so the hooks are called the way
// Laravel's TestCase and Pest call them). The factory concern refuses without a workspace and creates each row inside its own. SQLite memory, synthetic data.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\Multitenancy\Events\MakingTenantCurrentEvent;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Testing\CreatesInWorkspace;
use Tey\Kit\Testing\CurrentWorkspace;
use Tey\Kit\Testing\InWorkspace;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function runCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The current tenant, Concierge's current workspace and the permission team, by id. */
function runNow(): array
{
    return [Workspaces::current()?->getKey(), app(Concierge::class)->context()->current()?->getKey(), app(PermissionRegistrar::class)->getPermissionsTeamId()];
}

function runRefusal(callable $operation): ?string
{
    try {
        $operation();
    } catch (Throwable $error) {
        return $error instanceof ModelNotFoundException ? 'not_found' : $error->getMessage();
    }

    return null;
}

class RunUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(RunUser::class, 'App\Models\User');

final class RunTicket extends Model
{
    use BelongsToWorkspace;

    protected $table = 'run_tickets';

    protected $guarded = [];

    public static array $creatingIn = [];

    protected static function booted(): void
    {
        self::creating(function (self $ticket): void {
            self::$creatingIn[] = Workspaces::current()?->getKey();
        });
    }
}

/** @extends Factory<RunTicket> */
/** A TenantAware job that records the tenant it runs in. */
final class RunProbeJob implements Illuminate\Contracts\Queue\ShouldQueue, Spatie\Multitenancy\Jobs\TenantAware
{
    use Illuminate\Bus\Batchable, Illuminate\Bus\Queueable, Illuminate\Foundation\Bus\Dispatchable, Illuminate\Queue\InteractsWithQueue;

    public static array $ranIn = [];

    public function __construct(public string $label) {}

    public function handle(): void
    {
        self::$ranIn[$this->label] = Workspaces::current()?->getKey();
    }
}

final class RunTicketFactory extends Factory
{
    use CreatesInWorkspace;

    protected $model = RunTicket::class;

    public static array $afterIn = [];

    public function definition(): array
    {
        return ['title' => 'Synthetic ticket'];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RunTicket $ticket): void {
            // Reads the row back through the workspace scope: it only finds it inside the row's own workspace.
            self::$afterIn[] = [Workspaces::current()?->getKey(), RunTicket::query()->whereKey($ticket->getKey())->exists()];
        });
    }
}

/** A stand-in for a test case that uses the trait, as Laravel's TestCase calls its hooks. */
final class RunTestCase
{
    use InWorkspace;

    public function before(): void
    {
        $this->setUpInWorkspace();
    }

    public function after(): void
    {
        $this->tearDownInWorkspace();
    }

    public function optOut(): void
    {
        $this->withoutCurrentWorkspace();
    }

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }
}

/** Pest's uses(), recorded: the kit's withCurrentWorkspace() hands the trait to it. */
function uses(string ...$classes): array
{
    $GLOBALS['pestUses'][] = $classes;

    return $classes;
}

try {
    $base = sys_get_temp_dir().'/kit-workspace-run-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => RunUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access'], 'models' => ['user' => RunUser::class]],
        'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90], 'sync' => ['driver' => 'sync']], 'batching' => ['database' => 'testing', 'table' => 'job_batches'], 'failed' => ['driver' => 'null']],
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
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
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
    Schema::create('job_batches', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->integer('total_jobs');
        $table->integer('pending_jobs');
        $table->integer('failed_jobs');
        $table->longText('failed_job_ids');
        $table->mediumText('options')->nullable();
        $table->integer('cancelled_at')->nullable();
        $table->integer('created_at');
        $table->integer('finished_at')->nullable();
    });
    Schema::create('run_tickets', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->string('title');
        $table->timestamps();
    });
    $stubs = [];
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

    $one = Workspace::create(['name' => 'Northwind Farms', 'slug' => 'northwind']);
    $two = Workspace::create(['name' => 'Acme Travel', 'slug' => 'acme']);
    $none = [null, null, null];

    // run(): the workspace is current with its context and team, and nothing is current after.
    runCheck(Workspaces::run($one->id, function () {
        return runNow();
    }) === [$one->id, $one->id, $one->id] && runNow() === $none, 'run() by id makes the workspace current (tenant, Concierge context, permission team) and restores none after');
    runCheck(Workspaces::run($two, function () {
        return runNow();
    }) === [$two->id, $two->id, $two->id] && runNow() === $none, 'run() takes the workspace model too');
    $one->makeCurrent();
    runCheck(Workspaces::run($two->id, function () {
        return runNow();
    }) === [$two->id, $two->id, $two->id] && runNow() === [$one->id, $one->id, $one->id], 'run() restores the tenant that was current before');
    $thrown = runRefusal(function () use ($two): void {
        Workspaces::run($two->id, function (): void {
            throw new RuntimeException('probe_boom');
        });
    });
    runCheck($thrown === 'probe_boom' && runNow() === [$one->id, $one->id, $one->id], 'when the closure throws, the exception reaches the caller and the previous tenant is restored');
    Workspace::forgetCurrent();
    $thrown = runRefusal(function () use ($two): void {
        Workspaces::run($two->id, function (): void {
            throw new RuntimeException('probe_boom');
        });
    });
    runCheck($thrown === 'probe_boom' && runNow() === $none, 'and with none before, none is current after a throw');
    $trail = [];
    Workspaces::run($one->id, function () use (&$trail, $one, $two): void {
        $trail[] = runNow()[0];
        Workspaces::run($two->id, function () use (&$trail, $one): void {
            $trail[] = runNow()[0];
            Workspaces::run($one->id, function () use (&$trail): void {
                $trail[] = runNow()[0];
            });
            $trail[] = runNow()[0];
        });
        $trail[] = runNow()[0];
    });
    runCheck($trail === [$one->id, $two->id, $one->id, $two->id, $one->id] && runNow() === $none, 'nested runs restore each level on the way out');
    $switched = false;
    $app['events']->listen(MakingTenantCurrentEvent::class, function () use (&$switched): void {
        $switched = true;
    });
    $ran = false;
    runCheck(runRefusal(function () use (&$ran): void {
        Workspaces::run(999999, function () use (&$ran): void {
            $ran = true;
        });
    }) === 'not_found' && ! $ran && ! $switched && runNow() === $none, 'an unknown workspace id is refused (ModelNotFoundException) before the closure runs or anything switches');

    // Kit code passes run() block closures: an arrow function would return dispatch()'s pending job, queued after the tenant is restored.
    $arrows = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php' && preg_match('/Workspaces::run\([^;]*?,\s*(static\s+)?fn\b/', (string) file_get_contents($file->getPathname()))) {
            $arrows[] = $file->getPathname();
        }
    }
    runCheck($arrows === [], 'nothing under src/ passes Workspaces::run() an arrow function'.($arrows === [] ? '' : ': '.implode(', ', $arrows)));

    // InWorkspace: each test starts with a fresh workspace current and ends with none.
    $test = new RunTestCase;
    $test->before();
    $first = $test->workspace();
    runCheck($first !== null && runNow() === [$first->getKey(), $first->getKey(), $first->getKey()], 'InWorkspace makes a fresh workspace current before a test, with its context and team');
    $test->after();
    runCheck(runNow() === $none && $test->workspace() === null, 'and forgets it after the test');
    $test = new RunTestCase;
    $test->before();
    $second = $test->workspace();
    runCheck($second !== null && $second->getKey() !== $first->getKey() && runNow()[0] === $second->getKey(), 'the next test gets a workspace of its own, not the last one');
    $test->optOut();
    runCheck(runNow() === $none && $test->workspace() === null, 'withoutCurrentWorkspace() leaves no workspace current');
    $test->after();
    runCheck(runNow() === $none, 'and the teardown after an opt-out still leaves none');
    $two->makeCurrent();
    $test = new RunTestCase;
    $test->before();
    runCheck(runNow()[0] === $test->workspace()->getKey() && $test->workspace()->getKey() !== $two->id, 'a workspace left current by an earlier test is replaced, not reused');
    $test->after();
    CurrentWorkspace::createUsing(function () use ($two): Workspace {
        return $two;
    });
    $test = new RunTestCase;
    $test->before();
    runCheck(runNow()[0] === $two->id, 'createUsing() lets a host build the test workspace its own way');
    $test->after();
    CurrentWorkspace::createUsing(null);

    // The Pest functions: not autoloaded, required from a host's tests/Pest.php.
    runCheck(! function_exists('withCurrentWorkspace'), 'the Pest functions are not loaded by the autoloader');
    require dirname(__DIR__).'/src/Testing/pest.php';
    runCheck(withCurrentWorkspace() === [InWorkspace::class] && $GLOBALS['pestUses'] === [[InWorkspace::class]], 'withCurrentWorkspace() hands InWorkspace to Pest\'s uses()');
    CurrentWorkspace::enter();
    withoutCurrentWorkspace();
    runCheck(runNow() === $none, 'withoutCurrentWorkspace() in a Pest test leaves no workspace current');

    // A closure that returns a pending dispatch, even a block closure, still has its job queued inside the workspace, and run() returns null.
    $warnings = [];
    $app['events']->listen(Illuminate\Log\Events\MessageLogged::class, function ($event) use (&$warnings): void {
        if ($event->level === 'warning') {
            $warnings[] = [$event->message, $event->context['job'] ?? null];
        }
    });
    $queued = fn (): array => DB::table('jobs')->orderBy('id')->pluck('payload')->map(fn (string $payload): string => json_decode($payload, true)['displayName'] ?? '')->all();
    $work = function () use ($app): void {
        $worker = $app->make('queue.worker');
        while (DB::table('jobs')->count() > 0) {
            $worker->runNextJob('database', 'default', new Illuminate\Queue\WorkerOptions(sleep: 0, maxTries: 1));
        }
    };
    $two->makeCurrent();
    $returned = Workspaces::run($one->id, function () {
        return dispatch(new RunProbeJob('single'));
    });
    runCheck($returned === null && runNow()[0] === $two->id && $queued() === [RunProbeJob::class], 'a block closure returning dispatch() queues the job, returns null, and the tenant before is current after');
    runCheck($warnings === [['Workspaces::run() callback returned a pending dispatch; it was dispatched inside the workspace and run() returns null', RunProbeJob::class]], 'one warning names the job');
    Workspace::forgetCurrent();
    $work();
    runCheck(RunProbeJob::$ranIn === ['single' => $one->id], 'and the job carries and runs in the workspace run() was given, not the one current after');
    $returned = Workspaces::run($one->id, function () {
        return dispatch(new RunProbeJob('single'));
    });
    runCheck($returned === null && count($warnings) === 2, 'another call site warns once more');
    for ($i = 0; $i < 2; $i++) {
        Workspaces::run($one->id, function () {
            return dispatch(new RunProbeJob('loop'));
        });
    }
    runCheck(count($warnings) === 3 && count($queued()) === 3, 'a call site warns once however often it runs, and every job is queued');
    $work();
    RunProbeJob::$ranIn = [];
    $two->makeCurrent();
    $returned = Workspaces::run($one->id, function () {
        return Illuminate\Support\Facades\Bus::chain([new RunProbeJob('chain-1'), new RunProbeJob('chain-2')]);
    });
    runCheck($returned === null && runNow()[0] === $two->id && $queued() === [RunProbeJob::class], 'a returned chain is dispatched inside the workspace');
    Workspace::forgetCurrent();
    $work();
    runCheck(RunProbeJob::$ranIn === ['chain-1' => $one->id, 'chain-2' => $one->id], 'and both chained jobs run in it');
    RunProbeJob::$ranIn = [];
    $two->makeCurrent();
    $returned = Workspaces::run($one->id, function () {
        return Illuminate\Support\Facades\Bus::batch([new RunProbeJob('batch-1'), new RunProbeJob('batch-2')]);
    });
    runCheck($returned === null && runNow()[0] === $two->id && count($queued()) === 2, 'a returned batch is dispatched inside the workspace');
    Workspace::forgetCurrent();
    $work();
    runCheck(RunProbeJob::$ranIn === ['batch-1' => $one->id, 'batch-2' => $one->id], 'and its jobs run in it');

    // The factory concern.
    runCheck(runRefusal(fn () => RunTicketFactory::new()->create()) === 'workspace_required' && RunTicket::$creatingIn === [], 'the factory refuses with no workspace current and none given, before anything is saved');
    $one->makeCurrent();
    $ticket = RunTicketFactory::new()->create();
    runCheck((int) $ticket->workspace_id === $one->id && RunTicket::$creatingIn === [$one->id] && RunTicketFactory::$afterIn === [[$one->id, true]], 'the workspace column defaults to the current workspace');
    RunTicket::$creatingIn = RunTicketFactory::$afterIn = [];
    $ticket = RunTicketFactory::new()->create(['workspace_id' => $two->id]);
    runCheck((int) $ticket->workspace_id === $two->id && RunTicket::$creatingIn === [$two->id] && RunTicketFactory::$afterIn === [[$two->id, true]] && runNow()[0] === $one->id, 'a row given another workspace is created, and its afterCreating callbacks run, inside that workspace; the current one is restored');
    Workspace::forgetCurrent();
    RunTicket::$creatingIn = RunTicketFactory::$afterIn = [];
    $tickets = RunTicketFactory::new()->count(2)->sequence(['workspace_id' => $one->id], ['workspace_id' => $two->id])->create();
    runCheck($tickets->pluck('workspace_id')->map(fn ($id) => (int) $id)->all() === [$one->id, $two->id] && RunTicket::$creatingIn === [$one->id, $two->id] && RunTicketFactory::$afterIn === [[$one->id, true], [$two->id, true]] && runNow() === $none, 'each row of a batch is created inside its own workspace, with none current before or after');

    echo "OK workspace run\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
