<?php

// The Workbench's server side: the WorkbenchViewModel's props, the per-person layout endpoint and its stale-write guard (session-ui, kit_user_state), the null
// store without session-ui, and the WorkbenchReadTool base. SQLite memory, real Concierge and spatie teams, synthetic users; no network.
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
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\WorkbenchInspector;
use Tey\Kit\Features\Core\Data\WorkbenchItem;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\ViewModels\WorkbenchViewModel;
use Tey\Kit\Features\Core\Workbench\NullWorkbenchPreferences;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\WorkbenchReadTool;
use Tey\Kit\Features\SessionUi\UserWorkbenchPreferences;
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

/** A deck editor's page: slides in the navigator, a publish action, a reorder action with a version token. */
final class DeckWorkbench extends WorkbenchViewModel
{
    public function __construct(private string $key = 'deck-editor', private bool $canReorder = true) {}

    public function title(): string
    {
        return 'Quarterly review';
    }

    public function workbenchKey(): string
    {
        return $this->key;
    }

    public function items(): array
    {
        return [new WorkbenchItem('s1', 'Title', 'title'), new WorkbenchItem('s2', 'Revenue', 'chart', 'Q3 by region')];
    }

    public function current(): ?string
    {
        return 's2';
    }

    public function inspectors(): array
    {
        return [new WorkbenchInspector('@document', 'Deck'), new WorkbenchInspector('chart', 'Chart'), new WorkbenchInspector('@fallback', 'Selection')];
    }

    public function actions(): array
    {
        return [new Action('Publish', '/decks/7/publish', 'post', key: 'publish')];
    }

    public function reorder(): ?Action
    {
        return $this->canReorder ? new Action('Reorder', '/decks/7/order', 'put', key: 'reorder') : null;
    }

    public function version(): string|int|null
    {
        return 12;
    }
}

#[Capability('workspace.decks')]
final class ReadDeck extends WorkbenchReadTool
{
    public const ABILITY = 'decks.read';

    protected string $description = 'Read a deck: its slides and the current one.';

    /** @var array<string, mixed> */
    public static array $returns = [];

    protected function subject(): string
    {
        return 'deck_id';
    }

    protected function read(Principal $principal, int $workspace, int $subject): array
    {
        return static::$returns + ['subject' => $subject];
    }

    public function run(int $subject): array
    {
        return $this->call(new Principal(1), 1, ['deck_id' => $subject]);
    }

    public function exposedRules(): array
    {
        return $this->rules();
    }
}

