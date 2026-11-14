<?php

// The browser job's recorded account and session payloads (tests/fixtures/form-payloads, written by browser/run.mjs's Save tests) posted to the real routes,
// as the page sends them (X-Inertia, a JSON body, or multipart with the photo's own bytes), by a signed-in person on SQLite memory: Account › Profile and its
// photo and date & time, Password, Security (two-factor on, confirmed and off, recovery codes, passkeys renamed and removed, other sessions signed out), Delete account, the
// welcome flow's timezone and photo steps, and the notes wall as a member of a synthetic workspace. Each must answer with a redirect, no validation errors,
// and the values stored. Two values are the server's, not the page's, and are swapped in: the authenticator app's TOTP code (computed from the secret the
// enable step stored), and nothing else. A passkey's credential needs a real authenticator's attestation, so its fixture is checked for the field names
// laravel/passkeys validates and is refused only by the ceremony's verification, not as a malformed form. Real Concierge, Fortify, laravel/passkeys,
// medialibrary (a temp disk) and the database session driver; no network, no mail sent (the array mailer).
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
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
use Illuminate\Http\UploadedFile;
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
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\PasskeysServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use PragmaRX\Google2FA\Google2FA;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionRegistrar;
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
use Tey\Kit\Features\Core\Clock;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
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

