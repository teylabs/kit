<?php

// The kit defines workspace capabilities in Pennant lazily (kit#949): a request that never checks one (`/up`) builds no Registry and resolves no
// Pennant manager. Once Pennant is used, every declared capability is defined: one declared in a host provider's register() or boot(), whether Pennant
// was first used before boot, during it or after it; a host's own Feature::define is kept (kit#947); and a check made before boot fails closed.
// SQLite memory, real Pennant (array store), synthetic workspaces; no users, no seeding.
use Illuminate\Auth\AuthServiceProvider;
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
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Laravel\Pennant\FeatureManager;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function lazyCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class LazyUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

/** Declares a capability in register() and leaves its definition to the kit. */
class RegisterCapabilityProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(Registry::class)->capabilities(['workspace.registered']);
    }
}

/** Declares a capability in boot(), after the kit's providers have booted. */
class BootCapabilityProvider extends ServiceProvider
{
    public function boot(Registry $registry): void
    {
        $registry->capabilities(['workspace.late']);
    }
}

/** Declares a capability in register() and defines it itself in boot(), to follow its own rule rather than kit.capabilities. */
class OwnDefinitionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(Registry::class)->capabilities(['workspace.followed']);
    }

    public function boot(): void
    {
        Feature::define('workspace.followed', fn (Tey\Concierge\Contracts\Workspace $workspace): bool => true);
    }
}

/** Asks Pennant in register(), before the kit has booted or defined anything. */
class EarlyCheckProvider extends ServiceProvider
{
    public static ?bool $answer = null;

    public function register(): void
    {
        self::$answer = Feature::for(LazyWorkspace::make())->active('workspace.settings');
    }
}

final class LazyWorkspace
{
    public static function make(): Workspace
    {
        return (new Workspace)->forceFill(['id' => 1]);
    }
}

class_alias(LazyUser::class, 'App\Models\User');

/**
 * @param  list<class-string<ServiceProvider>>  $hostProviders
 * @param  list<string>  $features
 */
