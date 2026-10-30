<?php

// Open registration: an app that turns on Fortify's `registration` feature gets the kit's Auth/Register page at /register and a "Create an account" link on
// sign in; posting it creates the account with the app's CreatesNewUsers action, or comes back with its validation errors. (With the kit's defaults,
// registration off, there is neither: AuthOnlyCheck.) SQLite memory, real Concierge, Fortify and Inertia, synthetic users; no network.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\ViewModels\RegisterViewModel;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
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

class HttpUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-register-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    // The app's own config/fortify.php, with Fortify's registration feature on: the kit's defaults (registration off) are not applied over it.
    mkdir($base.'/config', 0777, true);
    $fortify = ['guard' => 'web', 'passwords' => 'users', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/', 'prefix' => '', 'domain' => null, 'limiters' => ['login' => null], 'views' => true, 'features' => [Features::registration(), Features::resetPasswords()]];
    file_put_contents($base.'/config/fortify.php', '<?php return '.var_export($fortify, true).';');
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
        'fortify' => $fortify,
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'session-ui'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true]],
        'concierge' => ['invitations' => ['deliver' => true]], 'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
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
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    // The kit's lang, as KitServiceProvider loads it: the plain breach message comes from kit::validation.
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);

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
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
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

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    // The app's own CreatesNewUsers action (Fortify's), as a starter kit ships it: validates, then creates the user.
    $app->singleton(CreatesNewUsers::class, fn () => new class implements CreatesNewUsers
    {
        public function create(array $input)
        {
            Validator::make($input, ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'string', 'min:12', 'confirmed']])->validate();

            return HttpUser::create(['name' => $input['name'], 'email' => $input['email'], 'password' => Hash::make($input['password'])]);
        }
    });

    $page = function (string $path) use ($app): array {
        Auth::guard('web')->logout();
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        $response = $app['router']->dispatch($request);

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };
    $post = function (array $data) use ($app): array {
        Auth::guard('web')->logout();
        $session = $app['session']->driver();
        $session->flush();
        $session->setPreviousUrl('https://kit.test/register');
        $request = Request::create('https://kit.test/register', 'POST', $data);
        $request->setLaravelSession($session);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        return [$response, $session];
    };

    // Concierge's registration mode, open (the kit's default is closed; set after boot, as the kit's concierge defaults are laid over the config then).
    config(['concierge.registration.mode' => 'open']);
    httpCheck(Features::enabled(Features::registration()), 'the app turns on Fortify\'s registration feature');
    [$status, $body] = $page('/login');
    httpCheck($status === 200 && ($body['props']['register'] ?? null) === '/register', 'sign in carries the "Create an account" link to /register ('.json_encode($body['props']['register'] ?? 'absent').')');
    [$status, $body] = $page('/register');
    $props = $body['props'] ?? [];
    httpCheck($status === 200 && ($body['component'] ?? null) === 'Auth/Register/Page', 'GET /register is the kit\'s Auth/Register page ('.$status.')');
    $expected = ['title' => 'Create an account', 'heading' => 'Create an account', 'lead' => 'Enter your details below to create your account', 'action' => '/register', 'submitLabel' => 'Create account', 'email' => null, 'emailHint' => null, 'submit' => [], 'throttleMessage' => null, 'login' => '/login', 'status' => null];
    httpCheck(array_intersect_key($props, $expected) === $expected, 'its view model: Fortify\'s /register, the email typed (no address shown), nothing extra posted, the kit\'s 429 copy and a Sign in link ('.json_encode(array_intersect_key($props, $expected)).')');
    httpCheck(isset($props['layout']), 'and a page layout like every page view model');

    // Concierge's registration mode gates it, per request: closed (the kit's default) makes /register a 404 and drops the link; invitation mode keeps the page
    // (an invitation's register link) but not the link, which only open mode offers.
    foreach (['closed' => 404, 'invitation' => 200] as $mode => $expectedStatus) {
        config(['concierge.registration.mode' => $mode]);
        try {
            httpCheck(($page('/login')[1]['props'] ?? ['register' => 'absent'])['register'] === null && $page('/register')[0] === $expectedStatus, 'in Concierge\'s '.$mode.' mode sign in has no register link and /register answers '.$expectedStatus);
        } finally {
            config(['concierge.registration.mode' => 'open']);
        }
    }

    // A host's own sign-up flow renders the same page with its own values; they reach the page as props (the shape the browser checks drive).
    $invited = new RegisterViewModel('Create your account', heading: 'Create your account', lead: 'You have been invited to join Northwind Farms.', action: '/invitations/token/register', email: 'ada@example.com', emailHint: 'The invitation was sent to this address.', submit: ['email' => 'ada@example.com'], throttleMessage: 'Too many attempts with this invitation. Wait a minute, then try again.');
    $app->instance('request', Request::create('https://kit.test/invitations/token/register'));
    $props = json_decode(json_encode($invited->toArray()), true);
    httpCheck($props['email'] === 'ada@example.com' && $props['submit'] === ['email' => 'ada@example.com'] && $props['action'] === '/invitations/token/register' && $props['throttleMessage'] === 'Too many attempts with this invitation. Wait a minute, then try again.' && $props['lead'] === 'You have been invited to join Northwind Farms.', 'a host\'s view model passes its action, lead, shown address, posted values and 429 copy');

    // Validation errors come back to the form, nothing is created and nobody is signed in.
    [$response, $session] = $post(['name' => '', 'email' => 'nobody', 'password' => 'short', 'password_confirmation' => 'other']);
    $errors = $session->get('errors')?->getBag('default')->toArray() ?? [];
    httpCheck($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test/register' && isset($errors['name'], $errors['email'], $errors['password']) && HttpUser::query()->count() === 0 && ! Auth::guard('web')->check(), 'an invalid registration redirects back with name, email and password errors and creates nothing ('.json_encode(array_keys($errors)).')');

    // A successful registration creates the account with the app's action, signs it in and lands on Fortify's home.
    [$response] = $post(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'a long passphrase', 'password_confirmation' => 'a long passphrase']);
    $user = HttpUser::query()->where('email', 'ada@example.test')->first();
    httpCheck($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test' && $user !== null && $user->name === 'Ada' && Auth::guard('web')->id() === $user->id, 'a valid registration creates the account, signs it in and redirects home ('.$response->getStatusCode().' '.$response->headers->get('Location').')');
    echo "Registration checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
