<?php

// session-ui's Inertia props compose with the host's own HandleInertiaRequests. On a core, shell and session-ui host, a page carries session-ui's
// `kitPassword`, `passkeyOffer` and `environment`, the shell feature's `shell` and core's `kitUi` beside the host's own `hostThing`: whether the host's
// middleware is in `web` before the kit boots, is added after it, is followed by a later appendMiddlewareToGroup (which rebuilds the router's groups
// from the kernel's, the re-sync that dropped a router-only push), lists the kit's old middleware after its own, or is missing (the kit adds its plain
// one). Nothing in src flushes Inertia's shared props, and two requests in one app share nothing of each other's (as under Octane).
// Synthetic app, SQLite memory, real Fortify and Inertia, synthetic users; no network, no browser.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
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
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\PrepareKitRequest;
use Tey\Kit\KitServiceProvider;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function sharesCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class SharesUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** The host's own Inertia middleware, as a Laravel starter app has it: its shares, one of them the signed-in person's. */
class HostHandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request), 'hostThing' => 'from the host', 'hostUser' => fn (): ?string => $request->user()?->name];
    }
}

/** A later middleware a host or package appends through the kernel: each append rebuilds the router's groups from the kernel's. */
class HostLateMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}

/**
 * A core, shell and session-ui host. `$web` is the host's `web` group before the providers boot; `$after` runs once they have.
 *
 * @return array{0: Application, 1: Closure(Request): Response, 2: list<SharesUser>}
 */
function sharesHost(array $web, ?Closure $after = null): array
{
    $root = sys_get_temp_dir().'/kit-inertia-shares-'.bin2hex(random_bytes(6));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    mkdir($root.'/bootstrap/cache', 0700, true);
    mkdir($root.'/storage/framework/views', 0700, true);
    $app = new Application($root);
    $app->useStoragePath($root.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Northwind Farms', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => SharesUser::class]], 'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
        'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'shell', 'session-ui'], 'models' => ['user' => SharesUser::class]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FoundationServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, LaravelDataServiceProvider::class, ServiceProvider::class, KitServiceProvider::class, FeaturesServiceProvider::class, FortifyServiceProvider::class,
        NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's own groups are the kernel's before any provider boots (bootstrap/app.php's withMiddleware applies when the kernel resolves).
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => $web]);
    $app->boot();
    foreach (['auth' => Authenticate::class, 'throttle' => ThrottleRequests::class, 'guest' => RedirectIfAuthenticated::class] as $alias => $middleware) {
        $app['router']->aliasMiddleware($alias, $middleware);
    }
    if ($after !== null) {
        $after($app);
    }
    // A host page that renders for anyone, signed in or not.
    Route::middleware('web')->get('/probe', fn () => Inertia::render('Probe'));
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach (glob(dirname(__DIR__).'/resources/features/session-ui/migrations/*.stub') as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();
    $users = [
        SharesUser::create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now(), 'password' => 'x']),
        SharesUser::create(['name' => 'Grace Example', 'email' => 'grace@example.test', 'email_verified_at' => now(), 'password' => 'x']),
    ];
    $dispatch = function (Request $request, ?SharesUser $as = null) use ($app): Response {
        // Each request starts with no signed-in person, as Octane gives every request fresh guards.
        $app['auth']->forgetGuards();
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        if ($as !== null) {
            Auth::guard('web')->setUser($as);
        }
        try {
            return $app['router']->dispatch($request);
        } catch (Throwable $error) {
            return $app->make(ExceptionHandler::class)->render($request, $error);
        }
    };

    return [$app, $dispatch, $users];
}

/** @return array<string, mixed> */
function sharesProps(Response $response): array
{
    $body = json_decode((string) $response->getContent(), true);
    if (! is_array($body) || ! isset($body['props'])) {
        throw new RuntimeException('not an Inertia page ('.$response->getStatusCode().'): '.substr((string) $response->getContent(), 0, 300));
    }

    return $body['props'];
}

$inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
$kitKeys = ['kitPassword', 'passkeyOffer', 'environment', 'shell', 'kitUi'];
$has = fn (array $props, array $keys): bool => array_diff($keys, array_keys($props)) === [];

