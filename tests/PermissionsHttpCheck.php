<?php

// Settings update over HTTP with real Concierge tables, spatie teams and the kit's providers booted, on SQLite memory with synthetic users.
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Spatie\Permission\PermissionRegistrar;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
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

class HttpUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-permissions-http-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach ($stubs as $file) {
        (require $file)->up();
    }

    $app['router']->middlewareGroup('web', [Illuminate\Session\Middleware\StartSession::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $people = [];
    foreach (['owner', 'admin', 'member', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        if ($name === 'outsider') {
            continue;
        }
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    $version = 1;
    $put = function (string $who, string $name) use ($app, $people, &$version): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('/@acme/settings/general', 'PUT', ['display_name' => $name, 'timezone' => 'UTC', 'expected_version' => $version, 'intent_key' => bin2hex(random_bytes(4))], server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response->getContent()];
    };
    // General's name is the workspace's own, the one the shell and its switcher read; the `display_name` setting is only a compatibility mirror.
    $name = fn () => (string) Workspace::query()->whereKey($workspace->id)->value('name');
    $mirror = fn () => $workspace->execute(fn () => Tey\Kit\Features\Settings\WorkspaceSettings::current()->display_name);

    // Before the sync nothing is granted: even the owner is refused, closed.
    httpCheck($put('owner', 'Before sync')[0] === 403, 'before the sync no role holds the permission and the update is refused');
    httpCheck($sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput) === 0, 'kit:permissions:sync runs as a command');

    [$status] = $put('admin', 'Acme Ltd');
    httpCheck($status === 200 && $name() === 'Acme Ltd', 'an admin can update settings after the sync');
    $version++;
    [$status] = $put('owner', 'Acme Inc');
    httpCheck($status === 200 && $name() === 'Acme Inc', 'the owner can update settings');
    $version++;
    [$status, $body] = $put('member', 'Hijacked');
    httpCheck($status === 403 && str_contains($body, 'permission_denied') && $name() === 'Acme Inc', 'a member is refused with 403 and nothing changes');
    [$status] = $put('outsider', 'Hijacked');
    httpCheck($status === 404 && $name() === 'Acme Inc', 'a non-member is refused and nothing changes');

    // The permission is granted; the capability is off.
    $config->set('kit.capabilities', ['workspace.settings' => false]);
    Feature::purge();
    [$status, $body] = $put('admin', 'Capability off');
    httpCheck($status === 404 && str_contains($body, 'capability_unavailable') && $name() === 'Acme Inc', 'a feature off with the permission granted is absent (404), not forbidden');
    $config->set('kit.capabilities', ['workspace.settings' => true]);
    Feature::purge();
    [$status] = $put('admin', 'Capability on');
    httpCheck($status === 200 && $name() === 'Capability on', 'the same admin is allowed again once the feature is on');

    // A default an admin removed stays removed across syncs.
    Spatie\Permission\Models\Role::findByName('admin', 'web')->revokePermissionTo('workspace.settings.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    // One source of truth: a rename moves the workspace's own name (and the mirror) in the version-checked transaction; a stale write and a replay after a newer rename change nothing.
    $raw = function (array $input, string $method = 'PUT') use ($app, $people): array {
        Auth::guard('web')->setUser($people['owner']);
        $request = Request::create('/@acme/settings/general', $method, $input, server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $people['owner']);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    $current = (int) Workspace::query()->whereKey($workspace->id)->value('settings_version');
    $first = ['display_name' => 'Renamed workspace', 'timezone' => 'UTC', 'expected_version' => $current, 'intent_key' => 'rename-1'];
    [$status, $saved] = $raw($first);
    httpCheck($status === 200 && $saved['display_name'] === 'Renamed workspace' && $name() === 'Renamed workspace' && $mirror() === 'Renamed workspace', 'a rename on General is the workspace name the shell reads, mirrored to display_name');
    [$status] = $raw([...$first, 'display_name' => 'Newest workspace', 'expected_version' => $saved['version'], 'intent_key' => 'rename-2']);
    httpCheck($status === 200 && $name() === 'Newest workspace', 'a newer rename applies');
    [$status, $replayed] = $raw($first);
    httpCheck($status === 200 && $replayed['display_name'] === 'Renamed workspace' && $name() === 'Newest workspace', 'replaying the first rename returns its stored result and cannot undo the newer one');
    [$status] = $raw([...$first, 'display_name' => 'Stale rename', 'intent_key' => 'rename-3']);
    httpCheck($status === 409 && $name() === 'Newest workspace' && $mirror() === 'Newest workspace', 'a stale rename is refused and changes neither the name nor the mirror');
    $workspace->execute(function (): void {
        $settings = Tey\Kit\Features\Settings\WorkspaceSettings::current();
        $settings->display_name = 'Obsolete display name';
        $settings->save();
    });
    [$status, $read] = $raw([], 'GET');
    httpCheck($status === 200 && $read['display_name'] === 'Newest workspace', 'General reads the workspace name over a stale display_name setting');
    $shell = (string) file_get_contents(dirname(__DIR__).'/src/Features/SessionUi/PageShell.php');
    httpCheck(substr_count($shell, '\'name\' => $workspace->name') >= 2 && ! str_contains($shell, 'display_name'), 'the shell and its switcher read the same workspace name, never the display_name setting');
    // Upgrading: a name set on General before 0.4.20 (the `display_name` setting) becomes the workspace's name, once. Acme's mirror now differs from its name.
    $others = [];
    foreach (['equal' => 'Equal team', 'empty' => ''] as $slug => $display) {
        $others[$slug] = Workspace::create(['name' => $slug === 'equal' ? 'Equal team' : 'Personal Team', 'slug' => $slug]);
        $others[$slug]->execute(function () use ($registry, $others, $slug, $display): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($others[$slug]);
            }
            $settings = Tey\Kit\Features\Settings\WorkspaceSettings::current();
            $settings->display_name = $display;
            $settings->save();
        });
    }
    $snapshot = fn () => Workspace::query()->orderBy('id')->get(['id', 'name', 'settings_version'])->toArray();
    $before = $snapshot();
    $adopt = require dirname(__DIR__).'/resources/features/settings/migrations/0001_01_02_000402_adopt_display_name_as_workspace_name.php.stub';
    $adopt->up();
    $after = $snapshot();
    [$status, $read] = $raw([], 'GET');
    httpCheck($name() === 'Obsolete display name' && $status === 200 && $read['display_name'] === 'Obsolete display name' && $after[0]['settings_version'] === $before[0]['settings_version'] + 1, 'a display name set on General becomes the workspace name General and the shell read, and the version moves');
    httpCheck(array_slice($after, 1) === array_slice($before, 1), 'an equal or empty display name changes nothing');
    $adopt->up();
    httpCheck($snapshot() === $after, 'a second run is a no-op');

    httpCheck(Workspace::current() === null, 'no tenant context leaks out of the checks');

    $version++;
    httpCheck($put('admin', 'Removed default')[0] === 403, 'a default removed from admin is not granted again by a later sync');
    echo "Permission HTTP checks on SQLite memory with real Concierge, spatie teams and Pennant; router dispatch without the global middleware stack, no browser or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