function lazyApp(array $hostProviders, bool $optimized, array $features = ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp']): Application
{
    $base = sys_get_temp_dir().'/kit-capability-lazy-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    if ($optimized) {
        // As after `php artisan optimize`: route and config caches. The kit skips loading its route files and its uncached-config defaults (both build the Registry).
        file_put_contents($app->getCachedRoutesPath(), "<?php\n");
        // The config cache's file marks config as cached; this check hands the app its config directly, as the cache would.
        file_put_contents($app->getCachedConfigPath(), "<?php return [];\n");
    }
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'production');
    // A cached config holds every package's merged config: the packages' own files, as `config:cache` would have merged them.
    $vendor = dirname(__DIR__).'/vendor';
    $packages = $optimized ? ['settings' => require $vendor.'/spatie/laravel-settings/config/settings.php', 'permission' => require $vendor.'/spatie/laravel-permission/config/permission.php', 'multitenancy' => require $vendor.'/spatie/laravel-multitenancy/config/multitenancy.php', 'data' => require $vendor.'/spatie/laravel-data/config/data.php', 'concierge' => require $vendor.'/tey/concierge/config/concierge.php'] : [];
    $app->instance('config', new Repository($packages + [
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'production'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => LazyUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'pennant' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        'kit' => ['app_id' => null, 'features' => $features, 'models' => ['user' => LazyUser::class], 'capabilities' => (require dirname(__DIR__).'/config/kit.php')['capabilities'] + ['workspace.registered' => true, 'workspace.late' => true], 'mcp' => ['ui' => true]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class,
        ...$hostProviders,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => []]);

    return $app;
}

/** @return array<string, bool> each capability's answer for one workspace, straight from Pennant */
function lazyAnswers(array $capabilities): array
{
    Feature::flushCache();

    return array_combine($capabilities, array_map(fn (string $capability): bool => Feature::for(LazyWorkspace::make())->active($capability), $capabilities));
}

try {
    // 1. A plain route, as `/up` after `optimize`: nothing asks Pennant, so neither the Registry nor the Pennant manager is built. Without mcp:
    // mcp builds the Registry at boot for Passport's token scopes (Passport::tokensCan is static), so with mcp only the Pennant manager stays unresolved.
    foreach (['without mcp' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'with mcp' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp']] as $label => $features) {
        $app = lazyApp([], optimized: true, features: $features);
        // Marked bootstrapped, so the kernel neither reloads the config from the stand-in cache file nor boots the app again.
        $app->bootstrapWith([]);
        $app->boot();
        $app['router']->get('/up', fn () => 'ok');
        $response = $app->make(Kernel::class)->handle(Request::create('https://kit.test/up'));
        lazyCheck($response->getStatusCode() === 200 && $response->getContent() === 'ok', $label.': a plain route answers ('.$response->getStatusCode().')');
        lazyCheck(! $app->resolved(FeatureManager::class), $label.': a plain route resolves no Pennant manager');
        if ($label === 'without mcp') {
            lazyCheck(! $app->resolved(Registry::class), $label.': a plain route builds no Registry');
        }
    }
    // The first use of Pennant, after boot, defines every declared capability.
    lazyCheck(lazyAnswers(['workspace.settings'])['workspace.settings'] === true, 'Pennant first used after boot: a kit capability is defined and follows kit.capabilities (on)');

    // 2. Pennant first used after boot, with capabilities a host declares in register() and boot(), and one it defines itself.
    $app = lazyApp([RegisterCapabilityProvider::class, BootCapabilityProvider::class], optimized: true);
    $app->boot();
    lazyCheck(! $app->resolved(FeatureManager::class), 'host providers that only declare capabilities resolve no Pennant manager');
    lazyCheck(lazyAnswers(['workspace.registered', 'workspace.late']) === ['workspace.registered' => true, 'workspace.late' => true], 'Pennant first used after boot: capabilities a host declares in register() and boot() are defined (on)');

    // 3. Pennant first used during boot (a host provider defines its own feature): the kit's pass on booted still defines one declared in boot(), and the host's own definition is kept.
    $app = lazyApp([RegisterCapabilityProvider::class, OwnDefinitionProvider::class, BootCapabilityProvider::class], optimized: true);
    $app->boot();
    lazyCheck(lazyAnswers(['workspace.registered', 'workspace.late', 'workspace.followed', 'workspace.settings']) === ['workspace.registered' => true, 'workspace.late' => true, 'workspace.followed' => true, 'workspace.settings' => true], 'Pennant first used during boot: capabilities declared in register() and boot() are defined, and the host\'s own definition is kept (on, though kit.capabilities leaves it unset)');

    // 4. Pennant first asked before boot: undefined, so off (fail closed); once booted, every declared capability is defined all the same.
    EarlyCheckProvider::$answer = null;
    $app = lazyApp([EarlyCheckProvider::class, RegisterCapabilityProvider::class, BootCapabilityProvider::class], optimized: true);
    lazyCheck(EarlyCheckProvider::$answer === false, 'a capability checked before boot is off (fail closed)');
    $app->boot();
    lazyCheck(lazyAnswers(['workspace.settings', 'workspace.registered', 'workspace.late']) === ['workspace.settings' => true, 'workspace.registered' => true, 'workspace.late' => true], 'Pennant first used before boot: after boot every declared capability is defined (on)');

    echo "Covered: Pennant's definitions through a real FeatureManager (array store) and a plain route through the HTTP kernel with empty route and config cache files standing in for `optimize`. Not covered: a 404 (the kit's error page is the Registry's, so it builds it by design), the Registry on a host with mcp (built at boot for Passport's static token scopes), a capability checked through Capabilities (per-person overrides; test:capability-declared and test:entitlements), PHP-FPM or Octane workers, and the timing itself (CHANGELOG).\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
