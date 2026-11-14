<?php

// Signed-in devices (signed_in_devices, TrackSignedInDevice, DevicesController) on a synthetic app over HTTP: real Fortify sign-in (which regenerates),
// real Concierge, the kit's routes through the router with the `web` group, and each request run as a fresh one in the same process, as a long-lived
// worker runs them (guards, the session store and scoped instances forgotten between requests). Several browsers are cookie jars. Session drivers:
// database, file, memcached on a file cache store (the CacheBasedSessionHandler redis, valkey, memcached and dynamodb use) and cookie. SQLite files by default;
// DB_CONNECTION=pgsql runs in a schema of the PostgreSQL the DB_* variables name, created and dropped here. No network, no mail sent.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
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
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Deploy\HealthChecks;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Account\SignedInDevices;
use Tey\Kit\Features\SessionUi\BackfillDevicesCommand;
use Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Http\Middleware\TrackSignedInDevice;
use Tey\Kit\Features\SessionUi\Models\SignedInDevice;
use Tey\Kit\Features\SessionUi\Notifications\SecurityNotice;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const PASSWORD = 'a long synthetic passphrase';
const MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6723.92 Safari/537.36';
const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class DeviceUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(DeviceUser::class, 'App\Models\User');

/** A browser: its cookies, user agent, address and any headers its network adds. */
final class Browser
{
    /** @var array<string, string> */
    public array $cookies = [];

    /** @param  array<string, string>  $headers */
    public function __construct(public string $agent = MAC, public string $ip = '203.0.113.77', public array $headers = []) {}

    public function session(): ?string
    {
        return $this->cookies['s'] ?? null;
    }
}

/** The exception handler the app has, keeping what it reports (the kit's scrubbed reports among them). */
final class RecordingHandler extends Handler
{
    /** @var list<string> */
    public array $reported = [];

    public function report(Throwable $e): void
    {
        if ($this->shouldReport($e)) {
            $this->reported[] = $e::class.': '.$e->getMessage();
        }
    }
}

/** A cache store that is down: every read and write throws. */
final class DownStore implements Store
{
    public function get($key): mixed
    {
        throw new RuntimeException('connection refused');
    }

    public function many(array $keys): array
    {
        throw new RuntimeException('connection refused');
    }

    public function put($key, $value, $seconds): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function putMany(array $values, $seconds): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function increment($key, $value = 1): int|bool
    {
        throw new RuntimeException('connection refused');
    }

    public function decrement($key, $value = 1): int|bool
    {
        throw new RuntimeException('connection refused');
    }

    public function forever($key, $value): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function touch($key, $seconds): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function forget($key): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function flush(): bool
    {
        throw new RuntimeException('connection refused');
    }

    public function getPrefix(): string
    {
        return '';
    }
}

final class NoAccountPurge implements PurgesAccountData
{
    public function purge(Model $user): void {}
}

final class LaterAccountPurge implements DefersAccountPurge
{
    public function purge(Model $user): void
    {
        throw new RuntimeException('purge() ran in the deferred mode');
    }

    public function begin(Model $user): void {}
}