try {
    $base = sys_get_temp_dir().'/kit-workbench-'.bin2hex(random_bytes(4));
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
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);

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
    $json = function (?string $who, string $method, string $path, array $body = []) use ($app, $people): array {
        Auth::guard('web')->forgetUser();
        if ($who !== null) {
            Auth::guard('web')->setUser($people[$who]);
        }
        $request = Request::create('https://kit.test'.$path, $method, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], content: json_encode($body));
        $request->setUserResolver(fn () => $who === null ? null : $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    $stored = fn (string $who) => DB::table('kit_user_state')->where(['user_id' => $people[$who]->id, 'key' => 'workbench.deck-editor'])->value('value');

    // The ViewModel: typed props for the page, with the person's layout and where to save it.
    Auth::guard('web')->setUser($people['member']);
    $props = (new DeckWorkbench)->toArray();
    check(array_diff(['title', 'layout', 'workbenchKey', 'items', 'current', 'inspectors', 'actions', 'reorder', 'version', 'workbenchLayout'], array_keys($props)) === [], 'the ViewModel has every Workbench prop, beside the page\'s own title and layout');
    check($props['workbenchKey'] === 'deck-editor' && $props['current'] === 's2' && $props['version'] === 12, 'key, current item and version token');
    check($props['items'] === [['id' => 's1', 'label' => 'Title', 'type' => 'title', 'description' => null], ['id' => 's2', 'label' => 'Revenue', 'type' => 'chart', 'description' => 'Q3 by region']], 'navigator items are plain id, label, type and description');
    check(array_column($props['inspectors'], 'type') === ['@document', 'chart', '@fallback'], 'inspector descriptors, including the reserved document and fallback types');
    check($props['actions'][0]['key'] === 'publish' && $props['actions'][0]['label'] === 'Publish' && $props['reorder']['url'] === '/decks/7/order' && $props['reorder']['method'] === 'put', 'actions are descriptors with keys (Toast::from finds them), and the reorder action');
    check((new DeckWorkbench('deck-editor', false))->toArray()['reorder'] === null, 'a viewer who may not reorder gets no reorder action');
    check($props['workbenchLayout'] === ['state' => null, 'preference' => ['url' => '/me/workbenches/deck-editor', 'method' => 'put']], 'before the first save: no state, and the kit\'s endpoint to save to');
    $refused = false;
    try {
        (new DeckWorkbench('Deck Editor!'))->toArray();
    } catch (InvalidArgumentException) {
        $refused = true;
    }
    check($refused, 'a workbench key outside its pattern is refused');
    Auth::guard('web')->forgetUser();
    check((new DeckWorkbench)->toArray()['workbenchLayout'] === ['state' => null, 'preference' => null], 'no one signed in: nothing to remember');

    // The endpoint: per person and key, versioned, a stale write refused with the stored copy.
    $layout = ['groups' => ['columns' => '{"navigator,stage,inspector":{"layout":[20,54,26]}}'], 'zoom' => ['fit' => 'page', 'zoom' => 1], 'tab' => 'stage'];
    [$status, $body] = $json('member', 'PUT', '/me/workbenches/deck-editor', ['layout' => $layout, 'version' => 0]);
    check($status === 200 && $body === ['layout' => $layout, 'version' => 1], 'the first save is version 1');
    check(json_decode((string) $stored('member'), true) === ['layout' => $layout, 'version' => 1], 'kept in kit_user_state as the person\'s own state');
    $older = ['tab' => 'inspector'];
    [$status, $body] = $json('member', 'PUT', '/me/workbenches/deck-editor', ['layout' => $older, 'version' => 0]);
    check($status === 409 && $body === ['error' => 'stale_write', 'layout' => $layout, 'version' => 1], 'a stale version is 409 stale_write, with the stored layout and version');
    check(json_decode((string) $stored('member'), true)['version'] === 1, 'and changes nothing');
    $collapsed = ['groups' => ['columns' => '{"navigator,stage,inspector":{"layout":[0,74,26]}}', 'stage' => '{"stage,footer":{"layout":[100,0]}}'], 'zoom' => ['fit' => null, 'zoom' => 1.25], 'tab' => 'footer'];
    [$status, $body] = $json('member', 'PUT', '/me/workbenches/deck-editor', ['layout' => $collapsed, 'version' => 1]);
    check($status === 200 && $body['version'] === 2, 'the current version saves: collapsed panes, a collapsed footer, a fixed zoom, the footer tab');
    Auth::guard('web')->setUser($people['member']);
    check((new DeckWorkbench)->toArray()['workbenchLayout']['state'] === ['layout' => $collapsed, 'version' => 2], 'the next page load hands back the saved layout and version');
    [$status, $body] = $json('owner', 'PUT', '/me/workbenches/deck-editor', ['layout' => $older, 'version' => 0]);
    check($status === 200 && $body['version'] === 1 && json_decode((string) $stored('member'), true)['version'] === 2, 'each person has their own layout');
    [$status] = $json('member', 'PUT', '/me/workbenches/slide-sorter', ['layout' => [], 'version' => 0]);
    check($status === 200 && json_decode((string) $stored('member'), true)['version'] === 2, 'and each workbench key its own');

    foreach ([
        'an unknown layout key' => ['layout' => ['content' => 'secret'], 'version' => 2],
        'a zoom out of range' => ['layout' => ['zoom' => ['fit' => null, 'zoom' => 9]], 'version' => 2],
        'an unknown fit' => ['layout' => ['zoom' => ['fit' => 'sideways', 'zoom' => 1]], 'version' => 2],
        'an unknown tab' => ['layout' => ['tab' => 'settings'], 'version' => 2],
        'an odd group name' => ['layout' => ['groups' => ['../../x' => '{}']], 'version' => 2],
        'too many groups' => ['layout' => ['groups' => array_fill_keys(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'], '{}')], 'version' => 2],
        'a group value too long' => ['layout' => ['groups' => ['columns' => str_repeat('x', 2001)]], 'version' => 2],
        'too large a layout' => ['layout' => ['groups' => array_fill_keys(['a', 'b', 'c', 'd', 'e'], str_repeat('x', 1900))], 'version' => 2],
        'no version' => ['layout' => []],
        'a negative version' => ['layout' => [], 'version' => -1],
    ] as $label => $payload) {
        [$status] = $json('member', 'PUT', '/me/workbenches/deck-editor', $payload);
        check($status === 422 && json_decode((string) $stored('member'), true)['version'] === 2, 'refused (422): '.$label);
    }
    [$status] = $json('member', 'PUT', '/me/workbenches/Deck_Editor', ['layout' => [], 'version' => 0]);
    check($status === 404, 'a key outside its pattern does not route');
    [$status] = $json(null, 'PUT', '/me/workbenches/deck-editor', ['layout' => [], 'version' => 2]);
    check(in_array($status, [401, 302], true) && json_decode((string) $stored('member'), true)['version'] === 2, 'signed out: refused');
    check(app(WorkbenchPreferences::class) instanceof UserWorkbenchPreferences, 'session-ui binds the per-person store');

    // Without session-ui: nothing is remembered on the server.
    $null = new NullWorkbenchPreferences;
    check($null->layout(1, 'deck-editor')->toArray() === ['state' => null, 'preference' => null] && $null->save(1, 'deck-editor', [], 0)['saved'] === false, 'the null store remembers nothing and saves nothing');

    // The read tool: navigator items normalized, the rest passed through, read only, the subject argument named by the subclass.
    $tool = new ReadDeck;
    ReadDeck::$returns = ['items' => [new WorkbenchItem('s1', 'Title', 'title'), ['id' => 's2', 'label' => 'Revenue']], 'current' => 's2', 'theme' => 'dark'];
    $read = $tool->run(7);
    check($read['items'] === [['id' => 's1', 'label' => 'Title', 'type' => 'title', 'description' => null], ['id' => 's2', 'label' => 'Revenue', 'type' => null, 'description' => null]] && $read['current'] === 's2' && $read['theme'] === 'dark' && $read['subject'] === 7, 'the read tool normalizes items and passes the rest through');
    ReadDeck::$returns = ['items' => [['id' => 's1', 'label' => 'Title']]];
    check($tool->run(7)['current'] === null, 'current defaults to null');
    foreach (['no items' => ['current' => 's1'], 'items not a list' => ['items' => ['a' => ['id' => 's1', 'label' => 'x']]], 'an item without a label' => ['items' => [['id' => 's1']]], 'a numeric id' => ['items' => [['id' => 1, 'label' => 'x']]]] as $label => $returns) {
        ReadDeck::$returns = $returns;
        $threw = false;
        try {
            $tool->run(7);
        } catch (RuntimeException) {
            $threw = true;
        }
        check($threw, 'the read tool refuses a malformed read: '.$label);
    }
    check(array_keys($tool->exposedRules()) === ['deck_id'] && ($tool->annotations()['readOnlyHint'] ?? null) === true, 'the subject argument is the only one, and the tool is read only');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
