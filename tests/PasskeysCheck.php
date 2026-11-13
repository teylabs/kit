<?php

// Passkeys: the sign-in page offers Fortify's passkey endpoints while the feature is on; a passkey signs in only an active Concierge account (passkeys'
// login check, since no Validated event fires); Settings › Security lists the person's own passkeys, renames one, and removes one with the password,
// each logged; another person's passkey is a 404. With the feature off there is no list and no endpoint. SQLite memory, real Concierge, Fortify and
// laravel/passkeys, the kit's router; no browser (the WebAuthn ceremony itself is the browser's and Fortify's).
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\RequirePassword;
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
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\PasskeysServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\AccountStatus;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
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

class HttpUser extends Authenticatable implements Member, PasskeyUser
{
    use HasApiTokens, HasWorkspaces, PasskeyAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public function getForeignKey()
    {
        return 'user_id';
    }
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-passkeys-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'people' => ['hide_grants' => []], 'mcp' => ['ui' => true]],
        'fortify' => ['guard' => 'web', 'features' => [Features::passkeys()]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        FoundationServiceProvider::class, EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, PasskeysServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('password.confirm', RequirePassword::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passkeys/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
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

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $people = [];
    foreach (['alice', 'bob'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make('secret-'.$name)]);
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }
    $key = function (HttpUser $user, string $name): int {
        return (int) DB::table('passkeys')->insertGetId(['user_id' => $user->id, 'name' => $name, 'credential_id' => bin2hex(random_bytes(8)), 'credential' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    };
    $laptop = $key($people['alice'], 'Laptop');
    $phone = $key($people['alice'], 'Phone');
    $bobs = $key($people['bob'], 'Bob key');
    $cookie = null;
    $send = function (?string $who, string $method, string $path, array $data = [], bool $json = true) use ($app, $people, &$cookie): array {
        Auth::guard('web')->logout();
        if ($who !== null) {
            Auth::guard('web')->setUser($people[$who]);
        }
        $server = $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $request = Request::create('https://kit.test'.$path, $method, $data, server: $server);
        $request->setUserResolver(fn () => $who === null ? null : $people[$who]);
        // A session the caller carries on (its cookie), as a browser does between Confirm and Add.
        if ($cookie !== null) {
            $request->cookies->set('s', $cookie);
        }
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        if ($response->getStatusCode() >= 500) {
            fwrite(STDERR, substr((string) $response->getContent(), 0, 800)."\n");
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };

    // The sign-in page: Fortify's passkey endpoints while the feature is on.
    [$status, $login] = $send(null, 'GET', '/login', json: false);
    httpCheck($status === 200 && ($login['props']['passkey'] ?? null) === ['options_url' => 'https://kit.test/passkeys/login/options', 'login_url' => 'https://kit.test/passkeys/login'], 'the sign-in page offers the passkey endpoints ('.$status.')');

    // Passkeys' login check: only an active Concierge account.
    $passkey = fn (int $id) => Passkey::query()->findOrFail($id);
    httpCheck(Passkeys::allowsLogin(request(), $passkey($laptop)) === true, 'an active account signs in with its passkey');
    ConciergeModels::account()::query()->updateOrCreate(['user_id' => $people['bob']->id], ['status' => AccountStatus::Deactivated, 'deactivated_at' => now(), 'deactivated_by_id' => $people['bob']->id, 'deactivation_reason' => 'Test.']);
    httpCheck(Passkeys::allowsLogin(request(), $passkey($bobs)) === false, 'a deactivated account is refused, though its passkey is valid');

    // Settings › Security lists only the person's own passkeys.
    [$status, $page] = $send('alice', 'GET', '/me/security', json: false);
    $items = $page['props']['passkeys']['items'] ?? null;
    httpCheck($status === 200 && array_column($items ?? [], 'name') === ['Laptop', 'Phone'] && ($page['props']['passkeys']['url'] ?? null) === 'https://kit.test/me/security/passkeys', 'Security lists the person\'s own passkeys, oldest first ('.$status.')');

    // Rename: their own only; another person's is a 404.
    [$status] = $send('alice', 'PATCH', '/me/security/passkeys/'.$phone, ['name' => '  Work phone ']);
    httpCheck($status === 200 && DB::table('passkeys')->where('id', $phone)->value('name') === 'Work phone', 'a passkey is renamed, trimmed');
    [$status] = $send('alice', 'PATCH', '/me/security/passkeys/'.$phone, ['name' => '']);
    httpCheck($status === 422 && DB::table('passkeys')->where('id', $phone)->value('name') === 'Work phone', 'an empty name is refused');
    [$status] = $send('alice', 'PATCH', '/me/security/passkeys/'.$bobs, ['name' => 'Mine now']);
    httpCheck($status === 404 && DB::table('passkeys')->where('id', $bobs)->value('name') === 'Bob key', 'another person\'s passkey cannot be renamed (404)');

    // Remove: with the password, logged; another person's is a 404 even with the right password.
    [$status] = $send('alice', 'DELETE', '/me/security/passkeys/'.$laptop);
    httpCheck($status === 422 && DB::table('passkeys')->where('id', $laptop)->exists(), 'removing a passkey without the password is refused');
    [$status] = $send('alice', 'DELETE', '/me/security/passkeys/'.$laptop, ['password' => 'wrong']);
    httpCheck($status === 422 && DB::table('passkeys')->where('id', $laptop)->exists(), 'removing a passkey with the wrong password is refused');
    [$status] = $send('alice', 'DELETE', '/me/security/passkeys/'.$bobs, ['password' => 'secret-alice']);
    httpCheck($status === 404 && DB::table('passkeys')->where('id', $bobs)->exists(), 'another person\'s passkey cannot be removed (404)');
    [$status] = $send('alice', 'DELETE', '/me/security/passkeys/'.$laptop, ['password' => 'secret-alice']);
    httpCheck($status === 204 && DB::table('passkeys')->where('id', $laptop)->doesntExist() && DB::table('kit_security_events')->where('user_id', $people['alice']->id)->where('type', 'passkey_removed')->count() === 1, 'a passkey is removed with the password, and the security log says so');

    // Adding one, as Security's Add a passkey does it on a default Fortify setup (its registration behind password.confirm): without a confirmed password
    // the options are a 423; the page confirms the password first, through Fortify's own confirmation, and then they are served. A wrong password is a 422
    // on `password`, shown inline, and confirms nothing.
    [$status, $page] = $send('alice', 'GET', '/me/security', json: false);
    httpCheck(($page['props']['passkeys']['confirm_url'] ?? null) === 'https://kit.test/user/confirm-password', 'Security names Fortify\'s password confirmation for Add a passkey');
    $cookie = 'alice-'.str_repeat('a', 34);
    [$status] = $send('alice', 'GET', '/user/passkeys/options');
    httpCheck($status === 423, 'unconfirmed, Fortify\'s passkey options are refused with a 423, as before ('.$status.')');
    [$status, $body] = $send('alice', 'POST', '/user/confirm-password', ['password' => 'wrong']);
    httpCheck($status === 422 && isset($body['errors']['password']), 'a wrong password is a 422 on password, for the field ('.$status.')');
    [$status] = $send('alice', 'GET', '/user/passkeys/options');
    httpCheck($status === 423, 'and confirms nothing: the options are still a 423 ('.$status.')');
    [$status] = $send('alice', 'POST', '/user/confirm-password', ['password' => 'secret-alice']);
    httpCheck($status === 201, 'the right password is confirmed (201) ('.$status.')');
    [$status, $body] = $send('alice', 'GET', '/user/passkeys/options');
    httpCheck($status === 200 && isset($body['options']['challenge']), 'then the passkey options are served, not a 423 ('.$status.')');
    $cookie = null;

    // A host that turns Fortify's confirmation off for passkeys (fortify-options.passkeys.confirmPassword false) gets no confirm_url, so the page does not
    // ask for the password.
    $config->set('fortify-options.passkeys.confirmPassword', false);
    try {
        [$status, $page] = $send('alice', 'GET', '/me/security', json: false);
        httpCheck($status === 200 && array_key_exists('confirm_url', $page['props']['passkeys'] ?? []) && $page['props']['passkeys']['confirm_url'] === null, 'with passkey password confirmation off, Security has no confirm_url ('.$status.')');
    } finally {
        $config->set('fortify-options.passkeys.confirmPassword', true);
    }

    // Off: no passkey list, no endpoint, nothing on the sign-in page's view model.
    $config->set('fortify.features', []);
    [$status, $page] = $send('alice', 'GET', '/me/security', json: false);
    httpCheck($status === 200 && array_key_exists('passkeys', $page['props'] ?? []) && $page['props']['passkeys'] === null, 'with passkeys off, Security has no passkeys section');
    [$status] = $send('alice', 'PATCH', '/me/security/passkeys/'.$phone, ['name' => 'Off']);
    httpCheck($status === 404 && DB::table('passkeys')->where('id', $phone)->value('name') === 'Work phone', 'with passkeys off, the endpoints are a 404');

    echo "Passkeys: SQLite memory, real Concierge, Fortify and laravel/passkeys, the kit's routes; no browser (the WebAuthn ceremony is the browser's).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
