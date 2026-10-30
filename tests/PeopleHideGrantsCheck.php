<?php

// kit.people.hide_grants: members holding a hidden Concierge grant are left out of People (rows and count), the dashboard's People list and people search,
// and their own pages and actions are a 404; everyone else stays. Hidden grants a Concierge can't apply (before 0.9.3) stop the app at boot. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
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
use Tey\Concierge\Support\MembersIndex;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\HiddenGrants;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
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
    $base = sys_get_temp_dir().'/kit-hide-grants-'.bin2hex(random_bytes(4));
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
    $people_page = function (string $search = '') use ($request): array {
        [$status, $page] = $request('owner', '/@acme/settings/people'.($search === '' ? '' : '?'.http_build_query(['filter' => ['search' => $search]])));
        httpCheck($status === 200, 'People answers 200 ('.$status.')');

        return [array_map(fn (array $row): string => $row['member']['name'], $page['props']['table']['rows']), array_column($page['props']['table']['tabs'], 'count', 'key')['members'], $page['props']['table']['pagination']['total']];
    };
    $widget = function () use ($request): array {
        [$status, $page] = $request('owner', '/@acme', ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Workspace/Dashboard/Page', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'dashboard_people']);
        httpCheck($status === 200, 'the People list answers 200 ('.$status.')');

        return array_column($page['props']['dashboard_people']['data'], 'title');
    };
    $search = function (string $who = 'owner') use ($request): array {
        [$status, $page] = $request($who, '/@acme/search?q=example');
        httpCheck($status === 200, 'search answers 200 ('.$status.')');

        return array_column(array_column($page['props']['groups'] ?? [], null, 'key')['kit.people']['results'] ?? [], 'label');
    };

    // The boot guard: hidden grants need MembersIndex::scopeMembersUsing() (Concierge 0.9.3 or later); none to hide needs nothing. A stand-in index without
    // the method plays an older Concierge, so this runs on whichever Concierge is installed.
    $refusal = function (string $index): ?string {
        try {
            HiddenGrants::assertUsable($index);

            return null;
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
    };
    $older = get_class(new class {});
    httpCheck($refusal($older) === null, 'with no hidden grants, a Concierge without scopeMembersUsing() boots');
    $config->set('kit.people.hide_grants', ['support']);
    httpCheck($refusal($older) === 'kit.people.hide_grants needs tey/concierge 0.9.3 or later', 'with hidden grants, a Concierge without scopeMembersUsing() is refused at boot with the version it needs');
    $config->set('kit.people.hide_grants', 'support');
    httpCheck(str_contains((string) $refusal(MembersIndex::class), 'must be a list of grant names'), 'a hide_grants that is not a list of names is refused at boot, never read loosely');
    $config->set('kit.people.hide_grants', ['support']);
    if (! method_exists(MembersIndex::class, 'scopeMembersUsing')) {
        // An older Concierge (the lowest CI job): the guard above is all that can run; the lists need 0.9.3.
        httpCheck($refusal(MembersIndex::class) === 'kit.people.hide_grants needs tey/concierge 0.9.3 or later', 'this Concierge has no scopeMembersUsing(), so the installed one is refused too');
        echo "People hide-grants: boot guard only, on a Concierge without MembersIndex::scopeMembersUsing(); the lists and member pages need 0.9.3 or later and were not exercised.\n";
        exit(0);
    }
    httpCheck($refusal(MembersIndex::class) === null, 'this Concierge has scopeMembersUsing(), so hidden grants boot');

    // Hidden: the support member is in none of the lists, and People's count agrees with its rows.
    httpCheck($people_page() === [['Admin', 'Member', 'Owner'], 3, 3], 'People leaves out a member holding a hidden grant, in its rows, its tab count and its total ('.json_encode($people_page()).')');
    httpCheck($people_page('support') === [[], 0, 0], 'searching People for the hidden member finds no one, and counts no one');
    httpCheck($widget() === ['Admin', 'Member', 'Owner'], 'the dashboard\'s People list leaves out the hidden member ('.json_encode($widget()).')');
    httpCheck($search() === ['Admin', 'Member', 'Owner'] && $search('member') === ['Admin', 'Member', 'Owner'], 'people search leaves out the hidden member, for a manager and a member alike ('.json_encode($search()).')');

    // A hidden member's own pages and actions, by URL, are a 404 as for no such member; a member who isn't hidden still opens.
    $membershipOf = fn (string $who): int => (int) ConciergeModels::membership()::query()->where('workspace_id', $workspace->id)->where('user_id', $people[$who]->id)->value('id');
    // The owner's ownership row, so the Transfer ownership page is theirs to open.
    ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $membershipOf('owner')]);
    $hidden = '/@acme/settings/people/members/'.$membershipOf('support');
    httpCheck($request('owner', $hidden)[0] === 404 && $request('owner', $hidden.'/transfer')[0] === 404, 'a hidden member\'s Change role and Transfer ownership pages are a 404');
    httpCheck($request('owner', $hidden.'?role=member', method: 'PUT')[0] === 404 && $request('owner', $hidden, method: 'DELETE')[0] === 404, 'changing or removing a hidden member by id is a 404');
    httpCheck(ConciergeModels::membership()::query()->find($membershipOf('support'))?->status === MembershipStatus::Active, 'and the hidden member is untouched');
    $shown = '/@acme/settings/people/members/'.$membershipOf('member');
    httpCheck($request('owner', $shown)[0] === 200 && $request('owner', $shown.'/transfer')[0] === 200, 'a member who isn\'t hidden still opens both pages ('.$request('owner', $shown)[0].' '.$request('owner', $shown.'/transfer')[0].')');

    // The grant is read in the workspace: in Beta the same person is a plain member, and is listed there.
    $beta = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    foreach (['owner' => 'owner', 'support' => 'member'] as $who => $role) {
        ConciergeModels::membership()::query()->create(['workspace_id' => $beta->id, 'user_id' => $people[$who]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($beta, fn () => $people[$who]->assignRole($role), [$people[$who]]);
    }
    $beta->forceFill(['admitted' => true])->save();
    [$status, $page] = $request('owner', '/@beta/settings/people');
    httpCheck($status === 200 && array_map(fn (array $row): string => $row['member']['name'], $page['props']['table']['rows']) === ['Owner', 'Support'], 'a grant hides its holder only in the workspace where they hold it ('.$status.' '.json_encode(array_map(fn (array $row): string => $row['member']['name'], $page['props']['table']['rows'] ?? [])).')');

    // Not hidden (the default): everyone is listed again.
    $config->set('kit.people.hide_grants', []);
    httpCheck($people_page() === [['Admin', 'Member', 'Owner', 'Support'], 4, 4], 'with no hidden grants People lists everyone');
    httpCheck($widget() === ['Admin', 'Member', 'Owner', 'Support'] && $search() === ['Admin', 'Member', 'Owner', 'Support'], 'with no hidden grants the People list and people search list everyone');

    $config->set('kit.people.hide_grants', ['support']);
    echo "People hide-grants checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack, no browser. The boot guard is called directly, not by booting an app on an older Concierge.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
