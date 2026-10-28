<?php

// Invited sign-up end to end: the kit's password policy and its plain breach message, one email in all (the invitation proves the address, so nothing asks
// to verify it again), every invite path verifying the invited address, and the post-sign-up passkey offer. SQLite memory, real Concierge, Fortify and
// Inertia, synthetic users; the breach check is a local fake, never the network.
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
    $base = sys_get_temp_dir().'/kit-greeting-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true], 'mcp' => ['ui' => true]],
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

    // The policy: 8 characters and not breached, no composition rules; one source, Password::defaults().
    $applied = Illuminate\Validation\Rules\Password::defaults()->appliedRules();
    httpCheck($applied['min'] === 8 && $applied['uncompromised'] === true && ! $applied['mixedCase'] && ! $applied['letters'] && ! $applied['numbers'] && ! $applied['symbols'], 'Password::defaults() is min(8)->uncompromised() with no composition rules');
    httpCheck(Tey\Kit\Features\SessionUi\PasswordPolicy::client() === ['min' => 8, 'uncompromised' => true], 'the page is told the same rules (kitPassword)');

    // An invited sign-up: the password rules, the plain breach message, then one email in all and a verified address.
    // The kit delivers invitations only with a real mailer configured; here the fakes catch every message.
    $config->set('concierge.invitations.deliver', true);
    $token = $invite('new@example.test');
    httpCheck(count($sent()) === 1, 'the invitation is the one email so far');
    [$status, $body] = $send(null, 'POST', '/invitations/'.$token.'/account', ['name' => 'New', 'password' => 'short', 'password_confirmation' => 'short']);
    httpCheck($status === 422 && str_contains(json_encode($body['errors']['password'] ?? []), '8 characters'), 'a password under 8 characters is refused');
    [$status, $body] = $send(null, 'POST', '/invitations/'.$token.'/account', ['name' => 'New', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery']);
    httpCheck($status === 422 && ($body['errors']['password'][0] ?? null) === 'This password has appeared in a data breach, so it isn\'t safe. Pick another.', 'a breached password gets the plain message, from kit::validation');
    [$status, $body] = $send(null, 'POST', '/invitations/'.$token.'/account', ['name' => 'New', 'password' => 'alllowercaseletters', 'password_confirmation' => 'alllowercaseletters']);
    $created = HttpUser::query()->where('email', 'new@example.test')->first();
    httpCheck($status === 201 && $created !== null && $created->email_verified_at !== null, 'an all-lowercase 19-character password is accepted (no composition rules), and the new account is verified');
    event(new Illuminate\Auth\Events\Registered($created));
    $all = $sent();
    httpCheck(count($all) === 1 && ! in_array(Illuminate\Auth\Notifications\VerifyEmail::class, $all, true), 'an invited sign-up sends exactly one email in all: the invitation, and no verification email even when Registered fires ('.implode(', ', $all).')');

    // Every invite path verifies the invited address, through Concierge (0.8.3): an existing, unverified account accepting a member invitation, and a workspace (tenant) invitation.
    $unverified = HttpUser::create(['name' => 'Late', 'email' => 'late@example.test']);
    $token = $invite('late@example.test');
    [$status, $body] = $send($unverified, 'GET', '/invitations/'.$token);
    httpCheck(Tey\Concierge\Actions\InspectInvitation::run($unverified, $token)->state === Tey\Concierge\Enums\InvitationState::Ready && $unverified->fresh()->email_verified_at === null, 'opening a member invitation signed in as the invited, unverified address shows it ready to accept, not "verify your email"');
    [$status] = $send($unverified, 'POST', '/invitations/'.$token);
    httpCheck($unverified->fresh()->email_verified_at !== null, 'accepting it verifies the invited address (Concierge)');
    $other = HttpUser::create(['name' => 'Other', 'email' => 'other@example.test']);
    $token = $invite('another@example.test');
    $send($other, 'GET', '/invitations/'.$token);
    $send($other, 'POST', '/invitations/'.$token);
    httpCheck($other->fresh()->email_verified_at === null, 'an account with a different address is never verified by someone else\'s invitation');
    $direct = HttpUser::create(['name' => 'Direct', 'email' => 'direct@example.test']);
    $token = $invite('direct@example.test');
    [$status] = $send($direct, 'POST', '/invitations/'.$token);
    httpCheck($direct->fresh()->email_verified_at !== null && ConciergeModels::membership()::query()->where('user_id', $direct->id)->where('workspace_id', $workspace->id)->exists(), 'accepting a member invitation directly verifies the invited address and joins');
    $config->set('concierge.tenant_invitations.platform_owners', [$owner->email]);
    Illuminate\Support\Facades\Gate::define(Tey\Concierge\Support\PlatformOwners::ability(), fn ($actor) => Tey\Concierge\Support\PlatformOwners::listed($actor));
    $tenantUser = HttpUser::create(['name' => 'Tenant', 'email' => 'tenant@example.test']);
    try {
        $issued = Tey\Concierge\Actions\InviteTenant::run($owner, 'tenant@example.test');
        $result = Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation::run(new Tey\Kit\Features\Core\Principal((int) $tenantUser->id), $issued->token);
        httpCheck($tenantUser->fresh()->email_verified_at !== null && isset($result['workspace_id']), 'accepting a workspace (tenant) invitation verifies the invited address and provisions the workspace');
    } catch (Throwable $error) {
        throw new RuntimeException('tenant invitation path: '.$error->getMessage(), previous: $error);
    }

    // Changing the password: the same policy and message.
    $created->forceFill(['password' => Illuminate\Support\Facades\Hash::make('alllowercaseletters')])->save();
    try {
        app(Tey\Kit\Features\SessionUi\Fortify\UpdatePassword::class)->update($created, ['current_password' => 'alllowercaseletters', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery']);
        $message = null;
    } catch (Illuminate\Validation\ValidationException $error) {
        $message = $error->errors()['password'][0] ?? null;
    }
    httpCheck($message === 'This password has appeared in a data breach, so it isn\'t safe. Pick another.', 'changing the password uses the same policy and plain message');

    // Password reset by emailed link (Fortify's resetPasswords, on in the kit's defaults): the kit's pages, the same policy, the same message.
    httpCheck(in_array(Laravel\Fortify\Features::resetPasswords(), (array) config('fortify.features'), true), 'the kit turns on Fortify\'s password reset');
    $config->set('auth.passwords', ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 0]]);
    Schema::create('password_reset_tokens', fn ($table) => [$table->string('email')->primary(), $table->string('token'), $table->timestamp('created_at')->nullable()]);
    $page = function (string $path) use ($app): array {
        Auth::guard('web')->logout();
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $app->instance('request', $request);
        $response = $app['router']->dispatch($request);

        return json_decode((string) $response->getContent(), true) ?? [];
    };
    httpCheck(($page('/forgot-password')['component'] ?? null) === 'Auth/ForgotPassword/Page', 'asking for a reset link is the kit\'s Auth/ForgotPassword page');
    $before = count($sent());
    $send(null, 'POST', '/forgot-password', ['email' => $created->email]);
    httpCheck(count($sent()) === $before + 1 && in_array(Illuminate\Auth\Notifications\ResetPassword::class, $sent(), true), 'it emails one reset link');
    $resetToken = Illuminate\Support\Facades\Password::broker()->createToken($created);
    $reset = $page('/reset-password/'.$resetToken.'?email='.urlencode($created->email));
    httpCheck(($reset['component'] ?? null) === 'Auth/ResetPassword/Page' && ($reset['props']['token'] ?? null) === $resetToken && ($reset['props']['email'] ?? null) === $created->email, 'the emailed link opens the kit\'s Auth/ResetPassword page with its token and address');
    [$status, $body] = $send(null, 'POST', '/reset-password', ['token' => $resetToken, 'email' => $created->email, 'password' => 'short', 'password_confirmation' => 'short']);
    httpCheck($status === 422 && str_contains(json_encode($body['errors']['password'] ?? []), '8 characters'), 'a reset under 8 characters is refused');
    [$status, $body] = $send(null, 'POST', '/reset-password', ['token' => $resetToken, 'email' => $created->email, 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery']);
    httpCheck($status === 422 && ($body['errors']['password'][0] ?? null) === 'This password has appeared in a data breach, so it isn\'t safe. Pick another.', 'a breached reset gets the plain message');
    [$status, $body] = $send(null, 'POST', '/reset-password', ['token' => $resetToken, 'email' => $created->email, 'password' => 'a brand new one', 'password_confirmation' => 'a brand new one']);
    httpCheck($status === 200 && Illuminate\Support\Facades\Hash::check('a brand new one', (string) $created->fresh()->password), 'a reset under the policy saves the new password');

    // The passkey offer: only where Fortify passkeys are on and the User can hold one; offered after sign-up, gone once dismissed.
    httpCheck(Tey\Kit\Features\SessionUi\PasskeyOffer::shared($created) === null, 'no passkey offer where the app has no passkeys');
    $config->set('fortify.features', [Laravel\Fortify\Features::passkeys()]);
    $holder = PasskeyHttpUser::query()->findOrFail($created->id);
    Tey\Kit\Features\SessionUi\PasskeyOffer::offer($holder);
    Schema::create('passkeys', fn ($table) => [$table->id(), $table->unsignedBigInteger('user_id'), $table->timestamps()]);
    $shared = Tey\Kit\Features\SessionUi\PasskeyOffer::shared($holder);
    httpCheck(($shared['options_url'] ?? null) === 'https://kit.test/user/passkeys/options' && ($shared['store_url'] ?? null) === 'https://kit.test/user/passkeys', 'after an invited sign-up the offer leads to Fortify\'s passkey registration');
    [$status] = $send($holder, 'DELETE', '/me/passkey-offer');
    httpCheck(in_array($status, [204, 302], true) && Tey\Kit\Features\SessionUi\PasskeyOffer::shared($holder) === null, '"Not now" dismisses the offer for good');
    echo "Onboarding checks on SQLite memory with real Concierge, Fortify and Inertia; the breach check is a local fake. No browser: the rules list and the passkey ceremony are checked in test:onboarding-js.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
