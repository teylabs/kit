<?php

// session-ui's Inertia props compose with the host's own HandleInertiaRequests. On a core, shell and session-ui host, a page carries session-ui's
// `kitPassword`, `passkeyOffer` and `environment`, the shell feature's `shell` and core's `kitUi` beside the host's own `hostThing`: whether the host's
// middleware is in `web` before the kit boots, is added after it, is followed by a later appendMiddlewareToGroup (which rebuilds the router's groups
// from the kernel's, the re-sync that dropped a router-only push), lists the kit's old middleware after its own, or is missing (the kit adds its plain
// one). Nothing in src flushes Inertia's shared props, and two requests in one app share nothing of each other's (as under Octane). After an
// Inertia::flushShared() between requests (pest-plugin-browser's, before each one), the next page still has every kit prop and only its own person's,
// and a key the host shares itself (`shell`, `environment`) keeps the host's value with or without a flush. `kitClock` resolves the person's own clock, then
// the workspace's (Registry::workspaceClockUsing), then kit.clock.default, then auto, on every page (a public one on a host without session-ui, a portal
// one); core's Clock answers 12h or 24h for a person and a workspace with no request and formats server-built times on it, a queued mail included.
// Synthetic app, SQLite memory, real Fortify and Inertia, synthetic users; no network, no browser.
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
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
use Tey\Kit\Features\Core\Clock;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\ClientClock;
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

/** A host whose own Inertia middleware shares keys the kit also shares: the host's value wins. */
class HostOverridingInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request), 'hostThing' => 'from the host', 'environment' => ['label' => 'host environment'], 'shell' => ['kind' => 'host shell']];
    }
}

/** A later middleware a host or package appends through the kernel: each append rebuilds the router's groups from the kernel's. */
/** A queued mail that formats its booking on the clock of the person and workspace it was made for, at render time in the queue. */
class ClockMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public static int $rendered = 0;

    public function __construct(public ?int $userId, public ?int $workspaceId, public string $start, public string $end) {}

    public function build(): self
    {
        self::$rendered++;
        $zone = 'America/Halifax';

        return $this->subject('Your booking')->html('<p>'.e(Clock::range(CarbonImmutable::parse($this->start)->setTimezone($zone), CarbonImmutable::parse($this->end)->setTimezone($zone), $this->userId, $this->workspaceId)).'</p>');
    }
}

class HostLateMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}

/**
 * A core, shell and session-ui host (or `$features`). `$web` is the host's `web` group before the providers boot; `$after` runs once they have.
 *
 * @return array{0: Application, 1: Closure(Request): Response, 2: list<SharesUser>}
 */