class HttpUser extends Authenticatable implements Member, PasskeyUser
{
    use HasApiTokens, HasWorkspaces, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public function getForeignKey()
    {
        return 'user_id';
    }
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-account-payloads-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    // The app's namespace, which the framework reads from composer.json.
    file_put_contents($base.'/composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}');
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]], 'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        // The database driver: Security's "Sign out other sessions" lists and ends sessions only with it.
        'session' => ['driver' => 'database', 'connection' => null, 'table' => 'sessions', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'notes'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true], 'lifecycle' => ['account_delete' => true]],
        'fortify' => ['guard' => 'web', 'features' => [Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(), Features::passkeys()]],
        'fortify-options' => ['two-factor-authentication' => ['confirm' => true, 'confirmPassword' => false]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false], 'public' => ['driver' => 'local', 'root' => $base.'/storage/app/public', 'url' => 'https://kit.test/storage', 'visibility' => 'public', 'throw' => false]]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'platform@kit.test', 'name' => 'Kit']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        FoundationServiceProvider::class, EventServiceProvider::class, PasswordResetServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class, MailServiceProvider::class, NotificationServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, PasskeysServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class, MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('password.confirm', RequirePassword::class);

    // Real tables: the host's users (with Fortify's two-factor columns), password reset tokens, sessions, Concierge's, spatie's, passkeys', medialibrary's and the kit's own stubs.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });
    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passkeys/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
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
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);
    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });

    $password = 'correct horse battery staple';
    $people = [];
    foreach (['robin', 'dana', 'sam', 'casey'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name).' Vale', 'email' => $name.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole('member'), [$people[$name]]);
    }
    // Dana owns no workspace, so her account can be deleted. The workspace's owner is someone else.
    $owner = HttpUser::create(['name' => 'Owen Example', 'email' => 'owner@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
    ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Concierge::class)->context()->run($workspace, fn () => $owner->assignRole('owner'), [$owner]);
    $sync->run(new ArrayInput([]), new NullOutput);
    // This app's member role writes notes (the kit's defaults name only owner, admin and viewer), as in NotesCheck.
    $roles = app(PermissionRegistrar::class)->getRoleClass();
    $roles::query()->where('name', 'member')->get()->each(fn ($role) => $role->givePermissionTo(['workspace.notes.view', 'workspace.notes.manage']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $workspace->refresh();

    $fixture = fn (string $name): array => json_decode((string) file_get_contents(dirname(__DIR__).'/tests/fixtures/form-payloads/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    // A multipart field the browser recorded as { file, type, base64 }: the same bytes, as an uploaded file.
    $upload = function (array $part) use ($base): UploadedFile {
        $path = $base.'/upload-'.bin2hex(random_bytes(4));
        file_put_contents($path, base64_decode($part['base64'], true));

        return new UploadedFile($path, $part['file'], $part['type'], null, true);
    };
    // The page's write, as the browser sent it: an Inertia visit with the payload as its JSON body, or multipart (a photo) with its fields and files.
    // Each person keeps their own session row, so "Sign out other sessions" has a current one to keep.
    $sessions = [];
    $send = function (string $who, string $method, string $path, array $payload) use ($app, $people, $upload, &$sessions): array {
        Auth::guard('web')->logout();
        Auth::guard('web')->setUser($people[$who]);
        $url = 'https://kit.test'.$path;
        $files = array_map($upload, array_filter($payload, fn ($value) => is_array($value) && isset($value['base64'])));
        $fields = array_diff_key($payload, $files);
        $server = ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_REFERER' => $url, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) Chrome/130'];
        $request = $files === []
            ? Request::create($url, $method, server: [...$server, 'CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR))
            : Request::create($url, $method, $fields, files: $files, server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        // The person's own session, by its cookie, so StartSession resumes it (and the database driver keeps its row).
        if (isset($sessions[$who])) {
            $request->cookies->set('s', $sessions[$who]);
        }
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
            if ($response->getStatusCode() >= 500) {
                fwrite(STDERR, get_class($error).': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()."\n");
            }
        }
        if ($response->getStatusCode() >= 500 && ($response->exception ?? null) instanceof Throwable) {
            fwrite(STDERR, get_class($response->exception).': '.$response->exception->getMessage().' at '.$response->exception->getFile().':'.$response->exception->getLine()."\n");
        }
        Feature::flushCache();
        $store = $app['session']->driver();
        $sessions[$who] = $store->getId();
        $bag = $store->get('errors');

        return [$response->getStatusCode(), (string) $response->headers->get('Location'), $bag ? $bag->getBag('default')->toArray() : [], $response];
    };
    $redirected = fn (array $result, ?string $to = null): bool => in_array($result[0], [302, 303], true) && $result[2] === [] && ($to === null || $result[1] === $to);
    $describe = fn (array $result): string => ' (got '.$result[0].' '.$result[1].' '.json_encode($result[2]).')';
    $fresh = fn (string $who): HttpUser => HttpUser::query()->findOrFail($people[$who]->id);

    // Account › Profile: the name and email address (Fortify's update, the kit's UpdateProfile).
    $result = $send('robin', 'PUT', '/user/profile-information', $fixture('profile'));
    httpCheck($redirected($result), 'Profile: the recorded payload saves, redirecting back with no errors'.$describe($result));
    httpCheck($fresh('robin')->name === 'Robin Ashby' && $fresh('robin')->email === 'robin.ashby@example.test', 'Profile: the name and email address are stored');

    // Account › Profile's Date & time: the person's time zone (saved and confirmed, as the welcome step confirms it) and their own 12h or 24h in user_state,
    // or `workspace` to follow the workspace's again; the page then shows both.
    $clockRow = fn (string $who): ?string => DB::table('user_state')->where(['user_id' => $people[$who]->id, 'workspace_id' => 0, 'key' => 'clock'])->value('value');
    $zoneRow = fn (string $who): ?string => DB::table('user_state')->where(['user_id' => $people[$who]->id, 'workspace_id' => 0, 'key' => 'timezone'])->value('value');
    $result = $send('robin', 'PUT', '/me/profile/clock', $fixture('profile-clock'));
    httpCheck($redirected($result) && $clockRow('robin') === '24h' && $zoneRow('robin') === 'Europe/Amsterdam' && $clockRow('sam') === null && $zoneRow('sam') === null, 'Profile Date & time: the recorded payload saves Robin\'s time zone and 24h to user_state, redirecting back with no errors, and no one else\'s'.$describe($result));
    httpCheck(! Onboarding::pending($people['robin']->id, 'timezone'), 'Profile Date & time: the saved time zone counts as confirmed, so the welcome flow does not ask again');
    $page = json_decode((string) $send('robin', 'GET', '/me/profile', [])[3]->getContent(), true);
    httpCheck(($page['props']['clock'] ?? null) === '24h' && ($page['props']['timezone'] ?? null) === 'Europe/Amsterdam' && ($page['props']['clock_url'] ?? null) === '/me/profile/clock' && Clock::for($people['robin']) === '24h', 'Profile Date & time: the page shows Robin\'s time zone and 24h, and Clock::for(Robin) is 24h'.json_encode([$page['props']['timezone'] ?? null, $page['props']['clock'] ?? null]));
    // One refused request for both fields: every throttled route counts against the same per-person limit, which Security's later steps need.
    $result = $send('robin', 'PUT', '/me/profile/clock', ['timezone' => 'Mars/Olympus', 'clock' => 'auto']);
    httpCheck(isset($result[2]['clock'], $result[2]['timezone']) && $clockRow('robin') === '24h' && $zoneRow('robin') === 'Europe/Amsterdam', 'Profile Date & time: an unknown time zone and a clock other than workspace, 12h or 24h (auto) are each refused on their field, and neither saved value changes'.$describe($result));
    $result = $send('robin', 'PUT', '/me/profile/clock', ['clock' => 'workspace']);
    $page = json_decode((string) $send('robin', 'GET', '/me/profile', [])[3]->getContent(), true);
    httpCheck($redirected($result) && $clockRow('robin') === null && $zoneRow('robin') === 'Europe/Amsterdam' && ($page['props']['clock'] ?? null) === 'workspace' && Clock::preference($people['robin']) === 'auto', 'Profile Date & time: `workspace` forgets Robin\'s own clock (Use workspace setting, preference auto again); a save without a time zone keeps the saved one'.$describe($result));

    // Account › Profile's photo: the recorded file, multipart, as StoreAvatar takes it.
    $result = $send('robin', 'POST', '/me/avatar', $fixture('avatar'));
    httpCheck($redirected($result), 'Profile photo: the recorded upload saves, redirecting back with no errors'.$describe($result));
    httpCheck(DB::table('media')->where('model_id', $people['robin']->id)->where('collection_name', 'avatar')->count() === 1, 'Profile photo: the photo is stored as the avatar');

    // Account › Password (Fortify's update, the kit's UpdatePassword).
    $result = $send('robin', 'PUT', '/user/password', $fixture('password'));
    httpCheck($redirected($result), 'Password: the recorded payload saves, redirecting back with no errors'.$describe($result));
    httpCheck(Hash::check('a fresh long passphrase 42', (string) $fresh('robin')->password), 'Password: the new password is stored');
    $people['robin']->password = $fresh('robin')->password;
    DB::table('users')->where('id', $people['robin']->id)->update(['password' => Hash::make($password)]);
    $people['robin']->refresh();

    // Security › two-factor: on (the password), confirmed (the app's code), recovery codes regenerated, then off.
    $result = $send('robin', 'POST', '/me/security/two-factor', $fixture('two-factor-enable'));
    httpCheck($redirected($result), 'Two-factor, Turn on: the recorded payload saves, redirecting back with no errors'.$describe($result));
    $people['robin']->refresh();
    httpCheck($people['robin']->two_factor_secret !== null && $people['robin']->two_factor_confirmed_at === null, 'Two-factor, Turn on: a secret is stored, waiting for a code');
    $confirm = $fixture('two-factor-confirm');
    httpCheck(array_keys($confirm) === ['code'], 'Two-factor, Confirm: the page posts the code alone');
    // The one server-issued value: the app's code for the stored secret, typed as the person did (with a space), in place of the recorded one.
    $code = app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($people['robin']->two_factor_secret));
    $result = $send('robin', 'PUT', '/me/security/two-factor', ['code' => substr($code, 0, 3).' '.substr($code, 3)]);
    httpCheck($redirected($result), 'Two-factor, Confirm: the recorded payload, with the current code, confirms it'.$describe($result));
    $people['robin']->refresh();
    $codes = $people['robin']->recoveryCodes();
    httpCheck($people['robin']->two_factor_confirmed_at !== null && count($codes) === 8, 'Two-factor, Confirm: it is on, with eight recovery codes');
    $result = $send('robin', 'POST', '/me/security/recovery-codes', $fixture('recovery-codes'));
    httpCheck($redirected($result), 'Recovery codes: the recorded payload regenerates them, redirecting back with no errors'.$describe($result));
    $people['robin']->refresh();
    httpCheck(count($people['robin']->recoveryCodes()) === 8 && array_intersect($codes, $people['robin']->recoveryCodes()) === [], 'Recovery codes: a new set replaces the old one');
    $result = $send('robin', 'DELETE', '/me/security/two-factor', $fixture('two-factor-disable'));
    httpCheck($redirected($result), 'Two-factor, Turn off: the recorded payload turns it off, redirecting back with no errors'.$describe($result));
    $people['robin']->refresh();
    httpCheck($people['robin']->two_factor_secret === null && $people['robin']->two_factor_confirmed_at === null, 'Two-factor, Turn off: the secret is gone');

    // Security › passkeys: renamed and removed (the kit's PasskeysController); adding one is laravel/passkeys' ceremony.
    $passkey = (int) DB::table('passkeys')->insertGetId(['user_id' => $people['robin']->id, 'name' => 'Phone passkey', 'credential_id' => bin2hex(random_bytes(8)), 'credential' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    $result = $send('robin', 'PATCH', '/me/security/passkeys/'.$passkey, $fixture('passkey-rename'));
    httpCheck($redirected($result), 'Passkeys, Rename: the recorded payload saves, redirecting back with no errors'.$describe($result));
    httpCheck(DB::table('passkeys')->where('id', $passkey)->value('name') === 'Work phone', 'Passkeys, Rename: the new name is stored');
    $result = $send('robin', 'DELETE', '/me/security/passkeys/'.$passkey, $fixture('passkey-remove'));
    httpCheck($redirected($result), 'Passkeys, Remove: the recorded payload removes it, redirecting back with no errors'.$describe($result));
    httpCheck(DB::table('passkeys')->where('id', $passkey)->doesntExist(), 'Passkeys, Remove: the passkey is gone');
    // Fortify guards passkey registration with password.confirm (fortify-options.passkeys.confirmPassword, on by default), and the Security page never
    // asks for it: without a recent confirmation the options and the store answer 423. Confirmed here first, through Fortify's own route.
    $result = $send('robin', 'POST', '/user/confirm-password', ['password' => $password]);
    httpCheck(in_array($result[0], [201, 302, 303], true) && $result[2] === [], 'Passkeys, Add: the password is confirmed through Fortify first'.$describe($result));
    $add = $fixture('passkey-add');
    httpCheck(array_keys($add) === ['name', 'credential'] && array_keys($add['credential']) === ['id', 'rawId', 'type', 'response'] && array_keys($add['credential']['response']) === ['clientDataJSON', 'attestationObject'], 'Passkeys, Add: the page posts the name and the browser\'s credential, as laravel/passkeys reads them');
    [$status, $location, $errors] = $send('robin', 'POST', '/user/passkeys', $add);
    $shape = array_filter(array_keys($errors), fn (string $field): bool => $field === 'name' || ($field !== 'credential' && str_starts_with($field, 'credential')));
    httpCheck($status === 302 && $errors === ['credential' => ['Invalid credential format.']] && $shape === [] && DB::table('passkeys')->where('user_id', $people['robin']->id)->doesntExist(), 'Passkeys, Add: the recorded payload reaches the store with every field it validates, and only the ceremony refuses it: a redirect back with the credential\'s "Invalid credential format." alone (a stubbed attestation), nothing stored; never a 5xx or any other refusal ('.$status.' '.json_encode($errors).')');

    // Security › Signed-in devices: another session of Robin's, on another device, ended; this one kept.
    DB::table('sessions')->insert(['id' => str_repeat('o', 40), 'user_id' => $people['robin']->id, 'ip_address' => '198.51.100.7', 'user_agent' => 'Safari on iOS', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp()]);
    $result = $send('robin', 'DELETE', '/me/security/sessions', $fixture('sessions-sign-out'));
    httpCheck($redirected($result), 'Signed-in devices: the recorded payload signs the others out, redirecting back with no errors'.$describe($result));
    httpCheck(DB::table('sessions')->where('id', str_repeat('o', 40))->doesntExist() && DB::table('sessions')->where('id', $sessions['robin'])->exists(), 'Signed-in devices: the other session is ended, this one kept');

    // The welcome flow: the timezone saved as it changes, then confirmed; then the photo step's upload.
    $result = $send('sam', 'PUT', '/welcome/timezone', $fixture('welcome-timezone-save'));
    httpCheck($redirected($result), 'Welcome, timezone: the recorded change saves, redirecting back with no errors'.$describe($result));
    httpCheck(Onboarding::value($people['sam']->id, 'timezone') === 'Europe/Amsterdam' && Onboarding::pending($people['sam']->id, 'timezone'), 'Welcome, timezone: the zone is saved, not yet confirmed');
    $result = $send('sam', 'POST', '/welcome/timezone', $fixture('welcome-timezone'));
    httpCheck($redirected($result, 'https://kit.test/welcome'), 'Welcome, timezone: Continue confirms it and moves on to /welcome'.$describe($result));
    httpCheck(! Onboarding::pending($people['sam']->id, 'timezone') && Onboarding::next($people['sam']->id)['id'] === 'photo', 'Welcome, timezone: confirmed, and the photo step is next');
    $result = $send('sam', 'POST', '/welcome/photo', $fixture('welcome-photo'));
    httpCheck($redirected($result, 'https://kit.test/welcome'), 'Welcome, photo: the recorded upload saves and moves on to /welcome'.$describe($result));
    httpCheck(DB::table('media')->where('model_id', $people['sam']->id)->where('collection_name', 'avatar')->count() === 1 && Onboarding::next($people['sam']->id) === null, 'Welcome, photo: the photo is stored and the flow is done');

    // The notes wall, as a member of Acme: add, then edit and delete notes at the versions the fixtures were recorded against.
    $notes = fn (): Builder => DB::table('kit_notes')->where('workspace_id', $workspace->id);
    $result = $send('casey', 'POST', '/@acme/notes', $fixture('note-create'));
    httpCheck($redirected($result), 'Notes, add: the recorded payload saves, redirecting back with no errors'.$describe($result));
    $added = $notes()->latest('id')->first();
    httpCheck($added !== null && $added->body === 'Order more seed trays' && $added->colour === 'blue' && (bool) $added->pinned === true && (int) $added->author_id === $people['casey']->id, 'Notes, add: the body, colour and pin are stored');
    $result = $send('casey', 'POST', '/@acme/notes', $fixture('note-create'));
    httpCheck($redirected($result) && $notes()->count() === 1, 'Notes, add: the same intent key posted again does not add a second note');
    // The edit fixture was recorded on a note at version 3, the delete fixture on one at version 2.
    $edited = (int) $notes()->insertGetId(['workspace_id' => $workspace->id, 'author_id' => $people['casey']->id, 'body' => 'Call the supplier', 'colour' => 'yellow', 'pinned' => false, 'version' => 3, 'created_at' => now(), 'updated_at' => now()]);
    $result = $send('casey', 'PUT', '/@acme/notes/'.$edited, $fixture('note-update'));
    httpCheck($redirected($result), 'Notes, edit: the recorded payload saves, redirecting back with no errors'.$describe($result));
    $row = $notes()->where('id', $edited)->first();
    httpCheck($row->body === 'Call the supplier on Monday' && $row->colour === 'green' && (int) $row->version === 4, 'Notes, edit: the new body and colour are stored and the version moves on');
    $deleted = (int) $notes()->insertGetId(['workspace_id' => $workspace->id, 'author_id' => $people['casey']->id, 'body' => 'Water the seedlings', 'colour' => 'pink', 'pinned' => false, 'version' => 2, 'created_at' => now(), 'updated_at' => now()]);
    $result = $send('casey', 'DELETE', '/@acme/notes/'.$deleted, $fixture('note-delete'));
    httpCheck($redirected($result), 'Notes, delete: the recorded payload deletes it, redirecting back with no errors'.$describe($result));
    httpCheck($notes()->where('id', $deleted)->whereNull('deleted_at')->doesntExist(), 'Notes, delete: the note is gone from the wall');

    // Delete account, last: Dana owns no workspace; her password, then signed out and sent to /. The host binds its purge (this one has nothing of its own).
    $app->instance(PurgesAccountData::class, new class implements PurgesAccountData
    {
        public function purge(Model $user): void {}
    });
    $result = $send('dana', 'DELETE', '/me/delete', $fixture('account-delete'));
    httpCheck($redirected($result, 'https://kit.test'), 'Delete account: the recorded payload deletes the account, redirecting to / with no errors'.$describe($result));
    // The users table has a remember-me column, as a host's does: signing out must not save the deleted user back (logout() cycles the token and saves).
    httpCheck(HttpUser::query()->whereKey($people['dana']->id)->doesntExist() && Auth::guard('web')->guest(), 'Delete account: the account is gone, not saved back by the sign-out, and signed out');

    httpCheck(Workspace::current() === null, 'no tenant context leaks out of the checks');
    echo "Account and session form payloads on SQLite memory: the browser job's recorded payloads, router dispatch, real Fortify, passkeys, medialibrary and database sessions.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
