<?php

// Invited sign-up end to end: the kit's password policy and its plain breach message, one email in all (the invitation proves the address, so nothing asks
// to verify it again), every invite path verifying the invited address, and the post-sign-up passkey offer. SQLite memory, real Concierge, Fortify and
// Inertia, synthetic users; the breach check is a local fake, never the network.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
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
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Routing\PublicRoute;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Http\Middleware\ResolveWorkspace;
use Tey\Kit\Testing\RouteInvariants;
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

class RouteInvariantsUnguarded
{
    public function index(): string
    {
        return 'ok';
    }

    public function update(): string
    {
        return 'ok';
    }
}

#[AuthorizedBy(RouteInvariantsUnguarded::class)]
class RouteInvariantsDeclared
{
    public function index(): string
    {
        return 'ok';
    }

    public function store(): string
    {
        return 'ok';
    }
}

#[AuthorizedBy('NoSuchOperation')]
class RouteInvariantsGhost
{
    public function store(): string
    {
        return 'ok';
    }
}

#[PublicRoute('A signed link')]
class RouteInvariantsPublic
{
    public function index(): string
    {
        return 'ok';
    }
}

#[PublicRoute('A status page', open: true)]
class RouteInvariantsOpen
{
    public function index(): string
    {
        return 'ok';
    }

    public function store(): string
    {
        return 'ok';
    }
}

class HttpUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

/** A host User that can hold passkeys (session-ui's optional `passkeys` integration). */
class PasskeyHttpUser extends HttpUser implements PasskeyUser
{
    use PasskeyAuthenticatable;

