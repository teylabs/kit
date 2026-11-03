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
use Illuminate\Support\Facades\DB;
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
    $base = sys_get_temp_dir().'/kit-people-suspend-'.bin2hex(random_bytes(4));
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

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']], 'support' => ['label' => 'Support', 'grantable_by' => ['owner']]]);
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
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    $request = function (string $who, string $path, array $server = [], string $method = 'GET') use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, server: $server + ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
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

    $member = $row($page(), 'Member');
    $suspend = $action($member, 'Suspend');
    httpCheck($suspend !== null && $suspend['method'] === 'post' && str_ends_with($suspend['url'], '/settings/people/members/'.ConciergeModels::membership()::query()->where('user_id', $people['member']->id)->value('id').'/suspend') && ! in_array('Reinstate', $labels($member), true), 'the owner sees Suspend (POST to the member\'s /suspend), not Reinstate, on an active member');
    httpCheck(($suspend['confirm']['title'] ?? null) !== null, 'Suspend asks to confirm first');
    httpCheck(! in_array('Suspend', $labels($row($page(), 'Owner')), true), 'nobody is offered Suspend on the owner');

    [$code] = $request('member', '/@acme'.parse_url($suspend['url'], PHP_URL_PATH), $json, 'POST');
    httpCheck(in_array($code, [403, 404], true) && $status('admin') === MembershipStatus::Active && $status('member') === MembershipStatus::Active, 'a plain member who may not manage is refused ('.$code.'), and nothing changes');

    [$code, $body] = $request('owner', '/@acme'.parse_url($suspend['url'], PHP_URL_PATH), $json, 'POST');
    httpCheck($code === 200 && ($body['status'] ?? null) === 'suspended' && $status('member') === MembershipStatus::Suspended, 'the owner suspends the member ('.$code.')');
    $suspended = $row($page(), 'Member');
    $reinstate = $action($suspended, 'Reinstate');
    httpCheck($reinstate !== null && ! in_array('Suspend', $labels($suspended), true) && str_ends_with($reinstate['url'], '/reinstate'), 'a suspended member offers Reinstate, not Suspend');
    $roles = $people['member']->fresh();
    [$code] = $request('owner', '/@acme'.parse_url($reinstate['url'], PHP_URL_PATH), $json, 'POST');
    httpCheck($code === 200 && $status('member') === MembershipStatus::Active, 'the owner reinstates the member ('.$code.')');
    httpCheck(in_array('Member', array_column($row($page(), 'Member')['roles'], 'label'), true), 'the reinstated member keeps the role they held');

    // Bounded: the People page's queries do not grow with its rows (members and invitations).
    $count = function (string $tab) use ($page): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page('owner', $tab);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    // One invitation first, so both counts include the invitations' own page and inviter queries (empty, Eloquent skips them).
    Auth::guard('web')->setUser($people['owner']);
    $workspace->execute(fn () => ManagePeople::make()->invite($people['owner'], 'invitee0@example.test', 'member'));
    $before = ['members' => $count('members'), 'invitations' => $count('invitations')];
    for ($i = 1; $i <= 8; $i++) {
        $user = HttpUser::create(['name' => 'Extra '.$i, 'email' => 'extra'.$i.'@example.test', 'email_verified_at' => now()]);
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => $i % 3 === 0 ? MembershipStatus::Suspended : MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $user->assignRole('member'), [$user]);
    }
    Auth::guard('web')->setUser($people['owner']);
    $workspace->execute(function () use ($people): void {
        for ($i = 1; $i <= 6; $i++) {
            ManagePeople::make()->invite($people['owner'], 'invitee'.$i.'@example.test', 'member');
        }
    });
    $after = ['members' => $count('members'), 'invitations' => $count('invitations')];
    httpCheck(count($page()) === 12 && count($page('owner', 'invitations')) === 7, 'the page now lists 12 members and 7 invitations');
    foreach (['members', 'invitations'] as $tab) {
        httpCheck($after[$tab] === $before[$tab], 'People\'s '.$tab.' tab runs the same number of queries as it grows ('.$before[$tab].' before, '.$after[$tab].' after)');
    }

    echo "People suspend: SQLite memory, real Concierge and the kit's routes; no network or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
