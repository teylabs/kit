<?php

// session-ui on a core and shell host: no workspaces, access, Concierge, Pennant or multitenancy installed or configured. The app lists core, shell and
// session-ui in kit.features, the sign-in page renders, a user signs in and the session is authenticated, and no workspace, access or Concierge class, and
// no tey/mod maintenance class (install history), is ever loaded. Synthetic host app in a temp directory, SQLite memory, real Fortify and Inertia, synthetic
// users; no network, no browser.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\Inertia;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\SessionUiPageLayouts;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Shell\ShellProvider;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The host's own user: a plain Laravel user, no Concierge member and no workspaces. */
class CoreUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** Classes a core and shell host must never load: the workspace, access and workspace-ui features, Concierge, Pennant, multitenancy, spatie's permissions, and tey/mod's install history. */
function forbidden(): array
{
    $prefixes = ['Tey\\Mod\\Maintenance\\', 'Tey\\Concierge\\', 'Tey\\Kit\\Features\\Workspaces\\', 'Tey\\Kit\\Features\\Access\\', 'Tey\\Kit\\Features\\WorkspaceUi\\', 'Tey\\Kit\\Features\\Settings\\', 'Laravel\\Pennant\\', 'Spatie\\Multitenancy\\', 'Spatie\\Permission\\'];

    return array_values(array_filter([...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], function (string $class) use ($prefixes): bool {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }));
}