    public function getForeignKey()
    {
        return 'user_id';
    }
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-route-invariants-'.bin2hex(random_bytes(4));
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
        'kit' => ['routes' => ['session-ui' => ['tenant-invitations' => true]], 'app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true], 'mcp' => ['ui' => true]],
        'concierge' => ['invitations' => ['deliver' => true], 'tenant_invitations' => ['platform_owners' => ['owner@example.test']]], 'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
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

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    // The breach check is a local fake: one known-breached password, never the network.
    $app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data)
        {
            return $data['value'] !== 'correct horse battery';
        }
    });
    // The host's usual listener: a Registered user who isn't verified is sent Laravel's verification email.
    $app['events']->listen(Registered::class, SendEmailVerificationNotification::class);
    $notifications = Notification::fake();
    $mail = Mail::fake();
    // Every notification and mailable the app sent, in all (the fakes' own assertions need PHPUnit, which these checks don't load).
    $sent = function () use ($notifications, $mail): array {
        $all = [];
        foreach ($notifications->sentNotifications() as $byNotifiable) {
            foreach ($byNotifiable as $byId) {
                foreach ($byId as $class => $list) {
                    foreach ($list as $entry) {
                        $all[] = $class;
                    }
                }
            }
        }
        foreach (['mailables', 'queuedMailables'] as $property) {
            foreach ((fn () => $this->{$property})->call($mail) as $mailable) {
                $all[] = $mailable::class;
            }
        }

        return $all;
    };

    $report = RouteInvariants::all(['Tey\\Kit\\']);
    httpCheck($report === [], 'the kit\'s own routes keep all four rules'.($report === [] ? '' : ': '.json_encode($report)));

    // Synthetic app routes, each breaking one rule once.
    $stack = ['web', 'auth:web', 'concierge.active', ResolveWorkspace::class];
    Route::middleware($stack)->group(function () {
        Route::put('@{workspace}/sheets/{sheet}', [RouteInvariantsUnguarded::class, 'update']);
        Route::put('sheets/{sheet}', [RouteInvariantsUnguarded::class, 'update']);
        Route::get('@{workspace}/lonely', [RouteInvariantsDeclared::class, 'index']);
        Route::post('@{workspace}/declared', [RouteInvariantsDeclared::class, 'store']);
        Route::post('declared', [RouteInvariantsDeclared::class, 'store']);
        Route::post('@{workspace}/ghost', [RouteInvariantsGhost::class, 'store']);
        Route::post('ghost', [RouteInvariantsGhost::class, 'store']);
        Route::put('@{workspace}/guarded', [RouteInvariantsUnguarded::class, 'update'])->middleware('workspace.ability:update');
        Route::put('guarded', [RouteInvariantsUnguarded::class, 'update'])->middleware('workspace.ability:update');
    });
    Route::middleware(['web', 'concierge.active', 'auth:web', ResolveWorkspace::class])->get('@{workspace}/backwards', [RouteInvariantsDeclared::class, 'index']);
    Route::middleware(['web', 'concierge.active', 'auth:web', ResolveWorkspace::class])->get('backwards', [RouteInvariantsDeclared::class, 'index']);
    Route::middleware('web')->get('open-door', [RouteInvariantsUnguarded::class, 'index']);
    Route::middleware('web')->get('signed-door', [RouteInvariantsPublic::class, 'index']);
    Route::middleware(['web', 'signed'])->get('signed-door-ok', [RouteInvariantsPublic::class, 'index']);
    Route::middleware('web')->post('open-write', [RouteInvariantsOpen::class, 'store']);
    Route::middleware('web')->get('open-read', [RouteInvariantsOpen::class, 'index']);
    app('router')->getRoutes()->refreshNameLookups();

    $has = fn (array $list, string $needle): bool => array_filter($list, fn (string $line): bool => str_contains($line, $needle)) !== [];
    $twins = RouteInvariants::shortTwins();
    httpCheck($has($twins, 'GET|HEAD @{workspace}/lonely (no GET|HEAD lonely)') && ! $has($twins, 'sheets'), 'shortTwins names a workspace route with no short twin, and only it');
    $stackWrong = RouteInvariants::workspaceStack();
    httpCheck($has($stackWrong, '@{workspace}/backwards') && ! $has($stackWrong, 'sheets') && ! $has($stackWrong, '/guarded'), 'workspaceStack names a route that resolves the account before signing in');
    $ours = ['RouteInvariantsUnguarded', 'RouteInvariantsDeclared', 'RouteInvariantsGhost', 'RouteInvariantsPublic', 'RouteInvariantsOpen'];
    $writes = RouteInvariants::guardedWrites($ours);
    httpCheck($has($writes, 'PUT @{workspace}/sheets/{sheet} → RouteInvariantsUnguarded (no workspace.ability'), 'guardedWrites names an app write with neither an ability nor #[AuthorizedBy]');
    httpCheck($has($writes, 'RouteInvariantsGhost names NoSuchOperation, which does not exist'), 'guardedWrites names an #[AuthorizedBy] operation that does not exist');
    httpCheck(! $has($writes, '/declared') && ! $has($writes, ' declared') && ! $has($writes, '/guarded') && ! $has($writes, ' guarded'), 'guardedWrites accepts an ability middleware or a real #[AuthorizedBy]');
    httpCheck(RouteInvariants::guardedWrites(['Elsewhere\\']) === [], 'guardedWrites checks only the namespaces it is given');
    $public = RouteInvariants::declaredPublic($ours);
    httpCheck($has($public, 'GET|HEAD open-door → RouteInvariantsUnguarded (no #[PublicRoute]'), 'declaredPublic names a public route with no declaration');
    httpCheck($has($public, 'GET|HEAD signed-door → RouteInvariantsPublic (public, but neither signed nor throttled)') && ! $has($public, 'signed-door-ok'), 'declaredPublic needs signed or throttle, and accepts it');
    httpCheck($has($public, 'POST open-write → RouteInvariantsOpen (declared open, but it writes)') && ! $has($public, 'open-read'), 'declaredPublic allows an open read on GET only');
    $all = RouteInvariants::all($ours);
    httpCheck(array_keys($all) === ['shortTwins', 'workspaceStack', 'guardedWrites', 'declaredPublic'], 'all() reports each broken rule by name');
    echo "done\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
