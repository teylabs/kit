<?php

// The kit's home (kit.home, workspace-ui's HomeController): one workspace is its dashboard; several go to the workspace last used while the person is still an
// active member who may open it, else the workspace list; a stale session workspace never leaks; a guest signs in first; and sign-in lands there.
// SQLite memory, real Concierge, Fortify and Inertia, synthetic users and workspaces (Northwind Farms, Acme Travel); no network.
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
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Fortify;
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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;
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

class HttpUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-home-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true]],
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

    $provision = function (string $name, string $slug) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $join = function (HttpUser $user, Workspace $workspace) use ($sync): void {
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $user->assignRole('owner'), [$user]);
        $sync->run(new ArrayInput([]), new NullOutput);
    };
    $northwind = $provision('Northwind Farms', 'northwind');
    $acme = $provision('Acme Travel', 'acme');
    $solo = HttpUser::create(['name' => 'Solo Person', 'email' => 'solo@example.test', 'email_verified_at' => now()]);
    $join($solo, $northwind);
    $many = HttpUser::create(['name' => 'Many Person', 'email' => 'many@example.test', 'email_verified_at' => now(), 'password' => Hash::make('a long passphrase')]);
    $join($many, $northwind);
    $join($many, $acme);

    // One request through the router, with a session the array handler keeps between requests (cookie `s`): [status, Location, Inertia component, session id].
    $handler = $app['session']->driver()->getHandler();
    $send = function (?object $user, string $method, string $path, array $session = [], array $data = []) use ($app, $handler): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        // The session manager keeps one store, which would merge the last request's attributes into this one: emptied first.
        $app['session']->driver()->flush();
        $id = bin2hex(random_bytes(20));
        $handler->write($id, serialize(['_token' => 'token', ...$session]));
        $request = Request::create('https://kit.test'.$path, $method, $data, ['s' => $id], server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => Auth::guard('web')->user());
        $app->instance('request', $request);
        RequestMemo::instance()->flush();
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response->headers->get('Location'), json_decode((string) $response->getContent(), true)['component'] ?? null, $request->hasSession() ? $request->session()->getId() : null];
    };
    $home = fn (?object $user, array $session = []): array => array_slice($send($user, 'GET', '/', $session), 0, 3);
    $list = [302, 'https://kit.test/me/workspaces', null];

    // One workspace: "/" is its dashboard, as before.
    [$status, , $component] = $home($solo);
    httpCheck($status === 200 && $component === 'Workspace/Dashboard/Page', 'one workspace: "/" renders its dashboard ('.$status.' '.$component.')');

    // Several, with a current one: "/" sends the person to it.
    httpCheck($home($many, ['kit' => ['workspace' => $acme->id]]) === [302, 'https://kit.test/@acme', null], 'several workspaces with Acme Travel current: "/" redirects to /@acme ('.json_encode($home($many, ['kit' => ['workspace' => $acme->id]])).')');
    httpCheck($home($many, ['kit' => ['workspace' => $northwind->id]]) === [302, 'https://kit.test/@northwind', null], 'several workspaces with Northwind Farms current: "/" redirects to /@northwind');
    // Opening a workspace page is what makes it current: the session's kit.workspace, kept by ResolveWorkspace.
    $opened = $send($many, 'GET', '/@acme');
    httpCheck($opened[0] === 200 && $handler->read($opened[3]) !== '' && data_get(unserialize($handler->read($opened[3])), 'kit.workspace') === $acme->id, 'opening /@acme makes it the session\'s current workspace (kit.workspace)');

    // Several, none current: the list.
    httpCheck($home($many) === $list, 'several workspaces, none current: "/" redirects to /me/workspaces');

    // A workspace the person is not a member of, named in the session: never followed.
    $other = $provision('Other Org', 'other-org');
    httpCheck($home($many, ['kit' => ['workspace' => $other->id]]) === $list, 'a session workspace the person was never a member of falls back to the list');
    httpCheck($home($many, ['kit' => ['workspace' => 999999]]) === $list, 'a session workspace that does not exist falls back to the list');

    // Stale memberships: suspended, then removed. Each falls back to the list and never names the workspace.
    $membership = fn (): object => ConciergeModels::membership()::query()->where('user_id', $many->id)->where('workspace_id', $acme->id)->firstOrFail();
    $third = $provision('Third Co', 'third-co');
    $join($many, $third);
    foreach ([MembershipStatus::Suspended, MembershipStatus::Removed] as $stale) {
        $membership()->update(['status' => $stale]);
        httpCheck($home($many, ['kit' => ['workspace' => $acme->id]]) === $list, 'a '.$stale->value.' membership in the session\'s workspace falls back to the list');
    }
    $membership()->update(['status' => MembershipStatus::Active]);
    httpCheck($home($many, ['kit' => ['workspace' => $acme->id]]) === [302, 'https://kit.test/@acme', null], 'restored to active, it is followed again');

    // Not admitted: still an active member, but the workspace's pages refuse (admission_required), so "/" does not send the person there.
    $acme->forceFill(['admitted' => false])->save();
    httpCheck($home($many, ['kit' => ['workspace' => $acme->id]]) === $list, 'a workspace that is not admitted falls back to the list');
    $acme->forceFill(['admitted' => true])->save();

    // Deleted: the row is gone.
    $third->delete();
    httpCheck($home($many, ['kit' => ['workspace' => $third->id]]) === $list, 'a deleted session workspace falls back to the list');

    // The dashboard turned off is not a refusal of the workspace: "/" still sends the person to its root, which opens its first page (test:workspace-root).
    app(Tey\Kit\Features\Access\Capabilities::class)->setForWorkspace('workspace.dashboard', $acme, false);
    try {
        httpCheck($home($many, ['kit' => ['workspace' => $acme->id]]) === [302, 'https://kit.test/@acme', null], 'dashboard off: "/" still sends a person with several workspaces to their last one, not the list ('.json_encode($home($many, ['kit' => ['workspace' => $acme->id]])).')');
        $root = $send($many, 'GET', '/@acme');
        httpCheck($root[0] === 302 && $root[1] !== 'https://kit.test/@acme', 'and that root opens another page instead of a 404 ('.json_encode(array_slice($root, 0, 2)).')');
    } finally {
        app(Tey\Kit\Features\Access\Capabilities::class)->setForWorkspace('workspace.dashboard', $acme, null);
    }

    // A guest signs in first, as before.
    httpCheck($home(null)[0] === 302 && $home(null)[1] === 'https://kit.test/me/workspaces', 'a guest at "/" is redirected to /me/workspaces ('.json_encode($home(null)).')');

    // Sign-in lands on kit.home, which sends the person to the workspace they last used.
    httpCheck(Fortify::redirects('login') === '/' && config('fortify.home') === config('kit.home'), 'with workspace-ui, Fortify\'s home is kit.home');
    [$status, $location, , $session] = $send(null, 'POST', '/login', ['kit' => ['workspace' => $acme->id]], ['email' => 'many@example.test', 'password' => 'a long passphrase']);
    httpCheck($status === 302 && $location === 'https://kit.test' && Auth::guard('web')->id() === $many->id, 'signing in redirects to kit.home ('.$status.' '.$location.')');
    $after = unserialize($handler->read($session));
    httpCheck($home($many, $after) === [302, 'https://kit.test/@acme', null], 'and kit.home, with the signed-in session, redirects to the last-used workspace, /@acme');
    // The logo (shell.product.url) and links.home: kit.home for a signed-in person, whose resolver picks the workspace; a guest's shell is as before.
    $shellFor = function (?object $user) use ($app): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $request = Request::create('https://kit.test/me', 'GET');
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        RequestMemo::instance()->flush();

        return json_decode(json_encode(app(PageShell::class)->forRequest($request)), true);
    };
    $shell = $shellFor($many);
    httpCheck($shell['product']['url'] === '/' && $shell['links']['home'] === '/' && $shell['links']['switcher'] === '/me/workspaces', 'several workspaces: the logo and links.home are kit.home ("/"); the switcher stays /me/workspaces ('.json_encode([$shell['product']['url'], $shell['links']['home']]).')');
    $config->set('kit.home', '/app');
    $shell = $shellFor($many);
    httpCheck($shell['product']['url'] === '/app' && $shell['links']['home'] === '/app', 'with kit.home at /app, the logo and links.home follow it');
    $config->set('kit.home', '/');
    $guest = $shellFor(null);
    httpCheck(($guest['kind'] ?? null) === 'guest' && $guest['product']['url'] === '/me/workspaces', 'a guest\'s shell is unaffected: its product links /me/workspaces as before ('.($guest['product']['url'] ?? 'none').')');
    echo "Home checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
