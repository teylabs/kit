<?php

// The kit's switch tenant tasks: making a workspace the current tenant (a TenantAware job or command, or Tenant::execute) sets Concierge's context
// and the permission team and flushes Pennant's cache (with a store that has a backend, as TenantBoundary does; the array store IS that cache, so it is kept);
// forgetting it restores both. A TenantAware job with no tenant fails.
// SQLite memory, a sync queue, synthetic data.
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

final class ProbeJob implements Illuminate\Contracts\Queue\ShouldQueue, Spatie\Multitenancy\Jobs\TenantAware
{
    use Illuminate\Bus\Queueable;

    public static array $seen = [];

    public function handle(): void
    {
        Feature::for('probe')->active('beta');
        self::$seen = [app(Tey\Concierge\Concierge::class)->context()->current()?->getKey(), app(PermissionRegistrar::class)->getPermissionsTeamId(), pennantCache()];
    }
}

/** How many results Pennant holds in memory. */
function pennantCache(): int
{
    $store = Feature::store();
    $cache = (new ReflectionProperty($store, 'cache'))->getValue($store);

    return count($cache);
}

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
    $base = sys_get_temp_dir().'/kit-tenant-tasks-'.bin2hex(random_bytes(4));
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
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
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
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class, Illuminate\Log\Context\ContextServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    app(Spatie\Multitenancy\Multitenancy::class)->start();
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
    [$one, $two] = [$make('One', 'one'), $make('Two', 'two')];
    $context = fn () => app(Tey\Concierge\Concierge::class)->context()->current()?->getKey();
    $team = fn () => app(PermissionRegistrar::class)->getPermissionsTeamId();
    Feature::define('beta', fn () => true);

    httpCheck(config('multitenancy.switch_tenant_tasks') === [Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask::class, Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask::class], "the kit's multitenancy defaults register both switch tasks");

    // Make and forget.
    Feature::for('probe')->active('beta');
    $one->makeCurrent();
    // Under the array store (the kit's default) Pennant's cache is the store itself: a switch keeps it, so a flag set in the request survives the switch.
    httpCheck($context() === $one->id && $team() === $one->id && pennantCache() === 1 && ! Tey\Kit\Features\Workspaces\TenantBoundary::flushes(), 'making a workspace current sets the Concierge context and the permission team, and keeps Pennant\'s array store (nothing to go stale)');
    Feature::for('probe')->active('beta');
    $two->makeCurrent();
    httpCheck($context() === $two->id && $team() === $two->id && pennantCache() === 1, 'switching straight to another workspace moves both and keeps the array store');
    Feature::for('probe')->active('beta');
    Workspace::forgetCurrent();
    httpCheck($context() === null && $team() === null && pennantCache() === 1, 'forgetting the tenant clears the context and the team, and keeps the array store');
    // With a store that has a backend, the same switch flushes the cache so the next tenant reads fresh flags.
    config(['pennant.stores.database' => ['driver' => 'database'], 'pennant.default' => 'database']);
    httpCheck(Tey\Kit\Features\Workspaces\TenantBoundary::flushes() === true, 'with the database store the switch tasks and TenantBoundary flush Pennant');
    config(['pennant.default' => 'array']);
    $result = $one->execute(fn () => [$context(), $team()]);
    httpCheck($result === [$one->id, $one->id] && $context() === null && $team() === null, 'Tenant::execute gives the callback the context and restores it after');
    try {
        $one->execute(function () {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }
    httpCheck($context() === null && $team() === null && Workspace::current() === null, 'a failure inside the tenant still restores the context');

    // A TenantAware job.
    Feature::for('probe')->active('beta');
    $one->execute(function (): void {
        dispatch(new ProbeJob);
    });
    httpCheck(ProbeJob::$seen === [$one->id, $one->id, 1], 'a TenantAware job queued in a workspace sees its Concierge context, its team and a fresh Pennant cache');
    httpCheck($context() === null && $team() === null && Workspace::current() === null, 'after the job, the context and the team are cleared');
    ProbeJob::$seen = [];
    try {
        dispatch(new ProbeJob);
        $loud = false;
    } catch (Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob) {
        $loud = true;
    }
    httpCheck($loud && ProbeJob::$seen === [], 'a TenantAware job with no tenant fails loudly and never runs');

    echo "OK tenant switch tasks\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
