<?php

// Auth-only: session-ui without workspace-ui. The sign-in, password reset and invitation pages work, sign-in and an accepted invitation land on the app's own
// home (kit.home), and none of the workspace or account pages is there. SQLite memory, real Concierge, Fortify and Inertia, synthetic users; no network.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function httpCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class HttpUser extends Authenticatable implements Member, Illuminate\Contracts\Auth\MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

/** A host User that can hold passkeys (session-ui's optional `passkeys` integration). */
class PasskeyHttpUser extends HttpUser implements Laravel\Fortify\Contracts\PasskeyUser
{
    use Laravel\Fortify\PasskeyAuthenticatable;

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
    $app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'session-ui'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true]],
        'concierge' => ['invitations' => ['deliver' => true]], 'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Events\EventServiceProvider::class, Illuminate\Foundation\Providers\FoundationServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class, Illuminate\Notifications\NotificationServiceProvider::class, Illuminate\Mail\MailServiceProvider::class, Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);
    // The kit's lang, as KitServiceProvider loads it: the plain breach message comes from kit::validation.
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);
    $app['router']->aliasMiddleware('guest', Illuminate\Auth\Middleware\RedirectIfAuthenticated::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
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
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);


    // The breach check is a local fake: one known-breached password, never the network.
    $app->instance(Illuminate\Contracts\Validation\UncompromisedVerifier::class, new class implements Illuminate\Contracts\Validation\UncompromisedVerifier {
        public function verify($data)
        {
            return $data['value'] !== 'correct horse battery';
        }
    });
    // The host's usual listener: a Registered user who isn't verified is sent Laravel's verification email.
    $app['events']->listen(Illuminate\Auth\Events\Registered::class, Illuminate\Auth\Listeners\SendEmailVerificationNotification::class);
    $notifications = Illuminate\Support\Facades\Notification::fake();
    $mail = Illuminate\Support\Facades\Mail::fake();
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
    app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => $owner->assignRole('owner'), [$owner]);
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
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
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? (string) $response->getContent()];
    };
    $invite = function (string $email) use ($owner, $workspace): string {
        // Inside the workspace, as the People page does: the delivery job is tenant-aware.
        return $workspace->execute(fn () => app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => Tey\Concierge\Actions\InviteMember::run($owner, $workspace, $email, new Tey\Concierge\Support\GrantSet(['member'])), [$owner]))->token;
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
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };

    // Only session-ui's own pages are here.
    httpCheck($app->getProvider(Tey\Kit\Features\SessionUi\SessionUiProvider::class) !== null && $app->getProvider(Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider::class) === null && ! $registry->adopts('workspace-ui'), 'session-ui is adopted without workspace-ui');
    httpCheck(($page(null, '/login')[1]['component'] ?? null) === 'Auth/Login/Page', 'GET /login is the kit\'s Auth/Login page');
    httpCheck(($page(null, '/forgot-password')[1]['component'] ?? null) === 'Auth/ForgotPassword/Page', 'GET /forgot-password is the kit\'s Auth/ForgotPassword page');
    $member = HttpUser::create(['name' => 'Member', 'email' => 'member@example.test', 'email_verified_at' => now(), 'password' => Illuminate\Support\Facades\Hash::make('a long passphrase')]);
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
    httpCheck(Laravel\Fortify\Fortify::redirects('login') === '/' && config('fortify.home') === config('kit.home'), 'Fortify\'s home is kit.home');

    // An invitation: its page renders (its trail has no kit home above it), and accepting it lands on the app's home, after the welcome steps for a first-timer.
    $token = $invite('member@example.test');
    [$status, $body] = $page($member, '/invitations/'.$token);
    httpCheck($status === 200 && ($body['component'] ?? null) === 'Auth/Invitation/Show/Page', 'the invitation page renders');
    Tey\Kit\Features\SessionUi\UserState::set((int) $member->id, 'onboarding.done');
    [$status, $body] = $send($member, 'POST', '/invitations/'.$token);
    httpCheck($status === 201 && ($body['redirect'] ?? null) === '/' && ConciergeModels::membership()::query()->where('user_id', $member->id)->where('workspace_id', $workspace->id)->exists(), 'accepting it joins the workspace and lands on the app\'s home, not a workspace dashboard ('.json_encode($body).')');
    $first = HttpUser::create(['name' => 'First', 'email' => 'first@example.test', 'email_verified_at' => now()]);
    $token = $invite('first@example.test');
    Auth::guard('web')->logout();
    Auth::guard('web')->setUser($first);
    $request = Request::create('https://kit.test/invitations/'.$token, 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $request->setLaravelSession($session = $app['session']->driver());
    $request->setUserResolver(fn () => $first);
    $app->instance('request', $request);
    $body = json_decode((string) $app['router']->dispatch($request)->getContent(), true);
    httpCheck(($body['redirect'] ?? null) === '/welcome' && $session->get(Tey\Kit\Features\SessionUi\Http\WelcomeController::SESSION_NEXT) === '/', 'a first-timer goes through the welcome steps first, which end on the app\'s home');
    echo "Auth-only checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
