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
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
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

    /** The current workspace's id each time the widget was built. */
    public static array $builtIn = [];

    /** @return list<string> */
    public function rows(): array
    {
        self::$builtIn[] = Tey\Kit\Features\Workspaces\Workspaces::current()?->getKey();

        return ['one', 'two'];
    }
}

/** A host's shell source in front of the kit's: records the current workspace each time the lazy `shell` prop is built. */
final class RecordingShell implements Tey\Kit\Features\Shell\ShellSource
{
    public static array $builtIn = [];

    public function __construct(private Tey\Kit\Features\Shell\ShellSource $inner) {}

    public function forRequest(Request $request): Spatie\LaravelData\Data
    {
        self::$builtIn[] = Tey\Kit\Features\Workspaces\Workspaces::current()?->getKey();

        return $this->inner->forRequest($request);
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

/** Names a column the dashboard does not have. */
final class SidewaysWidget extends Widget
{
    public function title(): string
    {
        return 'Sideways';
    }

    public function component(): string
    {
        return 'sideways';
    }

    public function region(): string
    {
        return 'side';
    }
}

/** A kind the dashboard does not know. */
final class OddKindWidget extends Widget
{
    public function title(): string
    {
        return 'Odd';
    }

    public function component(): string
    {
        return 'odd';
    }

    public function kind(): string
    {
        return 'chart';
    }
}

/** Holds its body's place while its list loads: a declared loading height. */
final class TallWidget extends Widget implements Tey\Kit\Features\SessionUi\Dashboard\DeclaresLoadingHeight
{
    public function title(): string
    {
        return 'Tall';
    }

    public function component(): string
    {
        return 'tall';
    }

    public function loadingHeight(): ?string
    {
        return '.5rem';
    }
}

/** A loading height that is not a plain length. */
final class StretchyWidget extends Widget implements Tey\Kit\Features\SessionUi\Dashboard\DeclaresLoadingHeight
{
    public function title(): string
    {
        return 'Stretchy';
    }

    public function component(): string
    {
        return 'stretchy';
    }

    public function loadingHeight(): ?string
    {
        return 'calc(100vh - 1px)';
    }
}

/** A host widget with a loadingHeight() of its own that does not opt in: never asked, sends null. */
final class OwnHeightWidget extends Widget
{
    public static int $asked = 0;

    public function title(): string
    {
        return 'Own height';
    }

    public function component(): string
    {
        return 'own-height';
    }

    public function loadingHeight(): string
    {
        self::$asked++;

        return 'calc(100vh)';
    }
}

final class NotAWidget {}

/** An "Ongoing"-style list that offers three orders, "Last touched" by default; its rows name the order its query was given. */
final class SortedWidget extends Widget
{
    public static ?string $default = 'touched';

    public function title(): string
    {
        return 'Ongoing items';
    }

    public function component(): string
    {
        return 'sorted';
    }

    protected function sortOptions(): array
    {
        return ['opened' => 'Newest', 'touched' => 'Last touched', 'due' => 'Due soonest', '30' => 'Thirty days', 'Bad Key' => 'Dropped', 'empty' => ''];
    }

    protected function defaultSort(): ?string
    {
        return self::$default;
    }

    /** @return list<string> */
    public function rows(): array
    {
        return ['sorted by '.$this->chosenSort()];
    }
}

/** A list-backed widget: the workspace's active members, by name A to Z or Z to A, in its own ScrollList. */
final class SortedListWidget extends Widget
{
    public function title(): string
    {
        return 'Members by name';
    }

    public function component(): string
    {
        return 'members';
    }

    protected function sortOptions(): array
    {
        return ['az' => 'A to Z', 'za' => 'Z to A'];
    }

    public function lists(): array
    {
        return [new SortedMembersList($this->context->request, $this->context->workspace, (string) $this->chosenSort())];
    }
}

final class SortedMembersList extends Tey\Kit\Features\Core\Lists\ScrollListViewModel
{
    public function __construct(Request $request, int|string $workspace, private string $order)
    {
        parent::__construct($request, $workspace);
    }

    public function key(): string
    {
        return 'dashboard_sorted_members';
    }

    public function title(): string
    {
        return 'Members';
    }

    protected function activeWorkspace(): int|string|null
    {
        return Tey\Kit\Features\Workspaces\Workspaces::current()?->getKey();
    }

    protected function query(): Illuminate\Database\Eloquent\Builder
    {
        $members = ConciergeModels::membership()::query()->where('workspace_id', $this->workspace)->where('status', MembershipStatus::Active)->select('user_id');

        return HttpUser::query()->whereIn('id', $members)->orderBy('name', $this->order === 'za' ? 'desc' : 'asc')->orderBy('id');
    }

    protected function row(Illuminate\Database\Eloquent\Model $model): Tey\Kit\Features\Core\Lists\ListItemData
    {
        return new Tey\Kit\Features\Core\Lists\ListItemData((string) $model->getKey(), (string) $model->name);
    }

    protected function empty(): Tey\Kit\Features\Core\Lists\ListEmptyData
    {
        return new Tey\Kit\Features\Core\Lists\ListEmptyData('first-use', 'No one', 'Nobody yet.');
    }
}

/** Whether the synthetic onboarding steps are done: one about the person, one about the workspace. */
final class SetupFlags
{
    public static bool $person = false;

    public static bool $workspace = false;
}

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
    $app->make(Registry::class)->widget('app.queue', QueueWidget::class, -40)->widget('app.broken', BrokenWidget::class, 5)->widget('app.admin', QueueWidget::class, 6, 'members.manage')->widget('app.sideways', SidewaysWidget::class, 7)->widget('app.oddkind', OddKindWidget::class, 8)->widget('app.tall', TallWidget::class, 9)->widget('app.stretchy', StretchyWidget::class, 10)->widget('app.ownheight', OwnHeightWidget::class, 11);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
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
    $request = function (string $who, string $method, string $path, array $cookies = [], array $headers = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, $method, cookies: $cookies, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', ...$headers]);
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
    check(array_column($registry->widgets(), 'key') === ['app.queue', 'kit.you', 'kit.activity', 'kit.people', 'app.broken', 'app.admin', 'app.sideways', 'app.oddkind', 'app.tall', 'app.stretchy', 'app.ownheight', 'kit.setup'], 'the kit registers you, activity, people and setup around the app\'s own');
    check(array_column($registry->widgets(), 'ability', 'key')['kit.setup'] === 'settings.write', 'the setup card is gated on the workspace settings ability (settings.write)');
    // An app replaces or suppresses a widget by key, whichever registers first.
    $replaced = (new Registry)->replaceWidget('kit.activity', QueueWidget::class)->widget('kit.you', BrokenWidget::class, -30)->widget('kit.activity', BrokenWidget::class, -20, 'members.manage')->widgets();
    check(array_map(fn (array $entry) => [$entry['key'], $entry['widget'], $entry['order'], $entry['ability']], $replaced) === [['kit.you', BrokenWidget::class, -30, 'dashboard.read'], ['kit.activity', QueueWidget::class, -20, 'members.manage']], 'a replacement registered before the kit takes the slot, keeping its key, order and ability');
    $moved = (new Registry)->widget('kit.activity', BrokenWidget::class, -20, 'members.manage')->replaceWidget('kit.activity', QueueWidget::class, 50, 'dashboard.read')->widget('a', BrokenWidget::class)->widgets();
    check(array_map(fn (array $entry) => [$entry['key'], $entry['widget'], $entry['order'], $entry['ability']], $moved) === [['a', BrokenWidget::class, 0, 'dashboard.read'], ['kit.activity', QueueWidget::class, 50, 'dashboard.read']], 'a replacement may set its own order and ability');
    check(array_column((new Registry)->withoutWidget('kit.activity')->widget('kit.activity', BrokenWidget::class)->widget('b', BrokenWidget::class)->widgets(), 'key') === ['b'], 'a suppressed widget is left out');
    check(array_column((new Registry)->withoutWidget('never.registered')->widget('b', BrokenWidget::class)->widgets(), 'key') === ['b'], 'suppressing a key no one registers changes nothing');
    check($refused(fn (Registry $r) => $r->replaceWidget('kit.activity', QueueWidget::class)->widgets()), 'replacing a key no one registers is refused when the widgets are read');
    check($refused(fn (Registry $r) => $r->replaceWidget('a', QueueWidget::class)->replaceWidget('a', QueueWidget::class)), 'a key replaced twice is refused');
    check($refused(fn (Registry $r) => $r->withoutWidget('a')->replaceWidget('a', QueueWidget::class)), 'a key both suppressed and replaced is refused');
    check($refused(fn (Registry $r) => $r->replaceWidget('a', NotAWidget::class)), 'a replacement that is not a Widget is refused');

    $page = function (string $who, array $headers = [], array $cookies = []) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test/@acme', 'GET', cookies: $cookies, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', ...$headers]);
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