try {
    // No flushShared anywhere in the kit's source: a flush drops the host's shares (and every other package's).
    $flushes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), 'flushShared')) {
            $flushes[] = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
        }
    }
    sharesCheck($flushes === [], 'nothing in src calls Inertia\'s flushShared'.($flushes === [] ? '' : ': '.implode(', ', $flushes)));

    $scenarios = [
        'the host\'s middleware is in `web` before the kit boots' => [[StartSession::class, HostHandleInertiaRequests::class], null],
        'the host\'s middleware is appended after the kit boots' => [[StartSession::class], fn (Application $app) => $app->make(Kernel::class)->appendMiddlewareToGroup('web', HostHandleInertiaRequests::class)],
        'a later appendMiddlewareToGroup re-syncs the router\'s groups from the kernel\'s' => [[StartSession::class, HostHandleInertiaRequests::class], function (Application $app): void {
            $app['router']->pushMiddlewareToGroup('web', HostLateMiddleware::class);
            $app->make(Kernel::class)->appendMiddlewareToGroup('web', HostLateMiddleware::class);
        }],
        'the host lists the kit\'s old middleware after its own' => [[StartSession::class, HostHandleInertiaRequests::class, HandleInertiaRequests::class], null],
    ];
    foreach ($scenarios as $label => [$web, $after]) {
        [$app, $dispatch] = sharesHost($web, $after);
        $props = sharesProps($dispatch(Request::create('https://kit.test/login', 'GET', server: $inertia)));
        sharesCheck($has($props, $kitKeys) && ($props['hostThing'] ?? null) === 'from the host' && is_array($props['kitPassword']) && ($props['shell']['kind'] ?? null) === 'guest', $label.': session-ui\'s sign-in page carries kitPassword, passkeyOffer, environment, shell and kitUi beside the host\'s hostThing (has: '.implode(', ', array_keys($props)).')');
        $group = $app->make(Kernel::class)->getMiddlewareGroups()['web'];
        sharesCheck(in_array(PrepareKitRequest::class, $app['router']->getMiddlewareGroups()['web'], true) && in_array(PrepareKitRequest::class, $group, true), $label.': the kit\'s request hooks are in the kernel\'s `web` group, and so in the router\'s');
    }

    // A host with no Inertia middleware of its own: the kit adds its plain one (root view, asset version, errors), once.
    [$app, $dispatch] = sharesHost([StartSession::class]);
    $group = $app->make(Kernel::class)->getMiddlewareGroups()['web'];
    $props = sharesProps($dispatch(Request::create('https://kit.test/login', 'GET', server: $inertia)));
    sharesCheck(count(array_keys($group, HandleInertiaRequests::class, true)) === 1 && $has($props, [...$kitKeys, 'errors']), 'with no Inertia middleware of the host\'s, the kit adds its plain HandleInertiaRequests once, and the page has the kit\'s props and Inertia\'s errors');
    [$app] = sharesHost([StartSession::class, HostHandleInertiaRequests::class]);
    sharesCheck(! in_array(HandleInertiaRequests::class, $app->make(Kernel::class)->getMiddlewareGroups()['web'], true), 'with the host\'s own Inertia middleware, the kit adds none');

    // Sequential requests in one app, as Octane serves them: each sees only its own person, and the kit leaves only closures shared.
    [$app, $dispatch, [$ada, $grace]] = sharesHost([StartSession::class, HostHandleInertiaRequests::class]);
    $first = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $ada));
    $second = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $grace));
    $third = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia)));
    sharesCheck(($first['shell']['user']['name'] ?? null) === 'Ada Example' && ($first['hostUser'] ?? null) === 'Ada Example', 'the first request, signed in as Ada, has Ada in the shell and the host\'s share');
    sharesCheck(($second['shell']['user']['name'] ?? null) === 'Grace Example' && ($second['hostUser'] ?? null) === 'Grace Example' && $has($second, $kitKeys), 'the second request, as Grace, has Grace, not Ada, and every kit prop again');
    sharesCheck(($third['shell']['kind'] ?? null) === 'guest' && array_key_exists('hostUser', $third) && $third['hostUser'] === null, 'a third, signed out, has a guest\'s shell and no person');
    $stored = array_filter(array_intersect_key(Inertia::getShared(), array_flip($kitKeys)), fn (mixed $value): bool => ! $value instanceof Closure);
    sharesCheck(count(array_intersect_key(Inertia::getShared(), array_flip($kitKeys))) === count($kitKeys) && $stored === [], 'after the requests, every kit share on Inertia\'s factory is still a closure, so no value outlives its request'.($stored === [] ? '' : ': '.implode(', ', array_keys($stored))));

    echo "Inertia shares: synthetic core, shell and session-ui hosts on SQLite memory with real Fortify and Inertia. No Octane worker, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