function sharesHost(array $web, ?Closure $after = null, array $features = ['core', 'shell', 'session-ui']): array
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
        'kit' => ['app_id' => null, 'features' => $features, 'models' => ['user' => SharesUser::class]],
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
    foreach (glob(dirname(__DIR__).'/database/migrations/session-ui/*.php') as $file) {
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
$kitKeys = ['kitPassword', 'passkeyOffer', 'environment', 'kitTimezone', 'kitClock', 'shell', 'kitUi', 'kitToasts', 'kitNumber'];
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
    // Money and percent defaults: no currency is guessed (null until kit.currency is set), and the locale is the app locale as a BCP 47 tag, read per request.
    sharesCheck(($props['kitNumber'] ?? null) === ['currency' => null, 'locale' => str_replace('_', '-', $app->getLocale())], 'kitNumber shares no currency until kit.currency is set, and the app locale (has: '.json_encode($props['kitNumber'] ?? null).')');
    $app['config']->set('kit.currency', 'CAD');
    $app->setLocale('fr_CA');
    $props = sharesProps($dispatch(Request::create('https://kit.test/login', 'GET', server: $inertia)));
    sharesCheck(($props['kitNumber'] ?? null) === ['currency' => 'CAD', 'locale' => 'fr-CA'], 'kitNumber carries kit.currency and the app locale fr_CA as fr-CA (has: '.json_encode($props['kitNumber'] ?? null).')');
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

    // Inertia::flushShared() between requests, as pest-plugin-browser does before each one (and as an Octane-style reset or a host's own flush would):
    // the kit's shares are put back on every web request (PrepareKitRequest), so request 2 still has every kit prop, and the host's shares still win.
    [$app, $dispatch, [$ada, $grace]] = sharesHost([StartSession::class, HostHandleInertiaRequests::class]);
    $before = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $ada));
    Inertia::flushShared();
    $after = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $grace));
    sharesCheck($has($before, $kitKeys), 'flush: request 1 has every kit prop (has: '.implode(', ', array_keys($before)).')');
    sharesCheck($has($after, $kitKeys) && ($after['hostThing'] ?? null) === 'from the host' && is_array($after['kitPassword']) && is_array($after['kitUi']) && is_array($after['kitToasts']) && is_array($after['kitNumber']), 'flush: after Inertia::flushShared(), request 2 still has kitPassword, passkeyOffer, environment, shell, kitUi, kitToasts and kitNumber beside the host\'s hostThing (has: '.implode(', ', array_keys($after)).')');
    sharesCheck(($before['shell']['user']['name'] ?? null) === 'Ada Example' && ($after['shell']['user']['name'] ?? null) === 'Grace Example' && ($after['hostUser'] ?? null) === 'Grace Example', 'flush: Ada then Grace each see only their own person in the shell after the flush, not the other\'s');
    Inertia::flushShared();
    $guest = sharesProps($dispatch(Request::create('https://kit.test/login', 'GET', server: $inertia)));
    sharesCheck($has($guest, $kitKeys) && ($guest['shell']['kind'] ?? null) === 'guest' && ($guest['passkeyOffer'] ?? null) === null, 'flush: a signed-out request after another flush has a guest\'s shell, no passkey offer and every kit prop');

    // kitTimezone: the viewer's zone for timed values, from the browser's `tz` cookie when it is a real zone, else UTC (no saved or workspace zone here).
    $zoned = Request::create('https://kit.test/login', 'GET', cookies: ['tz' => 'America/Halifax'], server: $inertia);
    sharesCheck(($guest['kitTimezone'] ?? null) === 'UTC' && (sharesProps($dispatch($zoned))['kitTimezone'] ?? null) === 'America/Halifax' && (sharesProps($dispatch(Request::create('https://kit.test/login', 'GET', cookies: ['tz' => 'Not/AZone'], server: $inertia)))['kitTimezone'] ?? null) === 'UTC', 'kitTimezone is the browser\'s zone from the tz cookie when it is real, else UTC, and survives a flush');

    // kitClock: the person's own clock (user_state `clock`), then the workspace's (Registry::workspaceClockUsing, read per request), then kit.clock.default,
    // then auto. Anything but 12h or 24h at any level is no choice, and a workspace getter that throws falls back rather than failing the page.
    $clockOf = fn (?SharesUser $as = null): mixed => sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $as))['kitClock'] ?? null;
    sharesCheck(($guest['kitClock'] ?? null) === 'auto' && $clockOf($ada) === 'auto' && $clockOf() === 'auto', 'kitClock: with nothing set, a guest and a signed-in person get auto (the locale\'s own clock, no visible change)');
    $app['config']->set('kit.clock.default', 'nonsense');
    sharesCheck($clockOf($ada) === 'auto', 'kitClock: an unknown kit.clock.default is auto');
    $app['config']->set('kit.clock.default', '24h');
    sharesCheck($clockOf() === '24h' && $clockOf($ada) === '24h', 'kitClock: kit.clock.default 24h applies to a guest and a person with no choice');
    $workspaceClock = '12h';
    $saved = [];
    $app->make(Registry::class)->workspaceClockUsing(function () use (&$workspaceClock): ?string {
        if ($workspaceClock instanceof Throwable) {
            throw $workspaceClock;
        }

        return $workspaceClock;
    }, function (int $actorId, ?string $clock) use (&$saved): void {
        $saved[] = [$actorId, $clock];
    });
    sharesCheck($clockOf() === '12h' && $clockOf($ada) === '12h', 'kitClock: the workspace\'s 12h wins over kit.clock.default 24h');
    ClientClock::save((int) $ada->getKey(), '24h');
    sharesCheck($clockOf($ada) === '24h' && $clockOf($grace) === '12h' && $clockOf() === '12h', 'kitClock: Ada\'s own 24h wins over the workspace\'s 12h, and only for Ada (Grace and a guest keep the workspace\'s)');
    ClientClock::save((int) $grace->getKey(), '12h');
    $workspaceClock = '24h';
    sharesCheck($clockOf($grace) === '12h' && $clockOf($ada) === '24h', 'kitClock: Grace\'s own 12h wins over the workspace\'s 24h');
    $workspaceClock = null;
    $app['config']->set('kit.clock.default', 'auto');
    ClientClock::save((int) $grace->getKey(), null);
    sharesCheck($clockOf($grace) === 'auto' && DB::table('user_state')->where(['user_id' => $grace->getKey(), 'key' => 'clock'])->doesntExist(), 'kitClock: Grace back on the workspace\'s (none) and the default (auto) gets auto, and her row is gone');
    $workspaceClock = new RuntimeException('the workspace\'s settings are unreadable');
    $app['config']->set('kit.clock.default', '12h');
    sharesCheck($clockOf() === '12h' && $clockOf($ada) === '24h', 'kitClock: a workspace getter that throws falls back to kit.clock.default (the page still renders), and a person\'s own choice still wins');
    DB::table('user_state')->where(['user_id' => $ada->getKey(), 'key' => 'clock'])->update(['value' => 'auto']);
    $workspaceClock = 'h23';
    sharesCheck($clockOf($ada) === '12h', 'kitClock: a stored value that is not 12h or 24h (auto), and a workspace value that is not either (h23), are no choice');
    sharesCheck($saved === [] && $app->make(Registry::class)->workspaceClock() !== null, 'kitClock: resolving never calls the workspace setter');

    // Core's Clock: for() and current() answer 12h or 24h (auto through the app's locale), and time() and range() format server-built strings on them,
    // in each Carbon's own zone. for() takes a person and a workspace and reads no request; a given workspace is entered through Registry::inWorkspace().
    $app['config']->set('kit.clock.default', 'auto');
    $workspaceClock = null;
    $app['auth']->forgetGuards();
    $locales = [];
    foreach (['en', 'en_CA', 'en_GB', 'de', 'ko', 'es_MX'] as $locale) {
        $app->setLocale($locale);
        $locales[$locale] = Clock::for();
    }
    $app->setLocale('en');
    sharesCheck($locales === ['en' => '12h', 'en_CA' => '12h', 'en_GB' => '24h', 'de' => '24h', 'ko' => '12h', 'es_MX' => '24h'] && Clock::preference() === 'auto', 'Clock::for: auto resolves through the app locale (en, en_CA, ko 12h; en_GB, de, es_MX 24h), and preference() stays auto ('.json_encode($locales).')');
    ClientClock::save((int) $ada->getKey(), '24h');
    ClientClock::save((int) $grace->getKey(), '12h');
    Auth::guard('web')->setUser($grace);
    sharesCheck(Clock::current() === '12h' && Clock::for($ada) === '24h' && Clock::for((int) $ada->getKey()) === '24h' && Clock::for() === '12h', 'Clock::current is the signed-in person\'s (Grace, 12h); Clock::for(Ada) is Ada\'s 24h whoever is signed in, and for() with no one is the locale\'s (en, 12h)');
    $app['auth']->forgetGuards();
    sharesCheck(Clock::current() === '12h', 'Clock::current with no one signed in: the current workspace\'s (none), the default (auto), the locale\'s (en, 12h)');
    // A workspace given by id or model is read inside it: the app's getter sees that workspace as current, as Workspaces::run() makes it.
    $inside = null;
    $entered = [];
    $byWorkspace = [7 => '24h', 8 => null];
    $registry = $app->make(Registry::class);
    $registry->workspaceRunnerUsing(function (object|int $workspace, Closure $fn) use (&$inside, &$entered): mixed {
        $before = $inside;
        $inside = is_object($workspace) ? $workspace->id : $workspace;
        $entered[] = $inside;
        try {
            return $fn();
        } finally {
            $inside = $before;
        }
    });
    $registry->workspaceClockUsing(function () use (&$inside, &$workspaceClock, $byWorkspace): ?string {
        return $inside === null ? $workspaceClock : ($byWorkspace[$inside] ?? null);
    }, fn (): null => null);
    sharesCheck(Clock::for(null, 7) === '24h' && Clock::for(null, (object) ['id' => 7]) === '24h' && Clock::for(null, 8) === '12h' && Clock::for($grace, 7) === '12h' && $entered === [7, 7, 8, 7] && $inside === null, 'Clock::for(null, workspace) reads that workspace\'s clock inside it (7: 24h, by id or model; 8: none, so the locale\'s), a person\'s own still wins (Grace 12h in 7), and the runner restores what was current');
    $at = CarbonImmutable::parse('2026-10-29 18:30:00', 'UTC')->setTimezone('America/Halifax');
    $end = $at->addHours(8)->addMinutes(15);
    sharesCheck(Clock::time($at, $ada) === '15:30' && Clock::time($at, (int) $grace->getKey()) === '3:30 PM' && Clock::time($at->setTime(0, 5), $ada) === '00:05' && Clock::time($at->setTime(0, 5), $grace) === '12:05 AM' && Clock::time($at, null, 7) === '15:30', 'Clock::time: Ada (24h) 15:30 and 00:05, Grace (12h) 3:30 PM and 12:05 AM, workspace 7 (24h) 15:30, in the Carbon\'s own zone (Halifax)');
    $ranges = [Clock::range($at, $end, $ada), Clock::range($at, $end, $grace), Clock::range($at, $at->addHours(10), $ada), Clock::range($at, $end->setTimezone('UTC'), $grace)];
    sharesCheck($ranges === ['Thu 29 Oct, 15:30 to 23:45', 'Thu 29 Oct, 3:30 PM to 11:45 PM', 'Thu 29 Oct, 15:30 to Fri 30 Oct, 01:30', 'Thu 29 Oct, 3:30 PM to 11:45 PM'], 'Clock::range shares the date (Thu 29 Oct, 15:30 to 23:45 / 3:30 PM to 11:45 PM), names both days across midnight, and shows the end in the start\'s zone ('.json_encode($ranges).')');
    $app->setLocale('en_GB');
    sharesCheck(Clock::time($at) === '15:30' && Clock::time($at, $grace) === '3:30 PM', 'Clock::time on auto follows the app locale (en_GB: 15:30); an explicit 12h is still 12-hour there (3:30 PM)');
    $app->setLocale('ko');
    sharesCheck(Clock::time($at) === '오후 3:30' && Clock::time($at, $ada) === '15:30', 'Clock::time keeps the locale\'s own pattern when its clock is the one asked for (ko auto: 오후 3:30), and is plain 24-hour when it isn\'t');
    $app->setLocale('en');
    Auth::guard('web')->setUser($ada);
    sharesCheck(Clock::time($at) === '15:30', 'Clock::time with no person given uses the signed-in person (Ada, 24h)');
    $app['auth']->forgetGuards();
    $workspaceClock = '24h';
    sharesCheck(Clock::time($at) === '15:30' && Clock::time($at, 999) === '15:30', 'Clock::time for a job (no one signed in) or a person with no choice is the current workspace\'s (24h)');
    $workspaceClock = null;

    // A queued mail, rendered by the queue (the sync driver serializes the job and runs it), with Grace (12h) signed in where it was sent from: each mail
    // shows the clock of the person and workspace it was made for, not the request's. A mail with its own locale resolves auto through it.
    $transport = $app['mailer']->getSymfonyTransport();
    $transport->flush();
    Auth::guard('web')->setUser($grace);
    $start = '2026-06-02T13:00:00+00:00';
    $finish = '2026-06-02T15:00:00+00:00';
    Mail::to('ada@example.test')->queue(new ClockMail((int) $ada->getKey(), null, $start, $finish));
    Mail::to('someone@example.test')->queue(new ClockMail(null, 7, $start, $finish));
    Mail::to('someone@example.test')->queue(new ClockMail(null, 8, $start, $finish));
    Mail::to('someone@example.test')->queue((new ClockMail(null, 8, $start, $finish))->locale('en_GB'));
    $app['auth']->forgetGuards();
    $bodies = array_map(fn ($sent): string => trim(strip_tags((string) $sent->getOriginalMessage()->getHtmlBody())), $transport->messages()->all());
    sharesCheck($bodies === ['Tue 2 Jun, 10:00 to 12:00', 'Tue 2 Jun, 10:00 to 12:00', 'Tue 2 Jun, 10:00 AM to 12:00 PM', 'Tue 2 Jun, 10:00 to 12:00'] && ClockMail::$rendered === 4, 'queued mail: Ada\'s on her 24h, workspace 7\'s on its 24h, workspace 8\'s (no choice) on en\'s 12h and on en_GB\'s 24h with that locale, though Grace (12h) sent them all ('.json_encode($bodies).')');
    ClientClock::save((int) $ada->getKey(), null);
    ClientClock::save((int) $grace->getKey(), null);

    // Every page the kit shares to has kitClock, not only the signed-in shell: a public page on a host with only core and shell (no session-ui, so no
    // person's clock), after a flush too, and a page whose visitor is signed in on another guard (a portal), which gets the workspace's and the default.
    [$app, $dispatch] = sharesHost([StartSession::class, HostHandleInertiaRequests::class], features: ['core', 'shell']);
    $public = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia)));
    $app['config']->set('kit.clock.default', '24h');
    Inertia::flushShared();
    $flushed = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia)));
    sharesCheck(($public['kitClock'] ?? null) === 'auto' && ($flushed['kitClock'] ?? null) === '24h' && $app->make(Registry::class)->userClock() === null, 'kitClock on a public page of a core and shell host with no session-ui: auto, then kit.clock.default 24h after a flush');
    // A host with no feature listed (kit.features empty: FeaturesServiceProvider binds no Registry and registers nothing) still renders its pages,
    // and kitClock, core's share, is auto: the clock never needs a feature.
    [$app, $dispatch] = sharesHost([StartSession::class, HostHandleInertiaRequests::class], features: []);
    $bare = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia)));
    sharesCheck(! $app->bound(Registry::class) && ($bare['kitClock'] ?? null) === 'auto' && ($bare['hostThing'] ?? null) === 'from the host', 'kitClock on a host with no feature listed (no Registry bound): the page renders, and kitClock is auto');
    [$app, $dispatch, [$ada]] = sharesHost([StartSession::class, HostHandleInertiaRequests::class]);
    $app['config']->set('auth.guards.portal', ['driver' => 'session', 'provider' => 'users']);
    $app['config']->set('kit.clock.default', '12h');
    Route::middleware('web')->get('/portal', fn () => Inertia::render('Portal/Home'));
    $app['router']->getRoutes()->refreshNameLookups();
    $app['auth']->forgetGuards();
    $portal = Request::create('https://kit.test/portal', 'GET', server: $inertia);
    $portal->setLaravelSession($app['session']->driver());
    $app->instance('request', $portal);
    Auth::guard('portal')->setUser($ada);
    $portalProps = sharesProps($app['router']->dispatch($portal));
    sharesCheck(($portalProps['kitClock'] ?? null) === '12h', 'kitClock on a portal page (signed in on another guard): the default, 12h');

    // A host sharing a kit key itself (`environment`, `shell`) wins on every request, with and without a flush between them.
    foreach (['without a flush' => false, 'with a flush' => true] as $label => $flush) {
        [$app, $dispatch, [$ada]] = sharesHost([StartSession::class, HostOverridingInertiaRequests::class]);
        $pages = [];
        foreach ([1, 2] as $n) {
            if ($flush && $n === 2) {
                Inertia::flushShared();
            }
            $pages[$n] = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia), $ada));
        }
        foreach ($pages as $n => $page) {
            sharesCheck(($page['shell'] ?? null) === ['kind' => 'host shell'] && ($page['environment'] ?? null) === ['label' => 'host environment'] && $has($page, ['kitPassword', 'passkeyOffer', 'kitUi', 'kitToasts']), 'host precedence '.$label.': request '.$n.' has the host\'s own shell and environment, and the kit\'s other props');
        }
    }
    // A host share registered outside its middleware (a provider or a test, before the request) with a kit key also wins on the next request.
    [$app, $dispatch] = sharesHost([StartSession::class, HostHandleInertiaRequests::class]);
    Inertia::flushShared();
    Inertia::share('environment', fn (): array => ['label' => 'shared by the host']);
    $page = sharesProps($dispatch(Request::create('https://kit.test/probe', 'GET', server: $inertia)));
    sharesCheck(($page['environment'] ?? null) === ['label' => 'shared by the host'] && $has($page, $kitKeys), 'host precedence: a key the host shared on Inertia\'s factory before the request keeps the host\'s value, and the kit fills in the rest');

    // A URL that matches no route (a 404, or a 405 for a method the URI doesn't take) fails before the `web` group runs. Its error page still
    // knows the signed-in viewer: signed in through a real session cookie, never by setting the guard, so only the session can say who it is.
    [$app] = sharesHost([StartSession::class, ValidateCsrfToken::class, SubstituteBindings::class, HostHandleInertiaRequests::class], function (Application $app): void {
        // Not 'testing': under it Laravel skips the CSRF check, and this host must have one.
        $app->instance('env', 'local');
        $app->make(Registry::class)->errorPages(fn (string $code, int $status, ?string $message, Request $request) => Inertia::render('Workspace/Error/Page', ['status' => $status]));
        // Sessions on disk, so a fresh store per request finds the signed-in session by its cookie alone.
        $files = $app->storagePath('framework/sessions');
        mkdir($files, 0700, true);
        $app['config']->set(['session.driver' => 'file', 'session.files' => $files]);
    });
    Route::middleware('web')->get('/sign-in-as/{id}', function (string $id) {
        Auth::guard('web')->login(SharesUser::findOrFail((int) $id));

        return response('signed in');
    });
    Route::middleware('web')->put('/only-put', fn () => response('put'));
    $app['router']->getRoutes()->refreshNameLookups();
    // The kernel would bootstrap the app again (config from files): this host is already configured and booted.
    $app->bootstrapWith([]);
    $http = function (Request $request) use ($app): Response {
        // Every request gets fresh guards and a fresh session store, as in production: only the cookie carries the session over.
        $app['auth']->forgetGuards();
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        $app->instance('request', $request);
        $response = $app->make(Kernel::class)->handle($request);
        $app->make(Kernel::class)->terminate($request, $response);

        return $response;
    };
    $signIn = $http(Request::create('https://kit.test/sign-in-as/1'));
    $cookie = null;
    foreach ($signIn->headers->getCookies() as $set) {
        if ($set->getName() === 's') {
            $cookie = $set->getValue();
        }
    }
    sharesCheck($signIn->getStatusCode() === 200 && $cookie !== null, 'unmatched routes: the sign-in request sets the session cookie');
    foreach (['GET /no-such-page' => ['GET', '/no-such-page', 404], 'GET /only-put' => ['GET', '/only-put', 405]] as $label => [$method, $path, $status]) {
        $signedIn = $http(Request::create('https://kit.test'.$path, $method, cookies: ['s' => $cookie], server: $inertia));
        $guest = $http(Request::create('https://kit.test'.$path, $method, server: $inertia));
        $props = sharesProps($signedIn);
        sharesCheck($signedIn->getStatusCode() === $status && ($props['status'] ?? null) === $status && ($props['shell']['kind'] ?? null) !== null && ($props['shell']['kind'] ?? null) !== 'guest', 'unmatched routes: '.$label.' is the '.$status.' error page with the signed-in viewer\'s shell, not a guest\'s (got '.json_encode($props['shell']['kind'] ?? null).')');
        sharesCheck($guest->getStatusCode() === $status && (sharesProps($guest)['shell']['kind'] ?? null) === 'guest', 'unmatched routes: '.$label.' for a guest is still the '.$status.' error page with a guest\'s shell');
        $json = $http(Request::create('https://kit.test'.$path, $method, cookies: ['s' => $cookie], server: ['HTTP_ACCEPT' => 'application/json']));
        sharesCheck($json->getStatusCode() === $status && ! str_contains((string) $json->getContent(), '"component"'), 'unmatched routes: '.$label.' asked for JSON stays a JSON '.$status.', never a page');
    }
    Route::middleware('web')->post('/a-post', fn () => response('posted'));
    $app['router']->getRoutes()->refreshNameLookups();
    $forged = $http(Request::create('https://kit.test/a-post', 'POST', cookies: ['s' => $cookie], server: $inertia));
    sharesCheck($forged->getStatusCode() === 419, 'unmatched routes: this host checks CSRF (a matched POST without a token is a 419)');
    // A POST to a URL with no POST route carries no CSRF token: it is still the 405 page for the signed-in viewer, never a 419.
    $post = $http(Request::create('https://kit.test/only-put', 'POST', cookies: ['s' => $cookie], server: $inertia));
    $renews = fn (Response $response): bool => array_filter($response->headers->getCookies(), fn ($set) => $set->getName() === 's') !== [];
    sharesCheck($post->getStatusCode() === 405 && (sharesProps($post)['shell']['kind'] ?? null) !== 'guest' && $renews($post), 'unmatched routes: a POST with no CSRF token to a PUT-only URL is the 405 page with the signed-in shell, not a 419, and it went through the whole `web` group (the session cookie is renewed)');

    echo "Inertia shares: synthetic core, shell and session-ui hosts on SQLite memory with real Fortify and Inertia. No Octane worker, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
