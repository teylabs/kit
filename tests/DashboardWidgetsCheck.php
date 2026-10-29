<?php

// Dashboard widgets: registration, ordering, per-widget gating, error isolation, the kit's generic widgets as rendered props, the people list's deferred
// rows and the MCP read tool's operation. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Dashboard\ReadDashboard;
use Tey\Kit\Features\SessionUi\Dashboard\Widget;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetSpanData;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);


final class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(HttpUser::class, 'App\Models\User');

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

final class QueueWidget extends Widget
{
    public function title(): string
    {
        return 'Queue';
    }

    public function component(): string
    {
        return 'queue';
    }

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 6, lg: 12);
    }

    /** @return list<string> */
    public function rows(): array
    {
        return ['one', 'two'];
    }
}

final class BrokenWidget extends Widget
{
    public function title(): string
    {
        return 'Broken';
    }

    public function component(): string
    {
        return 'broken';
    }

    public function rows(): array
    {
        throw new RuntimeException('secret detail SELECT * FROM users');
    }
}

final class NotAWidget {}

try {
    $base = sys_get_temp_dir().'/kit-widgets-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true], 'mcp' => ['ui' => true]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => Monolog\Handler\TestHandler::class]]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class)->widget('app.queue', QueueWidget::class, -40)->widget('app.broken', BrokenWidget::class, 5)->widget('app.admin', QueueWidget::class, 6, 'members.manage');
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
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

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();
    $request = function (string $who, string $method, string $path, array $cookies = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, cookies: $cookies, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };


    // Registration refuses what it cannot render, and orders by `order` then registration.
    $refused = function (callable $register): bool {
        try {
            $register(new Registry);
        } catch (RuntimeException) {
            return true;
        }

        return false;
    };
    check($refused(fn (Registry $r) => $r->widget('Bad Key', QueueWidget::class)), 'a widget key that is not dotted lower case is refused');
    check($refused(fn (Registry $r) => $r->widget('a', QueueWidget::class)->widget('a', QueueWidget::class)), 'a widget key registered twice is refused');
    check($refused(fn (Registry $r) => $r->widget('a', NotAWidget::class)), 'a class that is not a Widget is refused');
    check(array_column((new Registry)->widget('c', QueueWidget::class, 1)->widget('a', QueueWidget::class, 0)->widget('b', QueueWidget::class, 1)->widgets(), 'key') === ['a', 'c', 'b'], 'widgets sort by order, then registration');
    check(array_column($registry->widgets(), 'key') === ['app.queue', 'kit.you', 'kit.activity', 'kit.people', 'app.broken', 'app.admin'], 'the kit registers you, activity and people around the app\'s own');

    $page = function (string $who, array $headers = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test/@acme', 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', ...$headers]);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    $workspace->execute(fn () => DB::table('audit_events')->insert([
        ['actor_id' => $people['owner']->id, 'workspace_id' => $workspace->id, 'event' => 'membership.invited', 'details' => json_encode(['email' => 'secret@example.test']), 'created_at' => now()->subMinute()],
        ['actor_id' => $people['admin']->id, 'workspace_id' => $workspace->id, 'event' => 'settings.updated', 'details' => '{}', 'created_at' => now()],
        ['actor_id' => $people['admin']->id, 'workspace_id' => $workspace->id + 99, 'event' => 'elsewhere.happened', 'details' => '{}', 'created_at' => now()],
    ]));

    [$status, $owner] = $page('owner');
    check($status === 200, 'the dashboard answers 200 with a widget that throws');
    $widgets = array_column($owner['props']['widgets'], null, 'key');
    check(array_keys($widgets) === ['app.queue', 'kit.you', 'kit.activity', 'kit.people', 'app.broken', 'app.admin'], 'the owner sees every widget, in order');
    check($widgets['app.queue']['rows'] === ['one', 'two'] && $widgets['app.queue']['span'] === ['md' => 6, 'lg' => 12] && $widgets['app.queue']['state'] === 'ready', 'an app widget carries its frame and its own typed props');
    check($widgets['app.broken']['state'] === 'error' && $widgets['app.broken']['component'] === 'error' && $widgets['app.broken']['error']['message'] === "This couldn't load." && ! array_key_exists('rows', $widgets['app.broken']), 'a widget that throws becomes an error card with none of its props');
    check(! str_contains(json_encode($owner), 'secret detail'), 'the failure\'s message never reaches the page');
    $logged = $app['log']->driver()->getLogger()->getHandlers()[0]->getRecords();
    $failure = array_values(array_filter($logged, fn ($record) => $record->message === 'Dashboard widget failed'))[0] ?? null;
    check($failure !== null && $failure->context['widget'] === 'app.broken' && $failure->context['exception_class'] === RuntimeException::class && ! str_contains(json_encode($failure->context), 'secret detail'), 'the failure is logged by class and trace frames, without its message');
    check($widgets['kit.you']['person']['name'] === 'Owner' && $widgets['kit.you']['person']['role'] === 'Owner' && $widgets['kit.you']['person']['workspace'] === 'Acme', 'the You card names the viewer, their role and the workspace');
    $activity = $widgets['kit.activity']['items'];
    check(array_map(fn (array $entry) => $entry['headline'], $activity) === [['html' => '<strong>Admin</strong> · Settings updated'], ['html' => '<strong>Owner</strong> · Membership invited']], 'activity lists this workspace\'s events, newest first, with who did them, as ActivityStream entries');
    check($activity[0]['kind'] === 'activity' && is_string($activity[0]['occurredAt']) && $activity[0]['timestamp'] === null && $activity[0]['meta'] === [] && $activity[0]['children'] === [], 'each is a plain activity entry the stream formats in the viewer\'s zone');
    check(! str_contains(json_encode($widgets['kit.activity']), 'secret@example.test'), 'activity never shows an event\'s details');

    // ActivityEntryData, the shape hosts map into: one headline kind, kit tones, {label, value} meta, and groups that say when they were cut short.
    $group = Tey\Kit\Features\Core\Data\ActivityEntryData::group('g1', ['text' => '3 invoices sent'], [Tey\Kit\Features\Core\Data\ActivityEntryData::text('i1', 'Invoice 1 sent', meta: [['label' => 'Amount', 'value' => '£10']])], count: 3, day: ['date' => '2026-10-29', 'label' => 'Today'])->toArray();
    check($group['kind'] === 'group' && $group['count'] === 3 && $group['truncated'] === true && $group['children'][0]['headline'] === ['text' => 'Invoice 1 sent'] && $group['children'][0]['meta'] === [['label' => 'Amount', 'value' => '£10']] && $group['day'] === ['date' => '2026-10-29', 'label' => 'Today'], 'a group serializes its children, its full count and that it was truncated');
    check(Tey\Kit\Features\Core\Data\ActivityEntryData::group('g2', ['text' => 'two'], [Tey\Kit\Features\Core\Data\ActivityEntryData::text('a', 'a'), Tey\Kit\Features\Core\Data\ActivityEntryData::text('b', 'b')])->truncated === false, 'a group holding every child is not truncated');
    foreach ([
        'both headline kinds' => fn () => new Tey\Kit\Features\Core\Data\ActivityEntryData('x', 'activity', ['text' => 'a', 'html' => 'b']),
        'no headline' => fn () => new Tey\Kit\Features\Core\Data\ActivityEntryData('x', 'activity', []),
        'meta as bare strings' => fn () => Tey\Kit\Features\Core\Data\ActivityEntryData::text('x', 'a', meta: ['£10']),
        'an unknown kind' => fn () => new Tey\Kit\Features\Core\Data\ActivityEntryData('x', 'story', ['text' => 'a']),
    ] as $case => $make) {
        try {
            $make();
            check(false, 'an activity entry with '.$case.' is refused');
        } catch (InvalidArgumentException) {
            check(true, 'an activity entry with '.$case.' is refused');
        }
    }
    $open = Tey\Kit\Features\Core\Data\ActivityEntryData::text('x', 'Invoice paid', intent: 'paid')->toArray();
    check($open['intent'] === 'paid', 'a host\'s own intent is accepted as sent (the stream shows it as neutral), not refused');
    $bare = (new Tey\Kit\Features\Core\Data\ActivityEntryData('x', 'activity', null, '2026-10-29T09:00:00Z', meta: [['label' => 'By', 'value' => 'Ada']]))->toArray();
    check($bare['headline'] === null && $bare['meta'] === [['label' => 'By', 'value' => 'Ada']], 'a null headline is accepted: a row of its time and meta');
    $effective = Tey\Kit\Features\Core\Data\ActivityEntryData::text('x', 'Rate changed', occurredAt: '2026-10-29T09:00:00Z', timestamp: '29 Oct, 9:00', effectiveOn: '2026-11-01', effectiveOnLabel: '1 Nov')->toArray();
    check($effective['effectiveOn'] === '2026-11-01' && $effective['effectiveOnLabel'] === '1 Nov', 'effectiveOn and its label serialize');
    $actor = DB::table('users')->where('name', 'Admin')->value('id');
    DB::table('users')->where('id', $actor)->update(['name' => '<img src=x onerror=alert(1)>']);
    [, $renamed] = $page('owner');
    $escaped = json_encode(array_column($renamed['props']['widgets'], null, 'key')['kit.activity']['items'][0]['headline']);
    DB::table('users')->where('id', $actor)->update(['name' => 'Admin']);
    check(str_contains($escaped, '&lt;img src=x onerror=alert(1)&gt;') && ! str_contains($escaped, '<img'), 'the activity widget escapes the actor\'s name in its trusted html');
    check($widgets['kit.people']['list'] === 'dashboard_people' && array_key_exists('dashboard_people', $owner['props']) === false && in_array('dashboard_people', $owner['deferredProps']['lists'] ?? [], true), 'the people list is a deferred prop, not loaded with the page');
    check(array_column($widgets['kit.people']['actions'], 'key') === ['people'], 'someone who manages people gets the card\'s Manage people action');

    [, $member] = $page('member');
    $memberKeys = array_column($member['props']['widgets'], 'key');
    check(! in_array('kit.activity', $memberKeys, true) && ! in_array('app.admin', $memberKeys, true) && in_array('kit.you', $memberKeys, true), 'a member does not see widgets gated by an ability they lack');
    check(array_column($member['props']['widgets'], null, 'key')['kit.people']['actions'] === [], 'a member gets no Manage people action');
    check($member['props']['widgets'][array_search('kit.you', $memberKeys, true)]['person']['role'] === 'Member', 'the You card shows the member\'s own role');

    [$status] = $page('outsider');
    check($status !== 200, 'someone outside the workspace gets no dashboard');

    [$status, $rows] = $page('owner', ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Workspace/Dashboard/Page', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'dashboard_people']);
    check($status === 200 && array_column($rows['props']['dashboard_people']['data'], 'title') === ['Admin', 'Member', 'Owner'], 'the people list loads the workspace\'s active members by name, and no outsider');

    // Capability gating per widget: with workspace.settings off (the capability behind members.manage), the owner loses the widgets that ability gates, and keeps the rest.
    $config->set('kit.capabilities', ['workspace.settings' => false, 'workspace.dashboard' => true]);
    Feature::flushCache();
    [$status, $gated] = $page('owner');
    $gatedKeys = array_column($gated['props']['widgets'], 'key');
    check($status === 200 && ! in_array('kit.activity', $gatedKeys, true) && ! in_array('app.admin', $gatedKeys, true) && in_array('app.queue', $gatedKeys, true), 'a widget whose capability is off is hidden, even from the owner');
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => true]);
    Feature::flushCache();

    // The MCP read tool is registered as a capability tool, and its operation returns what the viewer may see.
    check(in_array(ReadDashboard::class, $registry->mcpTools(), true), 'the dashboard read tool is registered with the mcp feature');
    $request = Request::create('https://kit.test/mcp');
    $request->setUserResolver(fn () => $people['member']);
    $app->instance('request', $request);
    $call = new ReflectionMethod(ReadDashboard::class, 'call');
    $result = Tey\Kit\Features\Workspaces\Workspaces::run($workspace->id, function () use ($call, $people, $workspace) { return $call->invoke(new ReadDashboard, new Principal((int) $people['member']->id), $workspace->id, []); });
    check($result['workspace']['name'] === 'Acme' && ! in_array('kit.activity', array_column($result['widgets'], 'key'), true) && in_array('kit.people', array_column($result['widgets'], 'key'), true), 'the read tool returns the member\'s own widgets, gated the same way');

    // An app with its own dashboard or search turns the kit's tool off by name: it is no longer listed or callable (the server, tools/call and the consent gate all read mcpTools()), and the other stays.
    config(['kit.mcp.tools' => ['workspace_dashboard' => false]]);
    $tools = $registry->mcpTools();
    check(! in_array(ReadDashboard::class, $tools, true) && in_array(Tey\Kit\Search\SearchWorkspace::class, $tools, true), 'kit.mcp.tools turns workspace_dashboard off and leaves workspace_search');
    config(['kit.mcp.tools' => ['workspace_dashboard' => false, 'workspace_search' => false]]);
    check(array_intersect([ReadDashboard::class, Tey\Kit\Search\SearchWorkspace::class], $registry->mcpTools()) === [], 'both can be turned off');
    config(['kit.mcp.tools' => ['workspace_dashboard' => true]]);
    check(in_array(ReadDashboard::class, $registry->mcpTools(), true), 'a tool set to true, or not named, stays on (the default is unchanged)');
    config(['kit.mcp.tools' => []]);

    echo "Dashboard widget checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack. No browser: the Vue grid and cards are not rendered here, and the MCP tool's operation is called directly, not over the MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
