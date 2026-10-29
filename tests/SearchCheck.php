<?php

// Global search: providers registered and ordered, each asked only for a viewer who holds its ability, one that throws skipped and reported while search
// still answers, no workspace's rows in another's results, the per-person limit across the page and the MCP tool, the shell's search descriptor, and the MCP
// tool returning what the page shows. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users and notes. Case-insensitive matching is checked by result on SQLite and by compiled SQL for PostgreSQL.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Search\SearchProvider;
use Tey\Kit\Features\SessionUi\Search\SearchQuery;
use Tey\Kit\Features\SessionUi\Search\SearchResult;
use Tey\Kit\Features\SessionUi\Search\Searches;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Search\SearchWorkspace;

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

/** A synthetic app provider: this workspace's notes whose title matches, scoped as a real provider must be. */
final class NotesSearch implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        return DB::table('notes')->where('workspace_id', $query->workspace)->where('title', 'like', $query->like())->orderBy('title')->limit($query->limit)->get()
            ->map(fn ($note) => new SearchResult((string) $note->id, $note->title, '/notes/'.$note->id, meta: 'Note'))->all();
    }
}

final class BrokenSearch implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        throw new RuntimeException('secret detail '.$query->term);
    }
}

final class NotAProvider {}

try {
    $base = sys_get_temp_dir().'/kit-search-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'mcp' => ['ui' => true]],
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
    $app->make(Registry::class)->searchProvider('app.notes', NotesSearch::class, 'dashboard.read', 'Notes', 10)->searchProvider('app.broken', BrokenSearch::class, 'dashboard.read', 'Broken', 20)->searchProvider('app.admin', NotesSearch::class, 'members.manage', 'Admin notes', 30);
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

    $open = function (string $name) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => strtolower($name)]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $workspace = $open('Acme');
    $other = $open('Globex');
    $people = [];
    $join = function (Workspace $in, HttpUser $person, string $role): void {
        ConciergeModels::membership()::query()->create(['workspace_id' => $in->id, 'user_id' => $person->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Tey\Concierge\Concierge::class)->context()->run($in, fn () => $person->assignRole($role), [$person]);
    };
    foreach (['owner' => 'Ada Owner', 'member' => 'Bea Member', 'outsider' => 'Ada Outsider'] as $role => $name) {
        $people[$role] = HttpUser::create(['name' => $name, 'email' => $role.'@example.test', 'email_verified_at' => now()]);
    }
    $join($workspace, $people['owner'], 'owner');
    $join($workspace, $people['member'], 'member');
    // The outsider belongs to the other workspace only, where their name also matches "Ada".
    $join($other, $people['outsider'], 'owner');

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    // Provisioning admitted the workspaces on their own copies.
    $workspace->refresh();
    $other->refresh();
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


    Schema::create('notes', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->string('title');
    });
    DB::table('notes')->insert([['workspace_id' => $workspace->id, 'title' => 'Ada plan'], ['workspace_id' => $workspace->id, 'title' => 'Budget'], ['workspace_id' => $other->id, 'title' => 'Ada secret elsewhere']]);

    $get = function (string $who, string $path, bool $json = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $json ? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $request = Request::create('https://kit.test'.$path, 'GET', server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true), $response];
    };

    $refused = function (callable $register): bool {
        try {
            $register(new Registry);
        } catch (RuntimeException) {
            return true;
        }

        return false;
    };
    check($refused(fn (Registry $r) => $r->searchProvider('Bad Key', NotesSearch::class, 'dashboard.read', 'x')), 'a provider key that is not dotted lower case is refused');
    check($refused(fn (Registry $r) => $r->searchProvider('a', NotesSearch::class, 'dashboard.read', 'x')->searchProvider('a', NotesSearch::class, 'dashboard.read', 'x')), 'a provider key registered twice is refused');
    check($refused(fn (Registry $r) => $r->searchProvider('a', NotAProvider::class, 'dashboard.read', 'x')), 'a class that is not a SearchProvider is refused');
    check(array_column($registry->searchProviders(), 'key') === ['app.notes', 'app.broken', 'app.admin', 'kit.people'], 'providers sort by order, then registration, with the kit\'s people last');

    [$status, $owner] = $get('owner', '/@acme/search?q=ada');
    $groups = array_column($owner['props']['groups'] ?? [], null, 'key');
    check($status === 200 && $owner['component'] === 'Workspace/Search/Page', 'the results page answers 200 with a provider that throws');
    check(array_keys($groups) === ['app.notes', 'app.admin', 'kit.people'] && $owner['props']['partial'] === true, 'the owner gets every group they may see, in order, and the page says a source failed');
    check(array_column($groups['app.notes']['results'], 'label') === ['Ada plan'] && array_column($groups['kit.people']['results'], 'label') === ['Ada Owner'], 'results come only from this workspace: no other workspace\'s note or member');
    check(! str_contains(json_encode($owner), 'secret'), 'neither another workspace\'s rows nor a failure\'s message reaches the page');
    check(str_ends_with(strtok((string) $groups['kit.people']['results'][0]['url'], '?'), '/settings/people') && str_contains((string) $groups['kit.people']['results'][0]['url'], 'owner%40example.test'), 'someone who manages people gets a link to the person in People');
    $logged = $app['log']->driver()->getLogger()->getHandlers()[0]->getRecords();
    $failure = array_values(array_filter($logged, fn ($record) => $record->message === 'Search provider failed'))[0] ?? null;
    check($failure !== null && $failure->context['provider'] === 'app.broken' && $failure->context['exception_class'] === RuntimeException::class && ! str_contains(json_encode($failure->context), 'secret'), 'the failure is logged by provider and class, without its message or the term');

    [, $shouted] = $get('owner', '/@acme/search?q=ADA');
    check(array_column(array_column($shouted['props']['groups'], null, 'key')['kit.people']['results'], 'label') === ['Ada Owner'], 'the people provider ignores case');
    // PostgreSQL's like is case-sensitive: matches() must compile to ilike there. Compiled only; no PostgreSQL server is needed.
    $postgres = new Illuminate\Database\PostgresConnection(fn () => null, 'kit', '', ['driver' => 'pgsql']);
    $sql = (new SearchQuery('Ada', new Principal(1), 1, 5))->matches($postgres->table('users'), ['name', 'email'])->toSql();
    check(substr_count($sql, 'ilike') === 2 && ! preg_match('/\blike\b/', str_replace('ilike', '', $sql)) && str_contains($sql, ' or '), 'matches() compiles to case-insensitive ilike on PostgreSQL, one per column, ORed');

    [, $member] = $get('member', '/@acme/search?q=ada');
    $memberGroups = array_column($member['props']['groups'], null, 'key');
    check(array_keys($memberGroups) === ['app.notes', 'kit.people'], 'a member is not asked providers gated by an ability they lack');
    check($memberGroups['kit.people']['results'][0]['url'] === null, 'a member sees who matched, with no People link');

    [$status] = $get('outsider', '/@acme/search?q=ada');
    check($status !== 200, 'someone outside the workspace cannot search it');
    [$status, $palette, $response] = $get('owner', '/@acme/search?q=ada', true);
    check($status === 200 && array_column($palette['groups'], 'key') === ['app.notes', 'app.admin', 'kit.people'] && $palette['partial'] === true && str_contains((string) $response->headers->get('Cache-Control'), 'no-store'), 'the palette gets the same groups as JSON, never cached');
    [$status] = $get('owner', '/@acme/search?q='.str_repeat('x', 101), true);
    check($status === 422, 'a term over 100 characters is refused');
    [, $empty] = $get('owner', '/@acme/search');
    check($empty['props']['groups'] === [] && $empty['props']['query'] === '', 'no term asks no provider');

    // The shell names search only where the viewer may use it.
    [, $page] = $get('member', '/@acme/search?q=x');
    check(($page['props']['shell']['search'] ?? null) === ['url' => '/search', 'label' => 'Search', 'placeholder' => 'Search Acme'], 'the shell carries the search descriptor (short form for a one-workspace member) for a viewer who may search');

    // Opt-in: with workspace.search off the page is absent and the shell offers no search.
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => true]);
    Feature::flushCache();
    [$status] = $get('owner', '/@acme/search?q=ada');
    [, $dashboard] = $get('owner', '/@acme');
    check($status === 404 && ($dashboard['props']['shell']['search'] ?? null) === null, 'with workspace.search off, search is a 404 and the shell offers none');
    // Provider gating by capability: dashboard.read's capability off hides its providers even from the owner, while members.manage's still answers.
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => false, 'workspace.search' => true]);
    Feature::flushCache();
    [, $gated] = $get('owner', '/@acme/search?q=ada');
    check(array_column($gated['props']['groups'], 'key') === ['app.admin'], 'a provider whose capability is off is not asked, even for the owner');
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true]);
    Feature::flushCache();

    // MCP parity: the tool is registered, named workspace_search, and returns the page's groups for the same person.
    check(in_array(SearchWorkspace::class, $registry->mcpTools(), true) && (new SearchWorkspace)->name() === 'workspace_search', 'the workspace_search tool is registered with the mcp feature');
    $request = Request::create('https://kit.test/mcp');
    $request->setUserResolver(fn () => $people['member']);
    $app->instance('request', $request);
    $call = new ReflectionMethod(SearchWorkspace::class, 'call');
    $tool = fn (string $who, int $in, string $q) => Tey\Kit\Features\Workspaces\Workspaces::run($in, function () use ($call, $people, $who, $in, $q) { return $call->invoke(new SearchWorkspace, new Principal((int) $people[$who]->id), $in, ['q' => $q]); });
    $result = $tool('member', $workspace->id, 'ada');
    check(array_column($result['groups'], 'key') === array_column($member['props']['groups'], 'key') && array_column($result['groups'][0]['results'], 'label') === array_column($member['props']['groups'][0]['results'], 'label') && $result['partial'] === true, 'the MCP tool returns the same groups and results as the page, for the same person');
    try {
        $tool('outsider', $workspace->id, 'ada');
        check(false, 'the MCP tool refuses someone outside the workspace');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'workspace_not_found', 'the MCP tool refuses someone outside the workspace');
    }

    // The limit is per person, shared by the page, the palette and the tool.
    $config->set('kit.search.per_minute', 3);
    RateLimiter::clear('kit-search:'.$people['member']->id);
    RateLimiter::clear('kit-search:'.$people['owner']->id);
    $statuses = [$get('member', '/@acme/search?q=a')[0], $get('member', '/@acme/search?q=a', true)[0]];
    $tool('member', $workspace->id, 'a');
    [$limited] = $get('member', '/@acme/search?q=a', true);
    try {
        $tool('member', $workspace->id, 'a');
        $mcpLimited = false;
    } catch (RuntimeException $error) {
        $mcpLimited = $error->getMessage() === Searches::RATE_LIMITED;
    }
    check($statuses === [200, 200] && $limited === 429 && $mcpLimited, 'past the per-minute limit the page answers 429 and the tool refuses rate_limited, counted together');
    check($get('owner', '/@acme/search?q=a', true)[0] === 200, 'one person\'s limit does not limit another');

    echo "Search checks on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack. No browser: the palette, ⌘K and the sheet are not rendered here, and the MCP tool's operation is called directly, not over the MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
