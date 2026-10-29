<?php

// A host app booted from its installed kit lineages (kit.app_id `host`, no kit.features), its kit migrations run from the app's own
// database/migrations/kit, then the sign-in over HTTP: GET /login renders the kit's Auth/Login, a wrong password is refused, the right one
// authenticates the session. Returns [app, user, login page body]. SQLite memory, real Fortify and Inertia; a synthetic user.
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Mod\ModServiceProvider;

/**
 * @param  class-string  $user  the host's user model
 * @param  list<class-string>  $providers  service providers beyond the framework, Fortify, Inertia, laravel-data and the kit's
 * @param  Closure(): void  $tables  the host's own tables beyond users, password resets and media (run before the kit migrations)
 * @param  callable(bool, string): void  $check
 */
return function (string $root, string $user, array $providers, Closure $tables, callable $check, string $label): array {
    @mkdir($root.'/storage/framework/views', 0700, true);
    @mkdir($root.'/bootstrap/cache', 0700, true);
    $app = new Application($root);
    $app->useStoragePath($root.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Northwind Farms', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => $user]], 'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
        'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => 'host', 'models' => ['user' => $user]],
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
        ModServiceProvider::class, LaravelDataServiceProvider::class, ...$providers, FeaturesServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
        NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    foreach (['auth' => Authenticate::class, 'throttle' => ThrottleRequests::class, 'guest' => RedirectIfAuthenticated::class] as $alias => $middleware) {
        $app['router']->aliasMiddleware($alias, $middleware);
    }
    $app['translator']->addNamespace('kit', dirname(__DIR__, 2).'/lang');
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
    (require dirname(__DIR__, 2).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $tables();
    $migrations = glob($root.'/database/migrations/kit/*/*.php');
    usort($migrations, fn (string $a, string $b): int => strcmp(basename($a), basename($b)));
    foreach ($migrations as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();
    $person = $user::query()->create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now(), 'password' => Hash::make('a long passphrase')]);
    $dispatch = function (Request $request) use ($app): Response {
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            return $app['router']->dispatch($request);
        } catch (Throwable $error) {
            return $app->make(ExceptionHandler::class)->render($request, $error);
        }
    };
    $response = $dispatch(Request::create('https://kit.test/login', 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));
    $body = json_decode((string) $response->getContent(), true) ?? [];
    $check($response->getStatusCode() === 200 && ($body['component'] ?? null) === 'Auth/Login/Page', $label.'GET /login renders the kit\'s Auth/Login page ('.$response->getStatusCode().')');
    Auth::guard('web')->logout();
    $dispatch(Request::create('https://kit.test/login', 'POST', ['email' => 'ada@example.test', 'password' => 'not the passphrase']));
    $check(Auth::guard('web')->guest(), $label.'a wrong password does not sign in');
    $response = $dispatch(Request::create('https://kit.test/login', 'POST', ['email' => 'ada@example.test', 'password' => 'a long passphrase']));
    $check($response->getStatusCode() === 302 && Auth::guard('web')->id() === $person->getKey(), $label.'the right password signs in: the session is authenticated as the user ('.$response->getStatusCode().' '.$response->headers->get('Location').')');

    return [$app, $person, $body];
};
