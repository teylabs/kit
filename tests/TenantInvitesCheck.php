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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\PlatformOwners;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\SessionUiRoutes;
use Tey\Kit\Features\SessionUi\TenantInvites;
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
    $base = sys_get_temp_dir().'/kit-tenant-invite-'.bin2hex(random_bytes(4));
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

    Gate::define(PlatformOwners::ability(), fn ($actor) => PlatformOwners::listed($actor));
    $owner = HttpUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'email_verified_at' => now()]);
    // The host's provisioner names the workspace; the page is told the same name (TenantInvites::nameUsing).
    app(Concierge::class)->provisionWorkspaceUsing(fn ($user, $invitation) => Workspace::create(['name' => 'Studio '.$invitation->id, 'slug' => 'studio-'.$invitation->id]));
    TenantInvites::nameUsing(fn ($invitation): string => 'Studio '.$invitation->id);
    $invite = fn (string $email): array => [($issued = InviteTenant::run($owner, $email))->token, $issued->invitation];

    $send = function (?object $user, string $method, string $path, array $data = [], bool $json = false) use ($app): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $server = $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml'];
        $request = Request::create('https://kit.test'.$path, $method, $data, server: $server);
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();
        $body = json_decode((string) $response->getContent(), true) ?? (string) $response->getContent();

        return [$response->getStatusCode(), $body, $response, $request];
    };
    $protected = fn ($response): bool => str_contains((string) $response->headers->get('Cache-Control'), 'no-store') && str_contains((string) $response->headers->get('Cache-Control'), 'private') && $response->headers->get('Referrer-Policy') === 'no-referrer';
    $state = fn ($body): ?string => is_array($body) ? ($body['props']['state'] ?? null) : null;

    // Off by default: a host turns the group on.
    $routes = $config->get('kit.routes.session-ui');
    $config->set('kit.routes.session-ui', array_diff_key($routes, ['tenant-invitations' => true]));
    httpCheck(! SessionUiRoutes::on('tenant-invitations'), 'the tenant-invitations group is off unless a host turns it on');
    $config->set('kit.routes.session-ui', $routes);

    [$token, $invitation] = $invite('new@example.test');
    httpCheck(app(Concierge::class)->tenantInvitationUrl($invitation, $token) === 'https://kit.test/invite/'.$token, 'Concierge\'s tenant invitation emails link to /invite/{token}');
    httpCheck($send(null, 'GET', '/invite/'.substr($token, 0, 42))[0] === 404, 'a token of the wrong shape matches no route');

    // A guest with no account: register_required, the intended URL, the headers.
    [$status, $body, $response, $request] = $send(null, 'GET', '/invite/'.$token);
    httpCheck($status === 200 && $state($body) === 'register_required' && ($body['props']['invitation']['workspace'] ?? null) === 'Studio '.$invitation->id, 'a guest whose address has no account is asked to register, and sees the workspace\'s name');
    httpCheck($protected($response), 'the page is sent no-store, private and no-referrer');
    httpCheck($request->session()->get('url.intended') === 'https://kit.test/invite/'.$token, 'register_required puts url.intended back to the invitation');
    httpCheck(($body['props']['register_url'] ?? null) === 'https://kit.test/invite/'.$token.'/register', 'the account form posts to /invite/{token}/register');
    [$status, $body, $response] = $send(null, 'GET', '/invite/'.$token.'/register');
    httpCheck($status === 200 && $state($body) === 'register_required' && $protected($response), 'GET register shows the account form, protected');

    // Register: a verified account, signed in, the workspace provisioned and owned.
    [$status, $body] = $send(null, 'POST', '/invite/'.$token.'/register', ['name' => 'New', 'password' => 'short', 'password_confirmation' => 'short'], true);
    httpCheck($status === 422 && HttpUser::query()->where('email', 'new@example.test')->doesntExist(), 'a password the policy refuses creates nothing');
    [$status, $body, $response] = $send(null, 'POST', '/invite/'.$token.'/register', ['name' => 'New', 'password' => 'alllowercaseletters', 'password_confirmation' => 'alllowercaseletters'], true);
    $created = HttpUser::query()->where('email', 'new@example.test')->first();
    $workspace = Workspace::query()->where('slug', 'studio-'.$invitation->id)->first();
    httpCheck($status === 201 && $created?->email_verified_at !== null && Auth::guard('web')->id() === $created->id && $protected($response), 'register creates the invited account already verified and signs it in');
    httpCheck($workspace !== null && ($body['workspace_id'] ?? null) === $workspace->id && $invitation->fresh()->accepted_by_id === $created->id && ConciergeModels::membership()::query()->where(['user_id' => $created->id, 'workspace_id' => $workspace->id, 'status' => MembershipStatus::Active])->exists(), 'and accepts through the host\'s provisioner: the new workspace is theirs');
    [$status, , $response] = $send(null, 'POST', '/invite/'.$token.'/register', ['name' => 'Again', 'password' => 'alllowercaseletters', 'password_confirmation' => 'alllowercaseletters']);
    httpCheck($status === 302 && $response->headers->get('Location') === 'https://kit.test/invite/'.$token && HttpUser::query()->where('email', 'new@example.test')->count() === 1, 'register for any other state goes back to the invitation and creates nothing');
    httpCheck($send(null, 'GET', '/invite/'.$token)[1]['props']['state'] === 'accepted', 'a used invitation says so');

    // An existing account: sign in, come back, accept.
    $existing = HttpUser::create(['name' => 'Existing', 'email' => 'existing@example.test', 'email_verified_at' => now()]);
    [$token2, $invitation2] = $invite('existing@example.test');
    [$status, $body, , $request] = $send(null, 'GET', '/invite/'.$token2);
    httpCheck($state($body) === 'sign_in_required' && $request->session()->get('url.intended') === 'https://kit.test/invite/'.$token2, 'an address with an account is asked to sign in, and comes back to the invitation');
    [$status, , $response] = $send(null, 'GET', '/invite/'.$token2.'/register');
    httpCheck($status === 302 && $response->headers->get('Location') === 'https://kit.test/invite/'.$token2, 'GET register for sign_in_required goes back to the invitation');
    $stranger = HttpUser::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'email_verified_at' => now()]);
    httpCheck($state($send($stranger, 'GET', '/invite/'.$token2)[1]) === 'wrong_account', 'someone else signed in sees wrong_account');
    [$status, $body] = $send($stranger, 'POST', '/invite/'.$token2.'/accept', [], true);
    httpCheck($status === 409 && ($body['error'] ?? null) === 'wrong_account' && $invitation2->fresh()->accepted_at === null, 'and can\'t accept it');
    httpCheck($send(null, 'POST', '/invite/'.$token2.'/accept', [], true)[0] === 401, 'a guest can\'t accept');
    [$status, $body] = $send($existing, 'POST', '/invite/'.$token2.'/accept', [], true);
    httpCheck($status === 201 && $invitation2->fresh()->accepted_by_id === $existing->id, 'the invited account accepts, and the workspace is provisioned');

    // An unverified account comes back after verifying.
    $late = HttpUser::create(['name' => 'Late', 'email' => 'late@example.test']);
    [$token3] = $invite('late@example.test');
    [, $body, , $request] = $send($late, 'GET', '/invite/'.$token3);
    httpCheck(in_array($state($body), ['unverified', 'ready'], true) && ($state($body) !== 'unverified' || $request->session()->get('url.intended') === 'https://kit.test/invite/'.$token3), 'an unverified account comes back to the invitation after verifying');

    // Concierge's throttles, answered in words, still protected.
    [$token4] = $invite('throttle@example.test');
    for ($i = 0; $i < 30; $i++) {
        $send(null, 'GET', '/invite/'.$token4);
    }
    [$status, $body, $response] = $send(null, 'GET', '/invite/'.$token4);
    httpCheck($status === 429 && $state($body) === 'throttled' && ($body['props']['message'] ?? null) === 'Too many attempts with this invitation. Wait a minute, then try again.' && $protected($response) && $response->headers->has('Retry-After'), 'the 31st look in a minute is refused in words, protected, with Retry-After');
    [$status, $body] = $send(null, 'GET', '/invite/'.$token4, [], true);
    httpCheck($status === 429 && ($body['message'] ?? null) === 'Too many attempts with this invitation. Wait a minute, then try again.', 'an API client gets the same words as JSON');
    for ($i = 0; $i < 10; $i++) {
        $send(null, 'POST', '/invite/'.$token4.'/register', ['name' => 'X', 'password' => 'short', 'password_confirmation' => 'short'], true);
    }
    httpCheck($send(null, 'POST', '/invite/'.$token4.'/register', ['name' => 'X', 'password' => 'short', 'password_confirmation' => 'short'], true)[0] === 429, 'register posts take Concierge\'s accept limit');

    echo "Tenant invitation checks on SQLite memory with real Concierge, Fortify and Inertia. No browser: the page is kit's invitation page, which test:browser covers.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