try {
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $schema = 'kit_devices_'.bin2hex(random_bytes(4));
    $base = sys_get_temp_dir().'/kit-signed-in-devices-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache', 'database'] as $dir) {
        mkdir($base.'/'.$dir, 0777, true);
    }
    touch($base.'/database/database.sqlite');
    // The app's namespace, which the framework reads from composer.json.
    file_put_contents($base.'/composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}');
    $pg = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'prefer'];
    $testing = $pgsql ? $pg + ['search_path' => $schema] : ['driver' => 'sqlite', 'database' => $base.'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true];
    $key = 'base64:'.base64_encode(random_bytes(32));
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, RecordingHandler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Northwind', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => $key, 'previous_keys' => [], 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => DeviceUser::class]], 'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]]],
        'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array'], 'files' => ['driver' => 'file', 'path' => $base.'/storage/framework/cache'], 'down' => ['driver' => 'down']], 'prefix' => ''],
        'session' => ['driver' => 'file', 'connection' => null, 'table' => 'sessions', 'store' => 'files', 'lifetime' => 120, 'files' => $base.'/storage/framework/sessions', 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => $testing]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => DeviceUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true], 'lifecycle' => ['account_delete' => true], 'devices' => ['touch_minutes' => 5, 'country_header' => null, 'store' => null]],
        'fortify' => ['guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'home' => '/', 'prefix' => '', 'domain' => null, 'middleware' => ['web'], 'limiters' => ['login' => null], 'views' => true, 'features' => [Features::updatePasswords()]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false], 'public' => ['driver' => 'local', 'root' => $base.'/storage/app/public', 'url' => 'https://kit.test/storage', 'visibility' => 'public', 'throw' => false]]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'platform@kit.test', 'name' => 'Northwind']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        FoundationServiceProvider::class, EventServiceProvider::class, PasswordResetServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class, SessionServiceProvider::class, ViewServiceProvider::class,
        TranslationServiceProvider::class, ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class, BusServiceProvider::class,
        HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class, MailServiceProvider::class, NotificationServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app['cache']->extend('down', fn () => $this->repository(new DownStore));
    // The password policy asks a breach service; here every password is unbreached, with no network.
    $app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data): bool
        {
            return true;
        }
    });
    $app->make(Registry::class);
    // The host's `web` group, as a host has it: queued cookies (the remember-me cookie, cookie sessions), the session, an Inertia middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [AddQueuedCookiesToResponse::class, StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    foreach (['auth' => Authenticate::class, 'guest' => RedirectIfAuthenticated::class, 'throttle' => ThrottleRequests::class, 'password.confirm' => RequirePassword::class] as $alias => $class) {
        $app['router']->aliasMiddleware($alias, $class);
    }
    // Probes: a public page, and one that regenerates the session id (as an invitation or the demo sign-in does).
    Route::middleware('web')->get('kit-probe', fn () => response()->json(['user' => auth('web')->id()]));
    Route::middleware(['web', 'auth:web'])->post('kit-probe/regenerate', function (Request $request) {
        $request->session()->regenerate();

        return response()->json(['regenerated' => true]);
    });
    $app['router']->getRoutes()->refreshNameLookups();

    if ($pgsql) {
        $config->set('database.connections.pg_admin', $pg + ['search_path' => 'public']);
        DB::connection('pg_admin')->statement('create schema '.$schema);
        register_shutdown_function(function () use ($schema): void {
            DB::disconnect('testing');
            DB::connection('pg_admin')->statement('drop schema if exists '.$schema.' cascade');
        });
    }
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });
    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    // Core's and workspaces' stubs first: Concierge's tables reference `workspaces`, which PostgreSQL (unlike SQLite) needs to exist already.
    foreach ($stubs as $name => $file) {
        if (strcmp($name, '0001_01_02_000300') < 0) {
            (require $file)->up();
            unset($stubs[$name]);
        }
    }
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    check(Schema::hasTable('signed_in_devices'), 'Database: '.($pgsql ? 'pgsql' : 'sqlite').'; the package migrations created signed_in_devices');

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $handler = $app->make(ExceptionHandler::class);
    $people = [];
    $person = function (string $name) use (&$people): DeviceUser {
        return $people[$name] = DeviceUser::create(['name' => ucfirst($name).' Example', 'email' => $name.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make(PASSWORD)]);
    };
    Carbon::setTestNow(Carbon::now()->startOfSecond());
    $travel = fn (int $seconds) => Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    // One request, as a fresh one in a long-lived worker: the guards, the session store and scoped instances forgotten first. The browser keeps the
    // cookies the response sets. [status, Location, decoded body, the response].
    $send = function (Browser $browser, string $method, string $path, array $data = []) use ($app): array {
        $app['auth']->forgetGuards();
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        $app['cookie']->flushQueuedCookies();
        $app->forgetScopedInstances();
        RequestMemo::instance()->flush();
        $server = ['REMOTE_ADDR' => $browser->ip, 'HTTP_USER_AGENT' => $browser->agent, 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        foreach ($browser->headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $request = Request::create('https://kit.test'.$path, $method, $data, $browser->cookies, [], $server);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        if ($response->getStatusCode() >= 500) {
            fwrite(STDERR, $method.' '.$path.' answered '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 600)."\n".implode("\n", $app->make(ExceptionHandler::class)->reported)."\n");
        }
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < Carbon::now()->getTimestamp()) {
                unset($browser->cookies[$cookie->getName()]);
            } else {
                $browser->cookies[$cookie->getName()] = (string) $cookie->getValue();
            }
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response->headers->get('Location'), json_decode((string) $response->getContent(), true), $response];
    };
    $signIn = function (Browser $browser, DeviceUser $user, bool $remember = false) use ($send): string {
        [$status, , $body] = $send($browser, 'POST', '/login', ['email' => $user->email, 'password' => PASSWORD] + ($remember ? ['remember' => 'on'] : []));
        if ($status !== 200) {
            throw new RuntimeException('signing '.$user->email.' in answered '.$status.' '.json_encode($body));
        }

        return (string) $browser->session();
    };
    $signedIn = fn (Browser $browser): bool => $send($browser, 'GET', '/me/profile')[0] === 200;
    $rows = fn (DeviceUser $user) => DB::table('signed_in_devices')->where('user_id', $user->id)->orderBy('id')->get();
    $rowOf = fn (string $sessionId): ?object => DB::table('signed_in_devices')->whereIn('session_hash', SignedInDevices::hashes($sessionId))->first();
    $signOut = fn (Browser $from, int $device, ?string $password = PASSWORD): array => $send($from, 'DELETE', '/me/security/devices/'.$device, $password === null ? [] : ['password' => $password]);
    // Whether the store still holds a session, per driver.
    $stored = function (string $driver, string $id) use ($base, $app): bool {
        return match ($driver) {
            'database' => DB::table('sessions')->where('id', $id)->exists(),
            'file' => is_file($base.'/storage/framework/sessions/'.$id),
            'memcached' => $app['cache']->store('files')->get($id) !== null,
        };
    };
    $driver = function (string $name) use ($config): void {
        $config->set('session.driver', $name);
    };
    $reported = function () use ($handler): array {
        $out = $handler->reported;
        $handler->reported = [];

        return $out;
    };

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $deviceQueries = fn (array $sql): array => array_values(array_filter($sql, fn (string $q): bool => str_contains($q, 'signed_in_devices')));

    // ── 1. Sign-in ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $ada = $person('ada');
    $mac = new Browser;
    $send($mac, 'GET', '/kit-probe');
    $before = (string) $mac->session();
    $queries = [];
    $final = $signIn($mac, $ada);
    $signInQueries = array_map(fn (string $sql): string => strtok(strtolower(ltrim($sql)), ' '), $deviceQueries($queries));
    $row = $rows($ada)->sole();
    check($signInQueries === ['insert', 'select'], 'the sign-in request writes the row once, at its end: one insert and one select, and no update (a row written in the Login listener, under the id before the regenerate, would have to move)'.json_encode($signInQueries));
    check($before !== '' && $final !== $before && $row->session_hash === SignedInDevices::hash($final) && $rowOf($before) === null, 'a Fortify sign-in (which regenerates) writes exactly one row, keyed by the final session id; none by the id before it, so it is not written in the Login listener');
    check($row->browser === 'Chrome' && $row->platform === 'macOS' && $row->ip_prefix === '203.0.113.0/24' && (bool) $row->remembered === false && $row->country === null, 'the browser and platform are parsed (Chrome, macOS) and the IPv4 address is kept as its /24');
    $values = implode("\n", array_map('strval', (array) $row));
    check(! str_contains($values, '203.0.113.77') && ! str_contains($values, MAC) && ! str_contains($values, '130.0') && ! str_contains($values, $final) && Crypt::decryptString($row->session_id) === $final, 'the raw IP, the user agent (or its version) and the session id appear in no column; the session id is stored encrypted');
    $phone = new Browser(IPHONE, '2001:db8:abcd:12:34::1', ['CF-IPCountry' => 'CA']);
    $signIn($phone, $ada, remember: true);
    $row = $rowOf((string) $phone->session());
    check($row->browser === 'Safari' && $row->platform === 'iOS' && $row->ip_prefix === '2001:db8:abcd::/48' && (bool) $row->remembered === true && $row->country === null, 'an IPv6 address is kept as its /48, a remember-me sign-in is marked remembered, and with no country header named the country is not stored');
    $config->set('kit.devices.country_header', 'CF-IPCountry');
    $countries = [];
    foreach (['NL', 'XX', 'nl', 'NLD'] as $code) {
        $browser = new Browser(WINDOWS, '198.51.100.20', ['CF-IPCountry' => $code]);
        $signIn($browser, $ada);
        $countries[$code] = $rowOf((string) $browser->session())->country;
    }
    $config->set('kit.devices.country_header', null);
    check($countries === ['NL' => 'NL', 'XX' => null, 'nl' => null, 'NLD' => null], 'with CF-IPCountry named, only a two-letter code is stored (not XX, lower case or three letters)'.json_encode($countries));
    check(SignedInDevices::ipPrefix('::ffff:192.0.2.130') === '192.0.2.0/24' && SignedInDevices::ipPrefix('not an ip') === null && SignedInDevices::ipPrefix(null) === null, 'an IPv4-mapped IPv6 address is kept as its IPv4 /24; anything else is no prefix');
    foreach ([['country_header' => 'X-Country'], ['touch_minutes' => 0], ['touch_minutes' => '5'], ['sweep' => true]] as $bad) {
        $config->set('kit.devices', $bad + ['touch_minutes' => 5, 'country_header' => null, 'store' => null]);
        $refused = false;
        try {
            SignedInDevices::validate();
        } catch (RuntimeException) {
            $refused = true;
        }
        check($refused, 'kit.devices '.json_encode($bad).' stops the boot');
    }
    $config->set('kit.devices', ['touch_minutes' => 5, 'country_header' => null, 'store' => null]);

    // ── 2. Zero queries ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $zara = $person('zara');
    $desk = new Browser;
    $signIn($desk, $zara);
    $signedAt = Carbon::now();
    $queries = [];
    $total = 0;
    for ($i = 0; $i < 20; $i++) {
        $queries = [];
        [$status] = $send($desk, 'GET', '/me/profile');
        if ($status !== 200) {
            throw new RuntimeException('page load '.$i.' answered '.$status);
        }
        $total += count($deviceQueries($queries));
    }
    check($total === 0, 'twenty page loads inside N minutes run no query against signed_in_devices');
    $queries = [];
    $send($desk, 'GET', '/me/profile');
    $with = $queries;
    $group = $app['router']->getMiddlewareGroups()['web'];
    $app['router']->middlewareGroup('web', array_values(array_filter($group, fn ($middleware): bool => $middleware !== TrackSignedInDevice::class)));
    $queries = [];
    $send($desk, 'GET', '/me/profile');
    $without = $queries;
    $app['router']->middlewareGroup('web', $group);
    check(count($with) === count($without) && $with === $without, 'the page runs the same queries ('.count($with).') as the same app with the middleware off');
    Carbon::setTestNow($signedAt->copy()->addSeconds(5 * 60 - 1));
    $queries = [];
    $send($desk, 'GET', '/me/profile');
    check($deviceQueries($queries) === [], 'at N minutes less a second there is no update');
    Carbon::setTestNow($signedAt->copy()->addSeconds(5 * 60));
    $queries = [];
    $send($desk, 'GET', '/me/profile');
    $touches = $deviceQueries($queries);
    check(count($touches) === 1 && str_starts_with(strtolower($touches[0]), 'update') && Carbon::parse($rowOf((string) $desk->session())->last_seen_at)->equalTo(Carbon::now()), 'at N minutes there is exactly one, an update of last_seen_at');
    $queries = [];
    $send($desk, 'GET', '/me/profile');
    check($deviceQueries($queries) === [], 'and the next page inside N minutes of that touch runs none again');
    $queries = [];
    $send(new Browser, 'GET', '/kit-probe');
    check($deviceQueries($queries) === [] && preg_grep('/"users"/', $queries) === [], 'a guest\'s page runs no query against signed_in_devices or users');
    // Stale writes: last_seen_at only goes forward, whatever a request carries.
    $deskRow = $rowOf((string) $desk->session());
    $later = Carbon::parse($deskRow->last_seen_at)->addMinutes(30);
    DB::table('signed_in_devices')->where('id', $deskRow->id)->update(['last_seen_at' => $later]);
    $travel(5 * 60);
    $send($desk, 'GET', '/me/profile');
    check(Carbon::parse($rowOf((string) $desk->session())->last_seen_at)->equalTo($later) && $signedIn($desk), 'a touch never moves last_seen_at back (a later one is kept), and the device stays signed in');

    // ── 3. Regenerate ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $rex = $person('rex');
    $laptop = new Browser;
    $old = $signIn($laptop, $rex);
    $deviceId = $rowOf($old)->id;
    $stale = new Browser;
    $stale->cookies = $laptop->cookies;
    $send($laptop, 'POST', '/kit-probe/regenerate');
    $new = (string) $laptop->session();
    check($new !== $old && $rows($rex)->count() === 1 && $rowOf($new)?->id === $deviceId && $rowOf($old) === null, 'a mid-session regenerate() moves the row to the new id by compare-and-swap, with no second row');
    check(file_exists($base.'/storage/framework/sessions/'.$old) && ! $signedIn($stale) && $rowOf($new)?->id === $deviceId, 'a request still carrying the old id (its payload still in the store) is signed out instead of moving the row back');
    check($signedIn($laptop), 'and the regenerated session stays signed in');
    // With the cache store down, the old id is still refused: the row in the database decides.
    $stale->cookies = ['s' => $new];
    $send($laptop, 'POST', '/kit-probe/regenerate');
    $config->set('kit.devices.store', 'down');
    check(! $signedIn($stale) && $signedIn($laptop), 'with the cache store down, a request on a retired id is still signed out (the row decides), and the current one is not');
    $down = $reported();
    $config->set('kit.devices.store', null);
    check($down !== [] && ! str_contains(implode(' ', $down), $new) && str_contains(implode(' ', $down), 'could not read the cache store'), 'the store\'s failure is reported scrubbed, without the session id');

    // ── 4. Remote sign-out per driver, and 6. ownership ──────────────────────────────────────────────────────────────────────────────────────────────
    foreach (['database', 'file', 'memcached'] as $name) {
        $driver($name);
        $owner = $person('owner-'.$name);
        $a = new Browser;
        $b = new Browser(IPHONE, '198.51.100.7');
        $signIn($a, $owner);
        $bId = $signIn($b, $owner);
        check($stored($name, $bId) && $signedIn($b), $name.': B is signed in, its session in the store');
        $device = (int) $rowOf($bId)->id;
        [$status, , $body] = $signOut($a, $device);
        check($status === 200 && ($body['at_once'] ?? null) === true, $name.': A signs B out with the password');
        check(! $stored($name, $bId) && $rowOf($bId) === null, $name.': B\'s session is gone from the store and its row is gone');
        check(! $signedIn($b), $name.': B\'s next request is a guest\'s');
        check($signedIn($a), $name.': A stays signed in');
    }
    $driver('file');
    $notices = $reported();
    check($notices === [], 'nothing was reported'.json_encode($notices));

    // The cookie driver: the handler can't reach B's cookie, so it is skipped; B is cut off on its next request by the revocation marker.
    $driver('cookie');
    $cookieOwner = $person('cookie-owner');
    $a = new Browser;
    $b = new Browser(IPHONE, '198.51.100.7');
    $signIn($a, $cookieOwner);
    $bId = $signIn($b, $cookieOwner);
    check($signedIn($b) && isset($b->cookies[$bId]), 'cookie: B is signed in, its session in its own cookie');
    [$status, , $body] = $signOut($a, (int) $rowOf($bId)->id);
    check($status === 200 && $body['at_once'] === true && $signedIn($a), 'cookie: A signs B out (the handler call skipped, so this response queues no cookie for B), and A stays signed in');
    check(! $signedIn($b) && $rowOf($bId) === null, 'cookie: B is signed out on its next request, and its row is gone');
    // The marker could not be written (the cache store down during the sign-out, back since): B is still signed in before N minutes, and out at the first touch after.
    $c = new Browser(WINDOWS, '198.51.100.9');
    $cId = $signIn($c, $cookieOwner);
    $cAt = Carbon::now();
    $config->set('kit.devices.store', 'down');
    [$status, , $body] = $signOut($a, (int) $rowOf($cId)->id);
    $config->set('kit.devices.store', null);
    $downAtSignOut = $reported();
    check($status === 200 && $body['at_once'] === false && $downAtSignOut !== [], 'cookie with the store down at the sign-out: the row is deleted, the person told it ends within N minutes, the failure reported');
    $page = $send($a, 'GET', '/me/security')[2]['props'] ?? [];
    check(str_contains((string) ($page['notice'] ?? ''), 'will be signed out within 5 minutes'), 'and the page says "That device will be signed out within 5 minutes."');
    Carbon::setTestNow($cAt->copy()->addSeconds(5 * 60 - 1));
    check($signedIn($c), 'cookie: before N minutes, C is still signed in (its marker never reached the store)');
    Carbon::setTestNow($cAt->copy()->addSeconds(5 * 60));
    check(! $signedIn($c) && $rowOf($cId) === null, 'cookie: at the first touch after, C is signed out, and never given a new row');
    $driver('file');

    // Ownership: only the signed-in person's own devices, by id.
    $xena = $person('xena');
    $yuri = $person('yuri');
    $xBrowser = new Browser;
    $yBrowser = new Browser(IPHONE, '198.51.100.30');
    $signIn($xBrowser, $xena);
    $yId = $signIn($yBrowser, $yuri);
    $yDevice = (int) $rowOf($yId)->id;
    [$status] = $signOut($xBrowser, $yDevice);
    check($status === 404 && $rowOf($yId) !== null && $signedIn($yBrowser), 'X signing out Y\'s device id gets 404, with Y\'s row and session intact');
    check($signOut($xBrowser, 999999)[0] === 404, 'a device id that does not exist gets the same 404');
    check($send($xBrowser, 'DELETE', '/me/security/devices/abc', ['password' => PASSWORD])[0] === 404, 'a non-numeric id gets 404');
    $xCurrent = (int) $rowOf((string) $xBrowser->session())->id;
    [$status, , $body] = $signOut($xBrowser, $xCurrent);
    check($status === 422 && isset($body['errors']['device']) && $rowOf((string) $xBrowser->session()) !== null && $signedIn($xBrowser), 'the current device gets 422 ("Use Sign out on this device"), and stays signed in');
    $xOther = new Browser(WINDOWS, '198.51.100.31');
    $xOtherId = $signIn($xOther, $xena);
    [$status, , $body] = $signOut($xBrowser, (int) $rowOf($xOtherId)->id, 'not the password');
    check($status === 422 && isset($body['errors']['password']) && $rowOf($xOtherId) !== null && $signedIn($xOther), 'a wrong password is refused on its field, and nothing is signed out');
    [$status, , $body] = $signOut($xBrowser, (int) $rowOf($xOtherId)->id, null);
    check($status === 422 && isset($body['errors']['password']) && $rowOf($xOtherId) !== null, 'no password is refused too');
    check($signOut(new Browser, $yDevice)[0] === 401 && $rowOf($yId) !== null, 'a guest gets 401');
    $throttled = $person('throttled');
    $tBrowser = new Browser;
    $signIn($tBrowser, $throttled);
    $codes = [];
    for ($i = 0; $i < 11; $i++) {
        $codes[] = $signOut($tBrowser, 999999)[0];
    }
    check(array_slice($codes, 0, 10) === array_fill(0, 10, 404) && $codes[10] === 429, 'the route is throttled: the eleventh attempt in a minute gets 429');
    // Tenant and grant: a workspace's owner can neither list nor end a member's devices; no workspace route reaches them.
    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($workspace): void {
        foreach (app(Registry::class)->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    foreach (['xena' => 'owner', 'yuri' => 'member'] as $who => $role) {
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$who]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $people[$who]->assignRole($role), [$people[$who]]);
    }
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $sync->run(new ArrayInput([]), new NullOutput);
    $listed = array_column($send($xBrowser, 'GET', '/me/security')[2]['props']['sessions']['items'] ?? [], 'id');
    check($listed !== [] && ! in_array($yDevice, $listed, true) && $signOut($xBrowser, $yDevice)[0] === 404 && $rowOf($yId) !== null, 'the workspace\'s owner lists only their own devices and gets 404 for a member\'s, whose session stays');
    $deviceRoutes = array_values(array_filter(array_map(fn ($route) => $route->uri(), $app['router']->getRoutes()->getRoutes()), fn (string $uri): bool => str_contains($uri, 'devices')));
    check($deviceRoutes === ['me/security/devices/{device}'], 'the one route to a device is the person\'s own (no workspace, operator or admin route)'.json_encode($deviceRoutes));
    check(! in_array('workspace_id', Schema::getColumnListing('signed_in_devices'), true), 'and the table has no workspace column');

    // Another account signed in on the same session never adopts the row: the touch fails and signs out.
    $shared = new Browser;
    $sharedId = $signIn($shared, $xena);
    $sessionFile = $base.'/storage/framework/sessions/'.$sharedId;
    $payload = unserialize((string) file_get_contents($sessionFile));
    $payload[$app['auth']->guard('web')->getName()] = $yuri->id;
    file_put_contents($sessionFile, serialize($payload));
    check(! $signedIn($shared) && $rowOf($sharedId)?->user_id == $xena->id && $rows($yuri)->count() === 1, 'a session whose row belongs to another account signs out rather than adopting the row');

    // ── 5. Already gone ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    foreach (['file', 'memcached'] as $name) {
        $driver($name);
        $gone = $person('gone-'.$name);
        $a = new Browser;
        $b = new Browser(IPHONE, '198.51.100.40');
        $signIn($a, $gone);
        $bId = $signIn($b, $gone);
        $name === 'file' ? unlink($base.'/storage/framework/sessions/'.$bId) : $app['cache']->store('files')->forget($bId);
        $device = (int) $rowOf($bId)->id;
        [$status, , $body] = $signOut($a, $device);
        check($status === 200 && $body['signed_out'] === true && $rowOf($bId) === null && $reported() === [], $name.': B\'s session already gone from the store: signing it out succeeds, the row is deleted, nothing is reported');
        [$status] = $signOut($a, $device);
        $page = $send($a, 'GET', '/me/security')[2]['props'] ?? [];
        check($status === 404 && ($page['notice'] ?? null) === 'That device was already signed out.', $name.': the row already deleted gives 404, then the notice "That device was already signed out."');
    }
    $driver('file');

    // ── 7. The resurrection race ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $race = $person('race');
    $a = new Browser;
    $b = new Browser(IPHONE, '198.51.100.50');
    $signIn($a, $race);
    $bId = $signIn($b, $race);
    $bAt = Carbon::now();
    $inFlight = (string) file_get_contents($base.'/storage/framework/sessions/'.$bId);
    $signOut($a, (int) $rowOf($bId)->id);
    // B's request that was in flight finishes, and writes its session back.
    file_put_contents($base.'/storage/framework/sessions/'.$bId, $inFlight);
    $count = DB::table('signed_in_devices')->count();
    check(! $signedIn($b) && DB::table('signed_in_devices')->count() === $count && $rowOf($bId) === null, 'B\'s session written back after the destroy is signed out on its next request, and given no new row');
    // The same with its marker gone (the cache flushed): still signed in before N minutes, out at the touch, and never a new row.
    $c = new Browser(WINDOWS, '198.51.100.51');
    $cId = $signIn($c, $race);
    $cAt = Carbon::now();
    $inFlight = (string) file_get_contents($base.'/storage/framework/sessions/'.$cId);
    $signOut($a, (int) $rowOf($cId)->id);
    file_put_contents($base.'/storage/framework/sessions/'.$cId, $inFlight);
    $app['cache']->store('array')->forget('kit.devices.revoked.'.SignedInDevices::hash($cId));
    Carbon::setTestNow($cAt->copy()->addSeconds(5 * 60 - 1));
    check($signedIn($c) && $rowOf($cId) === null, 'with its marker gone, a written-back session is still signed in before N minutes');
    Carbon::setTestNow($cAt->copy()->addSeconds(5 * 60));
    check(! $signedIn($c) && $rowOf($cId) === null && DB::table('signed_in_devices')->count() === $count, 'and signed out at its touch, never given a new row');
    unset($bAt);

    // ── 8. Concurrency ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $twin = $person('twin');
    $sessionId = bin2hex(random_bytes(20));
    $guardKey = $app['auth']->guard('web')->getName();
    $legacy = serialize(['_token' => str_repeat('t', 40), $guardKey => $twin->id]);
    file_put_contents($base.'/storage/framework/sessions/'.$sessionId, $legacy);
    $first = new Browser;
    $first->cookies = ['s' => $sessionId];
    $second = new Browser;
    $second->cookies = ['s' => $sessionId];
    check($signedIn($first), 'a first request of a session with no row');
    // The second request read the session before the first saved it.
    file_put_contents($base.'/storage/framework/sessions/'.$sessionId, $legacy);
    check($signedIn($second) && $rows($twin)->count() === 1, 'two first requests of one session produce one row');
    check(SignedInDevices::track(Request::create('/'), $sessionId, $twin->id, false) === (int) $rows($twin)->sole()->id && $rows($twin)->count() === 1, 'the unique session hash dedupes: a third insert finds the same row');

    // ── 11. Adoption ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $pre = $person('pre');
    $preId = bin2hex(random_bytes(20));
    file_put_contents($base.'/storage/framework/sessions/'.$preId, serialize(['_token' => str_repeat('p', 40), $guardKey => $pre->id]));
    $preBrowser = new Browser(WINDOWS, '192.0.2.44');
    $preBrowser->cookies = ['s' => $preId];
    check($rows($pre)->count() === 0 && $signedIn($preBrowser) && $rowOf($preId)?->browser === 'Firefox' && $rowOf($preId)?->ip_prefix === '192.0.2.0/24', 'a session from before the migration gets its row on its next request');
    $driver('database');
    $backfill = function () use ($app): array {
        $command = new BackfillDevicesCommand;
        $command->setLaravel($app);
        $output = new BufferedOutput;

        return [$command->run(new ArrayInput([]), $output), $output->fetch()];
    };
    $old1 = $person('old-one');
    $old2 = $person('old-two');
    $now = Carbon::now()->getTimestamp();
    $legacyRows = [
        ['id' => str_repeat('a', 40), 'user_id' => $old1->id, 'ip_address' => '203.0.113.200', 'user_agent' => MAC, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 60],
        ['id' => str_repeat('b', 40), 'user_id' => $old1->id, 'ip_address' => '2001:db8:1234:5678::9', 'user_agent' => IPHONE, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 600],
        ['id' => str_repeat('c', 40), 'user_id' => $old2->id, 'ip_address' => null, 'user_agent' => null, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 30],
        // Expired, a guest's, and one of an account that no longer exists: no row.
        ['id' => str_repeat('d', 40), 'user_id' => $old2->id, 'ip_address' => '203.0.113.201', 'user_agent' => MAC, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 121 * 60],
        ['id' => str_repeat('e', 40), 'user_id' => null, 'ip_address' => '203.0.113.202', 'user_agent' => MAC, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 10],
        ['id' => str_repeat('f', 40), 'user_id' => 999999, 'ip_address' => '203.0.113.203', 'user_agent' => MAC, 'payload' => base64_encode(serialize([])), 'last_activity' => $now - 10],
    ];
    // Only these sessions: the database driver's earlier sessions (the sign-out checks above) are cleared first.
    DB::table('sessions')->delete();
    DB::table('sessions')->insert($legacyRows);
    $sessionsBefore = DB::table('sessions')->orderBy('id')->get()->all();
    [$code, $out] = $backfill();
    $adopted = fn (string $id): ?object => $rowOf($id);
    check($code === 0 && $adopted(str_repeat('a', 40))?->ip_prefix === '203.0.113.0/24' && $adopted(str_repeat('a', 40))?->browser === 'Chrome' && $adopted(str_repeat('b', 40))?->ip_prefix === '2001:db8:1234::/48' && $adopted(str_repeat('c', 40))?->browser === null, 'kit:devices:backfill on the database driver inserts a row for each signed-in session, the agent parsed and the IP cut to its prefix');
    check($adopted(str_repeat('d', 40)) === null && $adopted(str_repeat('e', 40)) === null && $adopted(str_repeat('f', 40)) === null && str_contains($out, '3 added for 3'), 'and none for an expired session, a guest\'s or a deleted account\'s ('.trim($out).')');
    check(Carbon::parse($adopted(str_repeat('b', 40))->last_seen_at)->getTimestamp() === $now - 600 && DB::table('sessions')->orderBy('id')->get()->all() == $sessionsBefore, 'its last-seen time is the session\'s last activity, and the sessions table is unchanged');
    $count = DB::table('signed_in_devices')->count();
    [$code, $out] = $backfill();
    check($code === 0 && DB::table('signed_in_devices')->count() === $count && str_contains($out, '0 added'), 'a second run adds nothing ('.trim($out).')');
    $driver('file');
    [$code, $out] = $backfill();
    check($code === 1 && str_contains($out, 'SESSION_DRIVER is file') && DB::table('signed_in_devices')->count() === $count, 'on another driver it refuses, nonzero, and adds nothing');
    DB::table('sessions')->delete();

    // ── 10. Remember-me ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $rem = $person('rem');
    $a = new Browser;
    $b = new Browser(IPHONE, '198.51.100.60');
    $signIn($a, $rem);
    $bId = $signIn($b, $rem, remember: true);
    $recallerName = $app['auth']->guard('web')->getRecallerName();
    $recaller = $b->cookies[$recallerName] ?? null;
    $fromRecaller = new Browser(IPHONE, '198.51.100.60');
    $fromRecaller->cookies = [$recallerName => (string) $recaller];
    check($recaller !== null && $signedIn($fromRecaller) && (bool) $rowOf((string) $fromRecaller->session())->remembered === true, 'control: B\'s recaller cookie alone signs in (a new session, its row remembered)');
    $tokenBefore = DeviceUser::query()->findOrFail($rem->id)->remember_token;
    [$status, , $body] = $signOut($a, (int) $rowOf($bId)->id);
    check($status === 200 && DeviceUser::query()->findOrFail($rem->id)->remember_token !== $tokenBefore, 'signing out a remembered device cycles the remember-me token');
    $page = $send($a, 'GET', '/me/security')[2]['props'] ?? [];
    check(str_contains((string) ($page['notice'] ?? ''), 'your other devices will ask you to sign in again next time'), 'and the page says the person\'s other devices will sign in again next time');
    $again = new Browser(IPHONE, '198.51.100.60');
    $again->cookies = [$recallerName => (string) $recaller];
    check(! $signedIn($again), 'B\'s recaller cookie no longer signs in');

    // ── 9. Ends everywhere ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $leave = $person('leave');
    $leaving = new Browser;
    $leavingId = $signIn($leaving, $leave);
    check($rowOf($leavingId) !== null && $send($leaving, 'POST', '/logout')[0] < 400 && $rowOf($leavingId) === null && ! $signedIn($leaving), 'Sign out deletes this device\'s row');
    $app->instance(PurgesAccountData::class, new NoAccountPurge);
    $ends = [
        'Sign out other sessions' => [fn (Browser $a) => $send($a, 'DELETE', '/me/security/sessions', ['password' => PASSWORD]), true],
        'a password change' => [fn (Browser $a) => $send($a, 'PUT', '/user/password', ['current_password' => PASSWORD, 'password' => 'another long passphrase', 'password_confirmation' => 'another long passphrase']), true],
        'an immediate account deletion' => [fn (Browser $a) => $send($a, 'DELETE', '/me/delete', ['password' => PASSWORD]), false],
        'a deferred account deletion' => [function (Browser $a) use ($send, $app) {
            $app->instance(PurgesAccountData::class, new LaterAccountPurge);
            try {
                return $send($a, 'DELETE', '/me/delete', ['password' => PASSWORD]);
            } finally {
                $app->instance(PurgesAccountData::class, new NoAccountPurge);
            }
        }, false],
        'an anonymised account deletion' => [function (Browser $a) use ($send, $config) {
            $config->set('kit.lifecycle.account_user_row', 'anonymise');
            try {
                return $send($a, 'DELETE', '/me/delete', ['password' => PASSWORD]);
            } finally {
                $config->set('kit.lifecycle.account_user_row', 'delete');
            }
        }, false],
    ];
    foreach (['file', 'memcached'] as $name) {
        foreach ($ends as $what => [$act, $keepsThis]) {
            $driver($name);
            $user = $person(substr(md5($what.$name), 0, 10));
            $a = new Browser;
            $others = [new Browser(IPHONE, '198.51.100.70'), new Browser(WINDOWS, '198.51.100.71')];
            $aId = $signIn($a, $user);
            $ids = array_map(fn (Browser $other): string => $signIn($other, $user), $others);
            [$status, , $body] = $act($a);
            $gone = array_filter($ids, fn (string $id): bool => ! $stored($name, $id) && $rowOf($id) === null) === $ids;
            check($status < 400 && $gone && array_filter($others, $signedIn) === [], $name.': '.$what.' destroys every other session in the store and deletes its row ('.$status.' '.json_encode($body).')');
            check($keepsThis ? ($signedIn($a) && $rowOf($aId) !== null) : (! $signedIn($a) && DB::table('signed_in_devices')->where('user_id', $user->id)->doesntExist()), $name.': '.$what.($keepsThis ? ' keeps this device' : ' ends this device too, and leaves no row'));
        }
    }
    $driver('file');
    // FK cascade on a hard delete, and the model listener where keys can't apply.
    $hard = $person('hard');
    $signIn(new Browser, $hard);
    check($rows($hard)->count() === 1, 'a person with a device');
    DB::table('users')->where('id', $hard->id)->delete();
    check($rows($hard)->count() === 0, 'a hard delete of the user row cascades to their devices (the foreign key)');
    $soft = $person('soft');
    $signIn(new Browser, $soft);
    $soft->delete();
    check($rows($soft)->count() === 0, 'deleting the user model deletes their devices too (SecurityLog::forget)');
    // /up lists the table while session-ui is on, and a deletion fails closed without it.
    Schema::rename('signed_in_devices', 'signed_in_devices_away');
    try {
        $health = (new HealthChecks(fn (): array => [], ['core', 'session-ui']))->failures();
        $healthOff = (new HealthChecks(fn (): array => [], ['core']))->failures();
        check(in_array('the table signed_in_devices is missing', $health, true) && ! in_array('the table signed_in_devices is missing', $healthOff, true), '/up names signed_in_devices when it is missing while session-ui is on, and not without session-ui');
    } finally {
        Schema::rename('signed_in_devices_away', 'signed_in_devices');
    }

    // ── 12. Retention ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $ret = $person('ret');
    $retBrowser = new Browser;
    $retId = $signIn($retBrowser, $ret);
    $insert = fn (string $sid, Carbon $seen): int => (int) DB::table('signed_in_devices')->insertGetId(['user_id' => $ret->id, 'session_hash' => SignedInDevices::hash($sid), 'session_id' => Crypt::encryptString($sid), 'browser' => 'Firefox', 'platform' => 'Linux', 'ip_prefix' => null, 'country' => null, 'remembered' => false, 'created_at' => $seen, 'last_seen_at' => $seen]);
    $justLive = $insert('just-live-'.bin2hex(random_bytes(8)), Carbon::now()->subMinutes(120 + 5)->addSecond());
    $stale = $insert('stale-'.bin2hex(random_bytes(8)), Carbon::now()->subMinutes(120 + 5)->subSecond());
    $listed = array_column($send($retBrowser, 'GET', '/me/security')[2]['props']['sessions']['items'] ?? [], 'id');
    check(in_array($justLive, $listed, true) && ! in_array($stale, $listed, true) && in_array((int) $rowOf($retId)->id, $listed, true), 'a row last seen past the session lifetime plus N is hidden from the list; one just inside it is listed');
    $prune = new PruneCommand;
    $prune->setLaravel($app);
    $prune->run(new ArrayInput(['--model' => [SignedInDevice::class]]), new NullOutput);
    check(DB::table('signed_in_devices')->where('id', $stale)->doesntExist() && DB::table('signed_in_devices')->where('id', $justLive)->exists() && $rowOf($retId) !== null, 'model:prune deletes the stale row and keeps the live ones');
    $items = $send($retBrowser, 'GET', '/me/security')[2]['props']['sessions']['items'];
    $current = array_values(array_filter($items, fn (array $item): bool => $item['current']));
    check(count($current) === 1 && $current[0]['url'] === null && $current[0]['agent'] === 'Chrome on macOS' && $current[0]['ip'] === '203.0.113.0/24' && array_keys($current[0]) === ['agent', 'ip', 'last_active', 'current', 'id', 'signed_in_at', 'country', 'remembered', 'url'], 'the list keeps its items\' keys (agent, ip, last_active, current) and adds id, signed_in_at, country, remembered and url; this device has no url');
    check(array_filter($items, fn (array $item): bool => ! $item['current'] && $item['url'] !== 'https://kit.test/me/security/devices/'.$item['id']) === [] && ! str_contains(json_encode($items), SignedInDevices::hash($retId)) && ! str_contains(json_encode($items), $retId), 'every other device has its sign-out url, and no item carries a session id or its hash');

    // ── 13. Encryption ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    $rot = $person('rot');
    $a = new Browser;
    $b = new Browser(IPHONE, '198.51.100.80');
    $signIn($a, $rot);
    $bId = $signIn($b, $rot);
    $rotate = function (string $next) use ($config, $app): void {
        $config->set('app.previous_keys', [$config->get('app.key')]);
        $config->set('app.key', $next);
        $app->forgetInstance('encrypter');
        Facade::clearResolvedInstance('encrypter');
    };
    $rotate('base64:'.base64_encode(random_bytes(32)));
    check($signedIn($a) && $signedIn($b), 'after an APP_PREVIOUS_KEYS rotation both devices stay signed in (their rows found by the previous key\'s HMAC)');
    [$status, , $body] = $signOut($a, (int) $rowOf($bId)->id);
    check($status === 200 && $body['at_once'] === true && ! is_file($base.'/storage/framework/sessions/'.$bId) && ! $signedIn($b), 'and A signs B out: its session id, encrypted with the previous key, still decrypts and is destroyed');
    $c = new Browser(WINDOWS, '198.51.100.81');
    $cId = $signIn($c, $rot);
    DB::table('signed_in_devices')->where('id', $rowOf($cId)->id)->update(['session_id' => 'not-a-ciphertext']);
    [$status, , $body] = $signOut($a, (int) $rowOf($cId)->id);
    $broken = $reported();
    check($status === 200 && $rowOf($cId) === null && ! $signedIn($c), 'a row whose session id won\'t decrypt is still deleted, and that device signed out on its next request');
    check(count($broken) === 1 && str_contains($broken[0], 'DecryptException') && ! str_contains($broken[0], $cId) && ! str_contains($broken[0], 'not-a-ciphertext'), 'and the failure is reported without the id or the stored value: '.json_encode($broken));
    check(! str_contains(implode("\n", $handler->reported), $cId), 'no report names a session id');

    // ── 10. The log and the mail (device_signed_out) ─────────────────────────────────────────────────────────────────────────────────────────────────
    Notification::fake();
    $mailed = $person('mailed');
    $a = new Browser;
    $b = new Browser(IPHONE, '198.51.100.90');
    $signIn($a, $mailed);
    $bId = $signIn($b, $mailed);
    $signOut($a, (int) $rowOf($bId)->id);
    $logged = DB::table(Tey\Kit\Features\Core\Kit::table('security_event'))->where('user_id', $mailed->id)->where('type', 'device_signed_out')->get();
    check($logged->count() === 1 && $logged[0]->agent === 'Safari on iOS' && $logged[0]->ip === '198.51.100.0/24', 'the security log records device_signed_out with the device\'s browser and platform and its network');
    $sent = Notification::sent($mailed, SecurityNotice::class)->filter(fn (SecurityNotice $notice) => $notice->type === 'device_signed_out');
    check($sent->count() === 1 && $sent->first()->ip === '203.0.113.77', 'and mails the owner a SecurityNotice, saying where the sign-out came from');
    $page = $send($a, 'GET', '/me/security')[2]['props'] ?? [];
    $event = collect($page['events'] ?? [])->firstWhere('type', 'device_signed_out');
    check(($event['label'] ?? null) === SecurityLog::LABELS['device_signed_out'] && ($event['agent'] ?? null) === 'Safari on iOS', 'the Security page shows it as "Signed out a device", on Safari on iOS');

    // ── 14. Octane ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    check((new ReflectionClass(TrackSignedInDevice::class))->getStaticProperties() === [] && (new ReflectionClass(SignedInDevices::class))->getStaticProperties() === [], 'the middleware and SignedInDevices keep no static state');
    $o1 = $person('octane-one');
    $o2 = $person('octane-two');
    $one = new Browser;
    $two = new Browser(IPHONE, '198.51.100.95');
    $oneId = $signIn($one, $o1);
    $twoId = $signIn($two, $o2);
    $at = Carbon::now();
    $deviceOf = fn (string $id): ?array => data_get(unserialize((string) file_get_contents($base.'/storage/framework/sessions/'.$id)), SignedInDevices::KEY);
    for ($i = 0; $i < 3; $i++) {
        $travel(5 * 60);
        $signedIn($one);
        $signedIn($two);
    }
    check($deviceOf($oneId)['user'] == $o1->id && $deviceOf($oneId)['id'] === (int) $rowOf($oneId)->id && $deviceOf($twoId)['user'] == $o2->id && $deviceOf($twoId)['id'] === (int) $rowOf($twoId)->id, 'two people\'s requests, alternating in one worker, each keep their own kit.device');
    check(Carbon::parse($rowOf($oneId)->last_seen_at)->equalTo(Carbon::now()) && Carbon::parse($rowOf($twoId)->last_seen_at)->equalTo(Carbon::now()) && Carbon::now()->greaterThan($at), 'and each touch lands on that person\'s row');

    check(Workspace::current() === null, 'no tenant context leaks out of the checks');
    echo 'Signed-in devices on '.($pgsql ? 'PostgreSQL (a throwaway schema)' : 'SQLite files').': router dispatch with the web group, real Fortify and Concierge, the database, file, memcached (the cache-based handler, on a file cache store) and cookie session drivers, synthetic browsers. Not covered: a real Redis, Valkey, Memcached or DynamoDB server, MySQL and SQL Server, file sessions on several servers, a consumer app, a real browser.'."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