    // The kit's own steps are classified: the person's timezone and photo are personal, the workspace's timezone is the workspace's.
    $steps = new ReflectionProperty(Onboarding::class, 'steps');
    Onboarding::defaults();
    check(array_map(fn (array $step) => $step['scope'], $steps->getValue()) === ['timezone' => 'person', 'workspace-timezone' => 'workspace', 'photo' => 'person'], 'the kit\'s timezone and photo steps are personal and the workspace timezone is the workspace\'s');
    try {
        Onboarding::step('odd', 'Odd', 1, fn () => true, scope: 'team');
        check(false, 'a step of no known scope is refused');
    } catch (InvalidArgumentException) {
        check(true, 'a step of no known scope is refused');
    }
    // From here the steps are synthetic, so their done state is the check's to set (the kit's photo step needs real media).
    $steps->setValue(null, []);
    Onboarding::step('profile', 'Your profile', 10, fn (): bool => SetupFlags::$person, 'Add a photo');
    Onboarding::step('branding', 'Workspace branding', 20, fn (): bool => SetupFlags::$workspace, 'Add the logo', scope: 'workspace');

    [$status, $owner] = $page('owner');
    check($status === 200, 'the dashboard answers 200 with a widget that throws');
    // "/" for a member of one workspace is that workspace's dashboard: a personal route, so ResolveWorkspace does not run, and HomeController switches itself.
    // The shared `shell` prop is a closure the response resolves lazily (a host's ShellSource builds it): record the workspace it is built in.
    $app->extend(Tey\Kit\Features\Shell\ShellSource::class, fn (Tey\Kit\Features\Shell\ShellSource $inner) => new RecordingShell($inner));
    QueueWidget::$builtIn = [];
    Tey\Kit\Features\Workspaces\Workspaces::model()::forgetCurrent();
    [$status, $body] = $request('member', 'GET', '/');
    $home = json_decode($body, true);
    check($status === 200 && ($home['component'] ?? null) === $owner['component'] && QueueWidget::$builtIn === [$workspace->id] && Tey\Kit\Features\Workspaces\Workspaces::current() === null, 'GET / for a member of one workspace builds its dashboard with that workspace current, and none is current after');
    check(RecordingShell::$builtIn === [$workspace->id], 'the lazily shared shell prop on / is built inside the workspace');
    RecordingShell::$builtIn = [];
    [$status, $body] = $request('member', 'GET', '/', headers: ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => $home['component'], 'HTTP_X_INERTIA_PARTIAL_DATA' => 'shell']);
    $partial = json_decode($body, true);
    check($status === 200 && array_key_exists('shell', $partial['props'] ?? []), 'a partial reload of / for the shell answers it');
    check(RecordingShell::$builtIn === [$workspace->id] && Tey\Kit\Features\Workspaces\Workspaces::current() === null, 'and builds it inside the workspace too, with none current after');
    check(array_key_exists('welcome', $owner['props']) && $owner['props']['welcome'] === null && $owner['props']['aside'] === 'narrow', 'the kit\'s dashboard sends no welcome card (the deprecated prop is null), and the aside is narrow by default');
    $widgets = array_column($owner['props']['widgets'], null, 'key');
    check(array_keys($widgets) === ['app.queue', 'kit.you', 'kit.activity', 'kit.people', 'app.broken', 'app.admin', 'app.sideways', 'app.oddkind', 'app.tall', 'app.stretchy', 'app.ownheight', 'kit.setup'], 'the owner sees every widget, in order, the setup card last');
    check($widgets['app.queue']['rows'] === ['one', 'two'] && $widgets['app.queue']['region'] === 'main' && $widgets['app.queue']['state'] === 'ready', 'an app widget carries its frame and its own typed props, in main by default');
    check($widgets['app.queue']['span'] === ['md' => 6, 'lg' => 12], 'a widget that still overrides the deprecated span() keeps compiling and sends it');
    check(array_map(fn (string $key) => $widgets[$key]['region'], ['kit.activity', 'kit.you', 'kit.people']) === ['main', 'aside', 'aside'], 'Recent activity is in main; You and People are in aside');
    check($widgets['app.queue']['kind'] === 'status' && array_map(fn (string $key) => $widgets[$key]['kind'], ['kit.activity', 'kit.you', 'kit.people']) === ['feed', 'status', 'status'], 'kind defaults to status; Recent activity is the feed, You and People are status');
    check($widgets['app.oddkind']['state'] === 'error' && $widgets['app.oddkind']['component'] === 'error', 'a widget of no known kind becomes an error card (fail closed)');
    check($widgets['app.tall']['loadingHeight'] === '.5rem' && $widgets['app.tall']['state'] === 'ready', 'a widget\'s declared loading height is sent in its frame');
    check(array_key_exists('loadingHeight', $widgets['app.queue']) && $widgets['app.queue']['loadingHeight'] === null, 'a widget that does not opt in sends a null loading height');
    check($widgets['app.ownheight']['state'] === 'ready' && $widgets['app.ownheight']['loadingHeight'] === null && OwnHeightWidget::$asked === 0, 'a host widget with its own loadingHeight() that does not implement DeclaresLoadingHeight is never asked for it and sends null, not its unvalidated value');
    check(! (new ReflectionClass(Widget::class))->hasMethod('loadingHeight'), 'the Widget base class declares no loadingHeight(), so a host widget\'s own method of that name still loads');
    check($widgets['app.stretchy']['state'] === 'error' && $widgets['app.stretchy']['component'] === 'error', 'a loading height that is not a px, rem or em length becomes an error card (fail closed)');
    check($widgets['app.sideways']['state'] === 'error' && $widgets['app.sideways']['component'] === 'error' && $widgets['app.sideways']['region'] === 'main', 'a widget naming neither main nor aside becomes an error card (fail closed)');
    check($widgets['app.broken']['state'] === 'error' && $widgets['app.broken']['component'] === 'error' && $widgets['app.broken']['error']['message'] === "This couldn't load." && ! array_key_exists('rows', $widgets['app.broken']), 'a widget that throws becomes an error card with none of its props');
    check(! str_contains(json_encode($owner), 'secret detail'), 'the failure\'s message never reaches the page');
    $logged = $app['log']->driver()->getLogger()->getHandlers()[0]->getRecords();
    $failure = array_values(array_filter($logged, fn ($record) => $record->message === 'Dashboard widget failed'))[0] ?? null;
    check($failure !== null && $failure->context['widget'] === 'app.broken' && $failure->context['exception_class'] === RuntimeException::class && ! str_contains(json_encode($failure->context), 'secret detail'), 'the failure is logged by class and trace frames, without its message');
    check($widgets['kit.you']['person']['name'] === 'Owner' && $widgets['kit.you']['person']['role'] === 'Owner' && $widgets['kit.you']['person']['workspace'] === 'Acme', 'the You card names the viewer, their role and the workspace');
    check($widgets['kit.you']['title'] === 'You' && $widgets['kit.you']['person']['timezone'] === 'UTC', 'the You card keeps "You" as its name and sends the viewer\'s timezone (UTC with none saved)');
    check(array_map(fn (array $action) => [$action['label'], $action['url'], $action['key']], $widgets['kit.you']['actions']) === [['Edit', '/me/profile', 'profile']], 'the You card\'s one action is Edit, to the viewer\'s profile');
    [, $zoned] = $page('owner', cookies: ['tz' => 'Europe/Amsterdam']);
    check(array_column($zoned['props']['widgets'], null, 'key')['kit.you']['person']['timezone'] === 'Europe/Amsterdam', 'the You card shows the zone the browser reports');
    [, $forged] = $page('owner', cookies: ['tz' => '<script>']);
    check(array_column($forged['props']['widgets'], null, 'key')['kit.you']['person']['timezone'] === 'Europe/Amsterdam', 'a zone that is not a real one never reaches the You card: the saved zone shows');
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

    // Personal steps are in You; workspace steps are in the setup card, a setup kind in the aside, last there.
    check(array_column($widgets['kit.you']['checklist'], 'key') === ['profile'] && $widgets['kit.you']['checklist'][0]['state'] === 'pending' && $widgets['kit.you']['checklist'][0]['action']['url'] === '/welcome/profile', 'the You card lists the viewer\'s personal steps, each linking to its welcome step');
    check($widgets['kit.setup']['title'] === 'Set up this workspace' && $widgets['kit.setup']['kind'] === 'setup' && $widgets['kit.setup']['region'] === 'aside' && array_column($widgets['kit.setup']['checklist'], 'key') === ['branding'], 'the setup card is a setup kind in the aside, listing only the workspace steps');
    $aside = array_values(array_filter($owner['props']['widgets'], fn (array $widget) => $widget['region'] === 'aside'));
    check(end($aside)['key'] === 'kit.setup', 'the setup card is last in the aside');
    [, $admin] = $page('admin');
    check(in_array('kit.setup', array_column($admin['props']['widgets'], 'key'), true), 'an admin, who manages the workspace settings, sees the setup card too');
    // A workspace step done hides the card; a personal step done hides You's checklist, not the card.
    SetupFlags::$workspace = true;
    [, $done] = $page('owner');
    $doneWidgets = array_column($done['props']['widgets'], null, 'key');
    check(! isset($doneWidgets['kit.setup']) && $doneWidgets['kit.you']['checklist'] !== [], 'the setup card disappears once every workspace step is done');
    SetupFlags::$person = true;
    [, $done] = $page('owner');
    check(array_column($done['props']['widgets'], null, 'key')['kit.you']['checklist'] === [], 'You lists no steps once every personal step is done');
    SetupFlags::$person = SetupFlags::$workspace = false;
    // The dashboard carries its workspace into the welcome flow its rows open, and a workspace step's skip holds for that workspace alone.
    check($app['session.store']->get(Tey\Kit\Features\SessionUi\Http\WelcomeController::SESSION_WORKSPACE) === $workspace->id, 'the dashboard hands its workspace to the welcome flow its checklist links open');
    $owner_id = (int) $people['owner']->id;
    Onboarding::context($owner_id, $workspace->id);
    Onboarding::skip($owner_id, 'branding');
    Onboarding::skip($owner_id, 'profile');
    $here = [Onboarding::state($owner_id, 'branding'), Onboarding::state($owner_id, 'profile')];
    Onboarding::context($owner_id, $workspace->id + 1000);
    check($here === ['skipped', 'skipped'] && Onboarding::state($owner_id, 'branding') === 'pending' && Onboarding::state($owner_id, 'profile') === 'skipped', 'a skipped workspace step stays pending in another workspace; a skipped personal step is skipped everywhere');
    DB::table('kit_user_state')->where('user_id', $owner_id)->where('key', 'like', 'onboarding.skipped.%')->delete();
    $config->set('kit.dashboard.aside', 'wide');
    [, $wide] = $page('owner');
    check($wide['props']['aside'] === 'wide', 'kit.dashboard.aside wide reaches the page');
    $config->set('kit.dashboard.aside', 'sideways');
    [, $odd] = $page('owner');
    check($odd['props']['aside'] === 'narrow', 'an unknown kit.dashboard.aside falls back to narrow');
    $config->set('kit.dashboard.aside', 'narrow');

    [, $member] = $page('member');
    check(! in_array('kit.setup', array_column($member['props']['widgets'], 'key'), true), 'a member, who cannot manage workspace settings, never gets the setup card');
    check(array_column(array_column($member['props']['widgets'], null, 'key')['kit.you']['checklist'], 'key') === ['profile'], 'a member still sees their own personal steps in You');
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

    // Sort by: a widget declares its orders and a default; the viewer's choice is kept per person, workspace and widget, and checked against the declared keys.
    // The host's throttle alias, which the sort endpoint's route names.
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);
    $registry->widget('app.sorted', SortedWidget::class, 8)->widget('app.sorted-admin', SortedWidget::class, 9, 'members.manage');
    $put = function (string $who, string $path, array $body) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $app['session.store']->forget('errors');
        $request = Request::create('https://kit.test'.$path, 'PUT', $body, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response->headers->get('Location'), array_keys($app['session.store']->get('errors')?->getBag('default')->toArray() ?? [])];
    };
    $sorted = fn (array $page, string $key = 'app.sorted') => array_column($page['props']['widgets'], null, 'key')[$key] ?? null;
    $stored = fn (string $who, int $in, string $key = 'app.sorted') => DB::table('kit_user_state')->where(['user_id' => $people[$who]->id, 'workspace_id' => $in, 'key' => Widget::sortStateKey($key)])->value('value');
    [, $fresh] = $page('owner');
    $control = $sorted($fresh)['sortBy'];
    check(array_map(fn (array $o) => [$o['key'], $o['label']], $control['options']) === [['opened', 'Newest'], ['touched', 'Last touched'], ['due', 'Due soonest'], ['30', 'Thirty days']], 'a widget\'s Sort by offers its declared orders in order, and drops a malformed key or an empty label');
    check($control['label'] === 'Sort by' && $control['current'] === 'touched' && $sorted($fresh)['rows'] === ['sorted by touched'], 'before any choice the default order is current, and the query is given it');
    // The owner belongs to one workspace, so its URLs are the short form here.
    check($control['url'] === '/widgets/app.sorted/sort' && $control['method'] === 'put' && $control['reload'] === ['widgets'], 'the control saves to the widget\'s own sort endpoint and reloads the widgets prop (it has no list)');
    check($widgets['app.queue']['sortBy'] === null, 'a widget that declares no orders has no Sort by control');
    SortedWidget::$default = 'archived';
    [, $undeclared] = $page('owner');
    check($sorted($undeclared)['sortBy']['current'] === 'opened' && $sorted($undeclared)['rows'] === ['sorted by opened'], 'a default the widget does not declare falls back to its first order');
    SortedWidget::$default = 'touched';

    [$status, $location] = $put('owner', $control['url'], ['sort' => 'due']);
    check($status === 303 && in_array($location, ['https://kit.test', 'https://kit.test/'], true) && $stored('owner', $workspace->id) === 'due', 'choosing an order saves it for the viewer in this workspace and returns to the dashboard (303)');
    [, $chosen] = $page('owner');
    check($sorted($chosen)['sortBy']['current'] === 'due' && $sorted($chosen)['rows'] === ['sorted by due'], 'the saved order is current next time, and the query is given it');
    $refusals = [$put('owner', $control['url'], ['sort' => 'Bad Key']), $put('owner', $control['url'], ['sort' => 'priority']), $put('owner', $control['url'], ['sort' => ['due']]), $put('owner', $control['url'], [])];
    check(array_map(fn (array $r) => $r[2], $refusals) === [['sort'], ['sort'], ['sort'], ['sort']] && $stored('owner', $workspace->id) === 'due', 'an order the widget does not declare (malformed, unknown, not a string or missing) is refused as a validation error on `sort`, and the saved choice stands');
    DB::table('kit_user_state')->where(['user_id' => $people['owner']->id, 'workspace_id' => $workspace->id, 'key' => Widget::sortStateKey('app.sorted')])->update(['value' => "priority'; drop table users;--"]);
    [, $forged] = $page('owner');
    check($sorted($forged)['sortBy']['current'] === 'touched' && $sorted($forged)['rows'] === ['sorted by touched'], 'a stored order the widget no longer declares falls back to the default (fail closed): it never reaches the query');
    $put('owner', '/@acme/widgets/app.sorted/sort', ['sort' => 'due']);

    // A numeric-only key is still a declared key, though PHP stores it as an int.
    [$status] = $put('owner', $control['url'], ['sort' => '30']);
    [, $numeric] = $page('owner');
    check($sorted($numeric)['sortBy']['current'] === '30' && $sorted($numeric)['rows'] === ['sorted by 30'], 'a numeric-only order key is offered, saved and given to the query as a string');
    $put('owner', $control['url'], ['sort' => 'due']);
    // A widget with its own list: the control reloads (and resets) that list's prop, and the partial reload returns its first page in the new order.
    $registry->widget('app.members', SortedListWidget::class, 10);
    [, $listed] = $page('owner');
    $members = $sorted($listed, 'app.members')['sortBy'];
    check($members['reload'] === ['dashboard_sorted_members'] && in_array('dashboard_sorted_members', $listed['deferredProps']['lists'] ?? [], true), 'a widget with a list reloads only that list\'s prop, not widgets');
    $partial = fn () => $page('owner', ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Workspace/Dashboard/Page', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'dashboard_sorted_members', 'HTTP_X_INERTIA_RESET' => 'dashboard_sorted_members'])[1]['props']['dashboard_sorted_members']['data'] ?? null;
    $before = $partial();
    $put('owner', $members['url'], ['sort' => 'za']);
    $after = $partial();
    check(array_column($before ?? [], 'title') === ['Admin', 'Member', 'Owner'] && array_column($after ?? [], 'title') === ['Owner', 'Member', 'Admin'], 'after a change the list\'s partial reload returns its first page in the new order, no stale rows');

    // Per person: the member's choice is their own, and neither sees the other's.
    [, $memberFresh] = $page('member');
    check($sorted($memberFresh)['sortBy']['current'] === 'touched', 'another member of the workspace still has the default: a choice is never shared across people');
    [$status] = $put('member', '/@acme/widgets/app.sorted/sort', ['sort' => 'opened']);
    [, $memberChosen] = $page('member');
    [, $ownerAgain] = $page('owner');
    check($status === 303 && $sorted($memberChosen)['sortBy']['current'] === 'opened' && $sorted($ownerAgain)['sortBy']['current'] === 'due', 'each person keeps their own order for the same widget');
    // Per widget: the same class under another key has its own choice.
    [, $admin] = $page('owner');
    check($sorted($admin, 'app.sorted-admin')['sortBy']['current'] === 'touched', 'a choice belongs to one widget key, not to every widget of that class');
    // Gated as the card is, and only for a registered, visible widget.
    [$status] = $put('member', '/@acme/widgets/app.sorted-admin/sort', ['sort' => 'due']);
    check($status === 403 && $stored('member', $workspace->id, 'app.sorted-admin') === null, 'someone the widget\'s ability refuses cannot save an order for it (403), and nothing is stored');
    [$status] = $put('owner', '/@acme/widgets/app.nowhere/sort', ['sort' => 'due']);
    check($status === 404, 'an order for a widget no one registered is a 404');
    [$status] = $put('outsider', '/@acme/widgets/app.sorted/sort', ['sort' => 'opened']);
    check($status !== 303 && $status < 500 && DB::table('kit_user_state')->where('user_id', $people['outsider']->id)->doesntExist(), 'someone outside the workspace cannot save an order there, and nothing is stored');

    // Per workspace: the owner of two workspaces keeps a separate order in each; a choice in one never leaks into the other.
    $other = Workspace::create(['name' => 'Northwind', 'slug' => 'northwind']);
    $other->execute(function () use ($registry, $other): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($other);
        }
    });
    ConciergeModels::membership()::query()->create(['workspace_id' => $other->id, 'user_id' => $people['owner']->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    app(Tey\Concierge\Concierge::class)->context()->run($other, fn () => $people['owner']->assignRole('owner'), [$people['owner']]);
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    $other->refresh();
    $pageIn = function (string $who, string $slug) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test/@'.$slug, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        $response = $app['router']->dispatch($request);
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    };
    [$status, $northwind] = $pageIn('owner', 'northwind');
    check($status === 200 && $sorted($northwind)['sortBy']['current'] === 'touched' && $sorted($northwind)['sortBy']['url'] === '/@northwind/widgets/app.sorted/sort', 'in another workspace the same person starts from the default: a choice is kept per workspace');
    [$status] = $put('owner', '/@northwind/widgets/app.sorted/sort', ['sort' => 'opened']);
    [, $northwind] = $pageIn('owner', 'northwind');
    [, $acme] = $pageIn('owner', 'acme');
    check($status === 303 && $sorted($northwind)['sortBy']['current'] === 'opened' && $sorted($acme)['sortBy']['current'] === 'due' && $stored('owner', $other->id) === 'opened' && $stored('owner', $workspace->id) === 'due', 'a choice in one workspace never changes the other\'s');
    [$status] = $put('member', '/@northwind/widgets/app.sorted/sort', ['sort' => 'due']);
    check($status !== 303 && $stored('member', $other->id) === null, 'a member of one workspace cannot save an order in a workspace they do not belong to');
    check(Widget::sortStateKey(str_repeat('a', 80)) !== Widget::sortStateKey(str_repeat('a', 81)) && strlen(Widget::sortStateKey(str_repeat('a.b', 40))) <= 60, 'a long widget key still fits the state column, and stays distinct');
    Tey\Kit\Features\SessionUi\UserState::forgetAll((int) $people['owner']->id);
    check(DB::table('kit_user_state')->where('user_id', $people['owner']->id)->doesntExist(), 'a person\'s saved orders go with their account');

    // Deprecated until 0.16: an app that still builds WelcomeCardData and passes it as `welcome`, in the pre-0.15.41 argument order, gets its page with the card.
    $legacy = new Tey\Kit\Features\SessionUi\Data\WelcomeCardData('Welcome to Acme, Owner.', 'Your workspace is ready.', new Tey\Kit\Features\Core\Data\Action('Dismiss welcome', '/me/welcome/'.$workspace->id, 'delete'), [['id' => 'timezone', 'label' => 'Your timezone', 'hint' => '', 'url' => '/welcome/timezone', 'state' => 'done']], 1, 1);
    $app['router']->middleware('web')->get('legacy-dashboard', fn (Request $request) => Inertia\Inertia::render('Workspace/Dashboard/Page', new Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel('Dashboard', 'Acme', 'Open settings', '/settings', 'Good morning, Owner', $legacy, [['key' => 'w']], ['rows' => [1]]))->toResponse($request));
    $app['router']->getRoutes()->refreshNameLookups();
    Auth::guard('web')->setUser($people['owner']);
    $legacyRequest = Request::create('https://kit.test/legacy-dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $legacyRequest->setUserResolver(fn () => $people['owner']);
    $app->instance('request', $legacyRequest);
    $response = $app['router']->dispatch($legacyRequest);
    $status = $response->getStatusCode();
    $props = json_decode((string) $response->getContent(), true)['props'] ?? [];
    check($status === 200 && $props['welcome']['title'] === 'Welcome to Acme, Owner.' && $props['welcome']['checklist'][0]['url'] === '/welcome/timezone' && $props['welcome']['dismiss']['url'] === '/me/welcome/'.$workspace->id && $props['widgets'] === [['key' => 'w']] && $props['rows'] === [1] && $props['aside'] === 'narrow', 'an app still passing WelcomeCardData as welcome (old argument order) gets a 200 with its card, widgets and lists');
    $new = new Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel('Dashboard', 'Acme', 'Open settings', '/settings', 'Hi', [['key' => 'w']], ['rows' => [1]], 'wide');
    check($new->welcome() === null && $new->widgets() === [['key' => 'w']] && $new->toArray()['rows'] === [1] && $new->aside() === 'wide', '0.15.41\'s argument order (widgets, lists, aside) still works');
    $named = new Tey\Kit\Features\SessionUi\ViewModels\DashboardViewModel('Dashboard', 'Acme', 'Open settings', '/settings', welcome: $legacy, widgets: [['key' => 'w']], aside: 'wide');
    check($named->welcome() === $legacy && $named->widgets() === [['key' => 'w']] && $named->aside() === 'wide', 'named arguments work, with or without welcome');

    echo "Dashboard widget checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack. No browser: the Vue grid and cards are not rendered here, and the MCP tool's operation is called directly, not over the MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