try {
    // The host: a synthetic app in a temp directory.
    $root = sys_get_temp_dir().'/kit-session-ui-core-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    mkdir($root, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/fixtures/host-app', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $root.substr($entry->getPathname(), strlen(__DIR__.'/fixtures/host-app'));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
    // The fixture's own User is a Concierge member: this host's is the plain one above.
    unlink($root.'/app/Models/User.php');
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $root = realpath($root);

    // The host boots from kit.features, with only the framework, Fortify, Inertia, laravel-data and medialibrary.
    $app = new Application($root);
    $app->useStoragePath($root.'/storage');
    $app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Northwind Farms', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => CoreUser::class]], 'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
        'view' => ['paths' => [], 'compiled' => $root.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]],
        'kit' => ['features' => ['core', 'shell', 'session-ui'], 'models' => ['user' => CoreUser::class]],
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
        ModServiceProvider::class, LaravelDataServiceProvider::class, FeaturesServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
        NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $registry = $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    foreach (['auth' => Authenticate::class, 'throttle' => ThrottleRequests::class, 'guest' => RedirectIfAuthenticated::class] as $alias => $middleware) {
        $app['router']->aliasMiddleware($alias, $middleware);
    }
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $adopted = array_values(array_filter(array_keys(FeaturesServiceProvider::FEATURES), fn (string $id): bool => $registry->adopts($id)));
    check($adopted === ['core', 'shell', 'session-ui'] && $app->getProvider(SessionUiProvider::class) !== null && $app->getProvider(ShellProvider::class) !== null, 'the host boots core, shell and session-ui from kit.features, and nothing else');

    // Its tables: the host's users, password resets and media, then the kit's migrations for its features.
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
    // session-ui's avatars are medialibrary items (it requires spatie/laravel-medialibrary): the host runs its media migration.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $migrations = glob(dirname(__DIR__).'/resources/features/{core,shell,session-ui}/migrations/*.php.stub', GLOB_BRACE);
    usort($migrations, fn (string $a, string $b): int => strcmp(basename($a), basename($b)));
    foreach ($migrations as $file) {
        (require $file)->up();
    }
    check(Schema::hasTable('kit_security_events') && Schema::hasTable('kit_user_state') && ! Schema::hasTable('workspaces') && ! Schema::hasTable('roles'), 'migrate creates session-ui\'s tables and no workspace or permission table');
    $app['router']->getRoutes()->refreshNameLookups();

    $user = CoreUser::create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now(), 'password' => Hash::make('a long passphrase')]);
    $dispatch = function (Request $request) use ($app): Response {
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            return $app['router']->dispatch($request);
        } catch (Throwable $error) {
            return $app->make(ExceptionHandler::class)->render($request, $error);
        }
    };
    $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

    // The sign-in page: the kit's Auth/Login, with the shell feature's `shell` prop (a guest's: the product and the footer).
    $response = $dispatch(Request::create('https://kit.test/login', 'GET', server: $inertia));
    $body = json_decode((string) $response->getContent(), true) ?? [];
    check($response->getStatusCode() === 200 && ($body['component'] ?? null) === 'Auth/Login/Page', 'GET /login renders the kit\'s Auth/Login page ('.$response->getStatusCode().')');
    check(($body['props']['shell']['kind'] ?? null) === 'guest' && ($body['props']['shell']['product']['name'] ?? null) === 'Northwind Farms' && isset($body['props']['shell']['footer']), 'the sign-in page carries the shared `shell` prop: a guest\'s, with the product and the footer');
    check(is_array($body['props']['kitPassword'] ?? null) && array_key_exists('passkeyOffer', $body['props']) && array_key_exists('environment', $body['props']), 'and session-ui\'s own props: kitPassword, passkeyOffer and environment');

    // Signing in: Fortify's POST /login with the right password authenticates the session and lands on the app's own home (kit.home).
    Auth::guard('web')->logout();
    $wrong = $dispatch(Request::create('https://kit.test/login', 'POST', ['email' => 'ada@example.test', 'password' => 'not the passphrase']));
    check(Auth::guard('web')->guest() && $wrong->getStatusCode() === 302, 'a wrong password does not sign in');
    $response = $dispatch(Request::create('https://kit.test/login', 'POST', ['email' => 'ada@example.test', 'password' => 'a long passphrase']));
    check($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test' && Auth::guard('web')->id() === $user->id && Auth::guard('web')->check(), 'the right password signs in: the session is authenticated as the user and lands on the app\'s home ('.$response->getStatusCode().' '.$response->headers->get('Location').')');
    check(DB::table('kit_security_events')->where('user_id', $user->id)->exists(), 'the sign-in is in the user\'s security log');

    // Signed in, the shell prop is the app shell with no workspace (session-ui's PageShell), shared once.
    $request = Request::create('https://kit.test/login', 'GET', server: $inertia);
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);
    $shell = Inertia::getShared('shell');
    $shared = is_callable($shell) ? $shell() : null;
    check(is_array($shared) && ($shared['kind'] ?? null) === 'app' && $shared['workspace'] === null && $shared['switcher'] === null && $shared['navigation'] === [] && ($shared['user']['name'] ?? null) === 'Ada Example', 'signed in, the shared `shell` is the app shell with the user and no workspace, switcher or navigation');

    // Without workspaces the shell still carries the app's own registered pages: root URLs, each allowed by its Gate ability (undefined or denied: absent),
    // and `user-menu` links in the account menu.
    Gate::define('app.dashboard', fn () => true);
    Gate::define('app.settings', fn () => true);
    Gate::define('app.admin', fn () => false);
    $registry->navigation('app.dashboard', 'Dashboard', 'dashboard')->navigation('app.admin', 'Admin', 'admin')->navigation('app.undefined', 'Hidden', 'hidden')->navigation('app.settings', 'Settings', 'settings', 'user-menu');
    $request = Request::create('https://kit.test/dashboard', 'GET', server: $inertia);
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);
    $app['router']->get('dashboard', fn () => 'ok')->name('app.dashboard.page');
    $request->setRouteResolver(fn () => $app['router']->getRoutes()->match($request));
    $navigated = (new PageShell)->forRequest($request)->toArray();
    check($navigated['navigation'] === [['label' => 'Dashboard', 'url' => '/dashboard', 'current' => true, 'group' => 'workspace']], 'without workspaces the shell lists the registered pages the Gate allows, root-relative and current by path; a denied or undefined ability is absent');
    check(array_column($navigated['user']['items'], 'label')[0] === 'Settings' && $navigated['user']['items'][0]['url'] === '/settings', 'a registered user-menu link (Settings) joins the account menu');

    // kit.brand.lockup (set in config/brand.php): a path (it has a slash) is the full lockup image, on in both shell sources; `true` and `false` keep their meaning with no image.
    $lockupBefore = getenv('KIT_BRAND_LOCKUP');
    $configBefore = config('kit.brand.lockup');
    try {
        $parsed = [];
        foreach (['/brand/lockup.svg', 'true', 'false'] as $value) {
            putenv('KIT_BRAND_LOCKUP='.$value);
            $_ENV['KIT_BRAND_LOCKUP'] = $_SERVER['KIT_BRAND_LOCKUP'] = $value;
            $parsed[$value] = (require dirname(__DIR__).'/config/kit.php')['brand']['lockup'];
        }
        check($parsed === ['/brand/lockup.svg' => false, 'true' => false, 'false' => false], 'config/kit.php no longer reads KIT_BRAND_LOCKUP (removed in 0.17): the lockup stays at its default, off');
        $guest = Request::create('https://kit.test/login', 'GET');
        foreach (['/brand/lockup.svg' => ['/brand/lockup.svg', true], true => [null, true], false => [null, false]] as $value => [$image, $on]) {
            config(['kit.brand.lockup' => $value === '/brand/lockup.svg' ? $value : (bool) $value]);
            $page = (new PageShell)->forRequest($guest)->toArray()['product'];
            $plain = (new \Tey\Kit\Features\Shell\PlainShell)->forRequest($guest)->toArray()['product'];
            check($page['lockup_image'] === $image && $page['lockup'] === $on && $plain['lockup_image'] === $image && $plain['lockup'] === $on, 'kit.brand.lockup '.var_export($value, true).': PageShell and PlainShell carry lockup '.var_export($on, true).' and lockup_image '.var_export($image, true));
        }
    } finally {
        $lockupBefore === false ? putenv('KIT_BRAND_LOCKUP') : putenv('KIT_BRAND_LOCKUP='.$lockupBefore);
        unset($_ENV['KIT_BRAND_LOCKUP'], $_SERVER['KIT_BRAND_LOCKUP']);
        config(['kit.brand.lockup' => $configBefore]);
    }

    // The root crumb is the home icon by path: gretel's URLs are absolute, so `https://host/` and `https://host/@slug` are home, and a page below is not.
    $home = [SessionUiPageLayouts::class, 'isHome'];
    check($home('https://kit.test') && $home('https://kit.test/') && $home('https://kit.test/@acme') && $home('/@acme') && $home('/') && ! $home('https://kit.test/@acme/settings') && ! $home('https://kit.test/settings'), 'the home crumb is recognised by path, whether gretel gives an absolute URL or a path');

    // A crumb's tooltip: the host's crumbTitleUsing() text when it says more than the label, else none (the label shows). Restored in finally.
    if (Illuminate\Routing\Route::hasMacro('breadcrumb')) {
        $app['router']->get('cases/a', fn () => 'ok')->name('app.case.page')->breadcrumb('Leaking roof');
        $app['router']->getRoutes()->refreshNameLookups();
        $crumbRequest = Request::create('/cases/a');
        $crumbRequest->setRouteResolver(fn () => $app['router']->getRoutes()->match($crumbRequest));
        $trail = fn (): array => array_map(fn ($crumb): array => [$crumb->label, $crumb->title], $app->make(SessionUiPageLayouts::class, ['request' => $crumbRequest])->layout('Leaking roof', null)->breadcrumbs);
        try {
            check($trail() === [['Leaking roof', null]], 'without a host tooltip a crumb has no title, so the label is its tooltip');
            SessionUiPageLayouts::crumbTitleUsing(fn ($crumb, int $index, Request $request): ?string => $crumb->current ? 'A-8364' : null);
            check($trail() === [['Leaking roof', 'A-8364']], "crumbTitleUsing() sets the current crumb's tooltip (a case's reference under its subject)");
            SessionUiPageLayouts::crumbTitleUsing(fn (): string => '  Leaking roof ');
            check($trail() === [['Leaking roof', null]], 'a tooltip that only repeats the label (or is blank) is dropped');
        } finally {
            SessionUiPageLayouts::crumbTitleUsing(null);
        }
    } else {
        throw new RuntimeException('gretel is not booted here, so the crumb tooltip is not checked');
    }

    // The welcome steps are the person's own: the timezone and the photo, never the workspace's.
    Onboarding::context((int) $user->id);
    check(array_column(Onboarding::steps(), 'id') === ['timezone', 'photo'], 'the welcome steps are the timezone and the photo');
    $names = array_filter(array_map(fn ($route): ?string => $route->getName(), $app['router']->getRoutes()->getRoutes()));
    check(! in_array('invitations.show', $names, true) && ! in_array('demo.login', $names, true) && ! in_array('home', $names, true) && in_array('login', $names, true) && in_array('focus.welcome', $names, true), 'the routes are the sign-in\'s: no invitation, demo sign-in or kit home');
    // Deletion (kit.lifecycle.*, off by default) needs workspaces and Concierge, so its routes are workspace-ui's: a session-ui-only host has none.
    $deletion = array_filter(array_map(fn ($route): string => $route->getActionName(), $app['router']->getRoutes()->getRoutes()), fn (string $action): bool => str_contains($action, 'DeletionController'));
    check($deletion === [], 'there is no workspace or account deletion route: deletion comes with workspace-ui');

    $loaded = forbidden();
    check($loaded === [], 'no workspace, access, workspace-ui, settings, Concierge, Pennant, multitenancy, spatie permission or tey/mod maintenance class was loaded'.($loaded === [] ? '' : ': '.implode(', ', $loaded)));
    echo "session-ui on a core and shell host: a synthetic host app, then HTTP on SQLite memory with real Fortify and Inertia. No browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
