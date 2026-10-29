<?php

// Auth-only: session-ui without workspace-ui. The sign-in and password reset pages work, sign-in lands on the app's own home (kit.home), and none of the
// workspace, account or invitation pages is there. SQLite memory, real Concierge, Fortify and Inertia, synthetic users; no network.
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
use Illuminate\Routing\Route;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Fortify\PasskeyAuthenticatable;
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
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Http\Middleware\RequireActiveMembership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider;
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
    $base = sys_get_temp_dir().'/kit-auth-only-'.bin2hex(random_bytes(4));
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

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $owner = HttpUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'email_verified_at' => now()]);
    ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Concierge::class)->context()->run($workspace, fn () => $owner->assignRole('owner'), [$owner]);
    $sync->run(new ArrayInput([]), new NullOutput);
    $workspace->refresh();

    $send = function (?object $user, string $method, string $path, array $data = []) use ($app): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $request = Request::create('https://kit.test'.$path, $method, $data, server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? (string) $response->getContent()];
    };
    $invite = function (string $email) use ($owner, $workspace): string {
        // Inside the workspace, as the People page does: the delivery job is tenant-aware.
        return $workspace->execute(fn () => app(Concierge::class)->context()->run($workspace, fn () => InviteMember::run($owner, $workspace, $email, new GrantSet(['member'])), [$owner]))->token;
    };

    $page = function (?object $user, string $path) use ($app): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };

    // Only session-ui's own pages are here.
    httpCheck($app->getProvider(SessionUiProvider::class) !== null && $app->getProvider(WorkspaceUiProvider::class) === null && ! $registry->adopts('workspace-ui'), 'session-ui is adopted without workspace-ui');
    httpCheck(($page(null, '/login')[1]['component'] ?? null) === 'Auth/Login/Page', 'GET /login is the kit\'s Auth/Login page');
    httpCheck(($page(null, '/forgot-password')[1]['component'] ?? null) === 'Auth/ForgotPassword/Page', 'GET /forgot-password is the kit\'s Auth/ForgotPassword page');
    $member = HttpUser::create(['name' => 'Member', 'email' => 'member@example.test', 'email_verified_at' => now(), 'password' => Hash::make('a long passphrase')]);
    (require __DIR__.'/fixtures/confirm-password.php')($app, $member, 'httpCheck', 'auth-only: ');
    foreach (['/me', '/me/profile', '/me/security', '/no-workspace', '/@acme', '/@acme/settings'] as $path) {
        // As the workspace's owner, so a 404 is the missing page, never a refused workspace.
        httpCheck($page($owner, $path)[0] === 404, 'GET '.$path.' is not there for the workspace\'s owner (404)');
    }
    httpCheck($app['router']->getRoutes()->getByName('home') === null && $page($owner, '/')[0] === 404, 'the kit has no home route: "/" is left to the app');

    // Sign-in lands on the app's own home: Fortify's home is kit.home ("/").
    Auth::guard('web')->logout();
    $request = Request::create('https://kit.test/login', 'POST', ['email' => 'member@example.test', 'password' => 'a long passphrase']);
    $request->setLaravelSession($session = $app['session']->driver());
    $app->instance('request', $request);
    $response = $app['router']->dispatch($request);
    httpCheck($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test' && Auth::guard('web')->id() === $member->id, 'a successful sign-in redirects to the app\'s own home, "/" ('.$response->getStatusCode().' '.$response->headers->get('Location').')');
    httpCheck(Fortify::redirects('login') === '/' && config('fortify.home') === config('kit.home'), 'Fortify\'s home is kit.home');

    // The invitation link is workspace-ui's since 0.6.0: without it there is no invitation page, and an invitation's link goes nowhere the kit serves.
    $token = $invite('member@example.test');
    httpCheck($page($member, '/invitations/'.$token)[0] === 404 && $app['router']->getRoutes()->getByName('invitations.show') === null, 'without workspace-ui there is no invitation page (404): it is workspace-ui\'s');
    // A member of the workspace (joined as the People page would add them), for the removed-member redirect below.
    ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    // The welcome steps: the person's timezone and photo, never the workspace's (its page is workspace-ui's).
    Onboarding::context((int) $owner->id, (int) $workspace->id);
    httpCheck(array_column(Onboarding::steps(), 'id') === ['timezone', 'photo'], 'the welcome steps are the timezone and the photo, even for a workspace\'s owner');

    // No link reaches a workspace-ui page: the chooser, the no-workspace page and the account pages all fall back to the app's home.
    $location = function (?object $user, string $path) use ($app): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $request = Request::create('https://kit.test'.$path, 'GET');
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        $response = $app['router']->dispatch($request);

        return [$response->getStatusCode(), $response->headers->get('Location')];
    };
    httpCheck(! str_contains((string) $app['router']->getRoutes()->getByName('workspaces.index')?->getActionName(), 'WorkspaceController') && $location($owner, '/me/workspaces') === [302, 'https://kit.test'], 'GET /me/workspaces redirects to the app\'s home, not a workspace chooser page');
    httpCheck(in_array($location($owner, '/workspaces')[0], [301, 302], true) && $location($owner, '/workspaces')[1] === 'https://kit.test', 'the old /workspaces address lands on the app\'s home');
    httpCheck(WorkspaceUrl::chooser() === '/' && WorkspaceUrl::authOnly(), 'the chooser is kit.home');
    httpCheck(config('concierge.workspaces.no_workspace_url') === '/', 'Concierge\'s no-workspace address is kit.home');
    ConciergeModels::membership()::query()->where('user_id', $member->id)->update(['status' => MembershipStatus::Removed]);
    $request = Request::create('https://kit.test/anything', 'GET');
    $request->setUserResolver(fn () => $member);
    $request->setLaravelSession($app['session']->driver());
    Auth::guard('web')->setUser($member);
    $app->instance('request', $request);
    $redirect = app(Concierge::class)->context()->run($workspace, fn () => app(RequireActiveMembership::class)->handle($request, fn () => response('through')), [$member]);
    httpCheck($redirect->getStatusCode() === 302 && $redirect->headers->get('Location') === 'https://kit.test', 'Concierge\'s redirect for a removed member lands on the app\'s home ('.$redirect->headers->get('Location').')');
    // The shell the app's own pages get (shared as `shell`), and the focus shell on the welcome and invitation pages.
    $shellFor = function (object $user, string $path, ?string $name = null) use ($app): object {
        $request = Request::create('https://kit.test'.$path, 'GET');
        $request->setUserResolver(fn () => $user);
        if ($name !== null) {
            $request->setRouteResolver(fn () => (new Route('GET', ltrim($path, '/'), []))->name($name));
        }
        $app->instance('request', $request);
        Auth::guard('web')->setUser($user);

        return app(PageShell::class)->forRequest($request);
    };
    $shell = json_decode(json_encode($shellFor($owner, '/')), true);
    $urls = json_encode($shell, JSON_UNESCAPED_SLASHES);
    httpCheck($shell['links']['home'] === '/' && $shell['links']['switcher'] === '/' && $shell['product']['url'] === '/' && $shell['account']['url'] === '/' && $shell['user']['items'] === [] && ($shell['workspace']['all']['url'] ?? '/') === '/', 'the app shell links home, the switcher, the product, the account and "All workspaces" to kit.home, with no account-settings item');
    httpCheck(! str_contains($urls, '/me/workspaces"') && ! str_contains($urls, '"/me"') && ! str_contains($urls, '/me/profile') && ! str_contains($urls, '/no-workspace'), 'no shared shell link points at /me/workspaces, /me, /me/profile or /no-workspace');
    $focus = json_decode(json_encode($shellFor($owner, '/welcome', 'focus.welcome')), true);
    httpCheck(($focus['product']['url'] ?? null) === '/' && ! str_contains(json_encode($focus, JSON_UNESCAPED_SLASHES), '/me/workspaces') && ! str_contains(json_encode($focus, JSON_UNESCAPED_SLASHES), '/no-workspace'), 'the focus shell (welcome, invitation) links the product to kit.home');
    echo "Auth-only checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
