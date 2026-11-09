<?php

// People's Suspend and Reinstate: Concierge offers each row action by the member's status and the viewer's rank, the kit routes them to
// settings/people/members/{id}/suspend|reinstate, and the membership's status follows; a member who may not manage is refused. People's queries stay
// fixed as members and invitations grow (no query per row). SQLite memory, real Concierge, spatie teams and Pennant, the kit's router; no network.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
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
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
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
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
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

class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-people-forms-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'people' => ['hide_grants' => []], 'mcp' => ['ui' => true]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        FoundationServiceProvider::class, EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
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
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']], 'support' => ['label' => 'Support', 'grantable_by' => ['owner']], 'guest' => ['label' => 'Guest', 'grantable_by' => ['owner', 'admin'], 'scoped' => true]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $people = [];
    foreach (['owner', 'admin', 'member', 'support', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        if ($name === 'outsider') {
            continue;
        }
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    $sync->run(new ArrayInput([]), new NullOutput);
    // A real Concierge owner, so the owner's People also offers Make owner (ManagePeople::transferable) on every eligible row.
    ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => ConciergeModels::membership()::query()->where('user_id', $people['owner']->id)->value('id')]);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    $request = function (string $who, string $path, array $server = [], string $method = 'GET', array $params = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, $params, server: $server + ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    $page = function (string $who = 'owner', string $tab = 'members') use ($request): array {
        [$status, $page] = $request($who, '/@acme/settings/people'.($tab === 'members' ? '' : '?tab='.$tab));
        httpCheck($status === 200, 'People answers 200 for '.$who.' ('.$status.')');

        return $page['props']['table']['rows'];
    };
    $row = fn (array $rows, string $name): ?array => array_values(array_filter($rows, fn (array $row): bool => ($row['member']['name'] ?? null) === $name))[0] ?? null;
    $labels = fn (?array $row): array => array_column($row['actions'] ?? [], 'label');
    $action = fn (?array $row, string $label): ?array => array_values(array_filter($row['actions'] ?? [], fn (array $action): bool => $action['label'] === $label))[0] ?? null;
    $json = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_INERTIA' => ''];
    $status = fn (string $name): MembershipStatus => ConciergeModels::membership()::query()->where('user_id', $people[$name]->id)->sole()->status;

    // A page visit: Inertia, sent from People, so a refusal on a field goes back there with the field's error.
    $visit = ['HTTP_REFERER' => 'https://kit.test/@acme/settings/people'];
    $session = $app['session']->driver();
    $errors = function () use ($session): array {
        $bag = $session->get('errors')?->getBag('default')?->toArray() ?? [];
        $session->forget('errors');

        return $bag;
    };
    $membership = fn (string $name): int => (int) ConciergeModels::membership()::query()->where('user_id', $people[$name]->id)->value('id');
    $roles = fn (string $name): array => app(Concierge::class)->context()->run($workspace, fn () => $people[$name]->fresh()->getRoleNames()->all(), [$people[$name]]);
    $invited = fn (string $email): bool => ConciergeModels::invitation()::query()->where('email', $email)->exists();
    $member = '/@acme/settings/people/members/'.$membership('member');
    $invite = '/@acme/settings/people/invitations';

    // Change role: a stray field is dropped and the change saves; a role the actor may not give, or a scoped one, is the role field's error.
    [$code] = $request('owner', $member, $visit, 'PUT', ['role' => 'support', 'stray_field' => 'x']);
    httpCheck(in_array($code, [302, 303], true) && $errors() === [] && $roles('member') === ['support'], 'Change role with a stray field drops it and saves ('.$code.')');
    foreach (['owner' => 'a role the owner may not give', 'nope' => 'a role that does not exist', 'guest' => 'a scoped role'] as $role => $what) {
        [$code] = $request('owner', $member, $visit, 'PUT', ['role' => $role]);
        $bag = $errors();
        httpCheck(in_array($code, [302, 303], true) && isset($bag['role']) && $roles('member') === ['support'], 'Change role to '.$what.' comes back inline on errors.role, not the 403 page, and changes nothing ('.$code.')');
        [$code, $body] = $request('owner', $member, $json, 'PUT', ['role' => $role]);
        httpCheck($code === 403 && ! isset($body['errors']) && $roles('member') === ['support'], 'a JSON caller asking for '.$what.' is still refused with a 403 ('.$code.')');
    }
    // Every other denial keeps its 403: a member who may not manage, and the owner, whom nobody manages.
    [$code] = $request('member', '/@acme/settings/people/members/'.$membership('support'), $visit, 'PUT', ['role' => 'member']);
    httpCheck(in_array($code, [403, 404], true) && $errors() === [] && $roles('support') === ['support'], 'a member who may not manage is still refused outright, not shown a field error ('.$code.')');
    [$code] = $request('admin', '/@acme/settings/people/members/'.$membership('owner'), $visit, 'PUT', ['role' => 'member']);
    httpCheck($code === 403 && $errors() === [] && $roles('owner') === ['owner'], 'changing the owner\'s role is still the 403 page ('.$code.')');

    // Invite: the same, and a stray field is dropped.
    [$code] = $request('owner', $invite, $visit, 'POST', ['email' => 'stray@example.test', 'role' => 'member', 'stray_field' => 'x']);
    httpCheck(in_array($code, [302, 303], true) && $errors() === [] && $invited('stray@example.test'), 'Invite with a stray field drops it and sends the invitation ('.$code.')');
    foreach (['owner' => 'a role the owner may not give', 'nope' => 'a role that does not exist', 'guest' => 'a scoped role'] as $role => $what) {
        [$code] = $request('owner', $invite, $visit, 'POST', ['email' => $role.'@invitee.test', 'role' => $role]);
        $bag = $errors();
        httpCheck(in_array($code, [302, 303], true) && isset($bag['role']) && ! $invited($role.'@invitee.test'), 'Invite with '.$what.' comes back inline on errors.role, not the 403 page, and sends nothing ('.$code.')');
        [$code] = $request('owner', $invite, $json, 'POST', ['email' => $role.'@invitee.test', 'role' => $role]);
        httpCheck($code === 403 && ! $invited($role.'@invitee.test'), 'a JSON caller inviting with '.$what.' is still refused with a 403 ('.$code.')');
    }
    [$code] = $request('member', $invite, $visit, 'POST', ['email' => 'nobody@invitee.test', 'role' => 'member']);
    httpCheck(in_array($code, [403, 404], true) && $errors() === [] && ! $invited('nobody@invitee.test'), 'a member who may not manage still cannot invite, and is not shown a field error ('.$code.')');

    // A photo upload with no photo is the avatar field's error, not the 422 error page (Profile, and the welcome flow's photo step).
    [$code] = $request('member', '/me/avatar', ['HTTP_REFERER' => 'https://kit.test/me'], 'POST', ['stray_field' => 'x']);
    $bag = $errors();
    httpCheck(in_array($code, [302, 303], true) && isset($bag['avatar']), 'Upload with no photo comes back inline on errors.avatar, not the 422 page ('.$code.')');
    [$code, $body] = $request('member', '/me/avatar', $json, 'POST');
    httpCheck($code === 422 && isset($body['errors']['avatar']), 'a JSON caller uploading no photo still gets a 422 ('.$code.')');
    [$code] = $request('member', '/welcome/photo', ['HTTP_REFERER' => 'https://kit.test/welcome/photo'], 'POST');
    $bag = $errors();
    httpCheck(in_array($code, [302, 303], true) && isset($bag['avatar']), 'the welcome photo step with no photo comes back inline on errors.avatar ('.$code.')');

    echo "People forms: SQLite memory, real Concierge and the kit's routes; no network or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
