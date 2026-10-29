<?php

// session-ui on a core and shell host: no workspaces, access, Concierge, Pennant or multitenancy installed or configured. kit:add session-ui adds it
// alone, the app boots from its lineages, the sign-in page renders, a user signs in and the session is authenticated, and no workspace, access or Concierge
// class is ever loaded. Synthetic host app in a temp directory, SQLite memory, real Fortify and Inertia, synthetic users; no network, no browser.
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
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Shell\ShellProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;
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

/** Classes a core and shell host must never load: the workspace, access and workspace-ui features, Concierge, Pennant, multitenancy and spatie's permissions. */
function forbidden(): array
{
    $prefixes = ['Tey\\Concierge\\', 'Tey\\Kit\\Features\\Workspaces\\', 'Tey\\Kit\\Features\\Access\\', 'Tey\\Kit\\Features\\WorkspaceUi\\', 'Tey\\Kit\\Features\\Settings\\', 'Laravel\\Pennant\\', 'Spatie\\Multitenancy\\', 'Spatie\\Permission\\'];

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
    // The host: a synthetic app with core and shell installed from their lineages, then kit:add session-ui.
    $root = sys_get_temp_dir().'/kit-session-ui-core-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    mkdir($root, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/fixtures/host-app', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $root.substr($entry->getPathname(), strlen(__DIR__.'/fixtures/host-app'));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
    // The fixture's own User is a Concierge member: this host's is the plain one above.
    unlink($root.'/app/Models/User.php');
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'verification'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $root = realpath($root);
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () use ($operation) { return $operation(); };');
    $bound = fn (Closure $operation): Closure => require $root.'/verification/host-operations.txt';
    $pass = fn () => true;
    Application::configure($root)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $lineages = new Lineages(new Lifecycle);

    $preview = $lineages->preview($root, 'host', ['core', 'shell'], [], $paths);
    check($preview['executable'] && array_column($preview['features'], 'feature') === ['core', 'shell'], 'core and shell install on the host app');
    $lineages->add($root, 'host', ['core', 'shell'], [], $paths, $preview['plan'], null, $bound($pass));
    $preview = $lineages->preview($root, 'host', ['session-ui'], [], $paths);
    $rows = array_column($preview['features'], 'action', 'feature');
    check($preview['executable'] && $rows === ['core' => 'adopted', 'shell' => 'adopted', 'session-ui' => 'add'], 'kit:add session-ui on the core and shell host plans session-ui alone, with no workspaces, access or workspace-ui');
    $result = $lineages->add($root, 'host', ['session-ui'], [], $paths, $preview['plan'], null, $bound($pass));
    $installed = array_keys(Checkout::installed($root, 'host'));
    sort($installed);
    check($result['status'] === 'completed' && $installed === ['tey/kit:core', 'tey/kit:session-ui', 'tey/kit:shell'], 'session-ui is installed beside core and shell, and nothing else is ('.implode(', ', $installed).')');
    $auth = array_map(fn (string $path): string => dirname(substr($path, strlen('resources/js/pages/'))), array_values(array_filter(array_keys(Checkout::installed($root, 'host')['tey/kit:session-ui']['files']), fn (string $path): bool => str_starts_with($path, 'resources/js/pages/Auth/'))));
    sort($auth);
    check($auth === ['Auth/ConfirmPassword', 'Auth/ForgotPassword', 'Auth/Login', 'Auth/ResetPassword', 'Auth/TwoFactor', 'Auth/VerifyEmail'] && is_file($root.'/resources/js/pages/Auth/Login/Page.vue') && ! file_exists($root.'/resources/js/pages/Auth/Invitation') && ! file_exists($root.'/resources/js/pages/Welcome/WorkspaceTimezone'), 'it writes the six Auth pages, and no invitation or workspace-timezone page');
    check(glob($root.'/database/migrations/kit/session-ui/*.php') !== [] && ! is_dir($root.'/database/migrations/kit/workspaces') && ! is_dir($root.'/database/migrations/kit/access'), 'it writes session-ui\'s migrations (security events, user state) and no workspace or access migration');

    // The host boots from its lineages (kit.app_id, no kit.features), with only the framework, Fortify, Inertia, laravel-data and medialibrary.
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
        'kit' => ['app_id' => 'host', 'models' => ['user' => CoreUser::class]],
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
    // session-ui's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    foreach (['auth' => Authenticate::class, 'throttle' => ThrottleRequests::class, 'guest' => RedirectIfAuthenticated::class] as $alias => $middleware) {
        $app['router']->aliasMiddleware($alias, $middleware);
    }
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $adopted = array_values(array_filter(array_keys(FeaturesServiceProvider::FEATURES), fn (string $id): bool => $registry->adopts($id)));
    check($adopted === ['core', 'shell', 'session-ui'] && $app->getProvider(SessionUiProvider::class) !== null && $app->getProvider(ShellProvider::class) !== null, 'the host boots core, shell and session-ui from its lineages, and nothing else');

    // Its tables: the host's users, password resets and media, then the kit migrations kit:add wrote into the app.
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
    $migrations = glob($root.'/database/migrations/kit/*/*.php');
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

    // The welcome steps are the person's own: the timezone and the photo, never the workspace's.
    Onboarding::context((int) $user->id);
    check(array_column(Onboarding::steps(), 'id') === ['timezone', 'photo'], 'the welcome steps are the timezone and the photo');
    $names = array_filter(array_map(fn ($route): ?string => $route->getName(), $app['router']->getRoutes()->getRoutes()));
    check(! in_array('invitations.show', $names, true) && ! in_array('demo.login', $names, true) && ! in_array('home', $names, true) && in_array('login', $names, true) && in_array('focus.welcome', $names, true), 'the routes are the sign-in\'s: no invitation, demo sign-in or kit home');
    // Deletion (kit.lifecycle.*, off by default) needs workspaces and Concierge, so its routes are workspace-ui's: a session-ui-only host has none.
    $deletion = array_filter(array_map(fn ($route): string => $route->getActionName(), $app['router']->getRoutes()->getRoutes()), fn (string $action): bool => str_contains($action, 'DeletionController'));
    check($deletion === [], 'there is no workspace or account deletion route: deletion comes with workspace-ui');

    $loaded = forbidden();
    check($loaded === [], 'no workspace, access, workspace-ui, settings, Concierge, Pennant, multitenancy or spatie permission class was loaded'.($loaded === [] ? '' : ': '.implode(', ', $loaded)));
    echo "session-ui on a core and shell host: kit:add on a synthetic host app, then HTTP on SQLite memory with real Fortify and Inertia. No browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
