<?php

// MCP resources: a host's laravel/mcp Resource classes and descriptors registered with Registry::mcpResources(), listed and read by the workspace server under the
// same admission as tools (gated by #[Capability] or a descriptor's capability, absent when off, refused by reason when read anyway), static docs always present,
// workspace-scoped content from the connection's workspace, an unknown URI refused, and the registration refusals. SQLite memory, real Concierge, spatie teams and Pennant.
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
use Tey\Kit\Features\Access\Capabilities;
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
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// An app's tool on the attribute: no ABILITY, so only #[Capability] ties it to the stand-in feature's capability.
#[Tey\Kit\Features\Mcp\Capability('workspace.reports', destructive: true)]
class ReportsTool extends Tey\Kit\Features\Mcp\CapabilityTool
{
    public const ABILITY = 'reports.read';

    protected string $description = 'Read the workspace reports.';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [];
    }

    protected function call(Tey\Kit\Features\Core\Principal $principal, int $workspace, array $validated): array
    {
        return [];
    }
}

#[Tey\Kit\Features\Mcp\Capability('workspace.unknown')]
class UndeclaredTool extends ReportsTool {}

// A static docs resource: no capability, always listed.
#[Laravel\Mcp\Server\Attributes\Name('slide-schema')]
#[Laravel\Mcp\Server\Attributes\Uri('app://docs/slide-schema')]
#[Laravel\Mcp\Server\Attributes\MimeType('text/markdown')]
#[Laravel\Mcp\Server\Attributes\Description('The slide schema.')]
class DocsResource extends Laravel\Mcp\Server\Resource
{
    public function handle(Laravel\Mcp\Request $request): Laravel\Mcp\Response
    {
        return Laravel\Mcp\Response::text('# Slide schema');
    }
}

// A resource on the stand-in capability, reading the connection's workspace.
#[Tey\Kit\Features\Mcp\Capability('workspace.reports')]
#[Laravel\Mcp\Server\Attributes\Uri('app://reports/latest')]
class ReportsResource extends Laravel\Mcp\Server\Resource
{
    use Tey\Kit\Features\Mcp\GatedByCapability;

    public function handle(Laravel\Mcp\Request $request): Laravel\Mcp\Response
    {
        return Laravel\Mcp\Response::text('Latest report of '.Tey\Kit\Features\Workspaces\Workspaces::current()->name);
    }
}

#[Tey\Kit\Features\Mcp\Capability('workspace.reports')]
class UngatedResource extends Laravel\Mcp\Server\Resource {}

#[Tey\Kit\Features\Mcp\Capability('workspace.nope')]
class UndeclaredResource extends ReportsResource {}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-mcp-resources-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'lifecycle' => ['workspace_delete' => true, 'account_delete' => true], 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true, 'workspace.reports' => true], 'mcp' => ['ui' => true]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // A stand-in feature declared the way an app's own would be: a capability, a user-menu link and a planned page. The guard below names none of the
    // capabilities it covers, so this one is covered by the same loop as the kit's.
    $app->make(Registry::class)->capabilities(['workspace.reports'])->abilities(['reports.read' => 'workspace.reports'])
        ->navigation('reports.read', 'Reports', 'reports', 'user-menu')->placeholder('workspaces.reports.index', 'reports', 'reports.read', 'Reports', 'Reports on the workspace.')
        ->capabilityTools([ReportsTool::class])
        ->mcpResources([DocsResource::class, ReportsResource::class,
            ['uri' => 'kit://docs/readme', 'name' => 'readme', 'mimeType' => 'text/markdown', 'description' => 'How to use the server.', 'read' => fn (): string => '# Read me'],
            ['uri' => 'kit://reports/summary', 'name' => 'reports-summary', 'capability' => 'workspace.reports', 'read' => fn (Laravel\Mcp\Request $request, $workspace): string => 'Reports for '.$workspace->name]]);
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
    $owner = $people['owner'];

    // The server's resources/list and resources/read with the kit's read method, for the owner, in the workspace, as a connection would run them: the listed URIs, or a read's text or error.
    $context = function () use ($app, $owner): Laravel\Mcp\Server\ServerContext {
        $request = Request::create('https://kit.test/mcp');
        $request->setUserResolver(fn () => $owner);
        $app->instance('request', $request);

        return new Laravel\Mcp\Server\ServerContext(['2025-06-18'], [], new Laravel\Mcp\Schema\Implementation('Kit', '0.1.0'), '', 50, 15, [], $app->make(Registry::class)->mcpResourceInstances(), []);
    };
    $list = fn (): array => $workspace->execute(function () use ($context): array {
        $result = (new Laravel\Mcp\Server\Methods\ListResources)->handle(new Laravel\Mcp\Transport\JsonRpcRequest(1, 'resources/list', []), $context())->toArray()['result'];

        return array_map(fn (array $resource): array => [$resource['uri'], $resource['name'], $resource['mimeType'], $resource['description']], $result['resources']);
    });
    $read = fn (string $uri): array => $workspace->execute(function () use ($context, $uri, $app): array {
        try {
            $response = $app->make(Tey\Kit\Features\Mcp\Methods\ReadResource::class)->handle(new Laravel\Mcp\Transport\JsonRpcRequest(2, 'resources/read', ['uri' => $uri]), $context());
        } catch (Laravel\Mcp\Exceptions\JsonRpcException $error) {
            return ['error' => $error->getMessage(), 'code' => $error->getCode()];
        }
        $contents = $response->toArray()['result']['contents'];

        return ['uri' => $contents[0]['uri'], 'mimeType' => $contents[0]['mimeType'], 'text' => $contents[0]['text']];
    });

    httpCheck($list() === [
        ['app://docs/slide-schema', 'slide-schema', 'text/markdown', 'The slide schema.'],
        ['app://reports/latest', 'reports-resource', 'text/plain', 'Reports Resource'],
        ['kit://docs/readme', 'readme', 'text/markdown', 'How to use the server.'],
        ['kit://reports/summary', 'reports-summary', 'text/plain', ''],
    ], 'with the capability on, all four resources are listed in registration order: class and descriptor, static and gated');
    httpCheck($read('app://docs/slide-schema') === ['uri' => 'app://docs/slide-schema', 'mimeType' => 'text/markdown', 'text' => '# Slide schema'], 'a static class resource reads its text under its mime type');
    httpCheck($read('kit://docs/readme') === ['uri' => 'kit://docs/readme', 'mimeType' => 'text/markdown', 'text' => '# Read me'], 'a static descriptor resource reads its closure\'s text');
    httpCheck($read('app://reports/latest')['text'] === 'Latest report of Acme', 'a gated class resource reads content from the connection\'s workspace');
    httpCheck($read('kit://reports/summary')['text'] === 'Reports for Acme', 'a gated descriptor resource receives the connection\'s workspace');
    $unknown = $read('app://docs/nope');
    httpCheck(isset($unknown['error']) && str_contains($unknown['error'], 'not found') && $unknown['code'] === -32602, 'an unknown URI is refused (invalid params, not found)');
    httpCheck(($read('')['code'] ?? null) === -32602, 'a missing uri is refused');

    app(Tey\Kit\Features\Access\Capabilities::class)->setForWorkspace('workspace.reports', $workspace, false);
    Laravel\Pennant\Feature::flushCache();
    httpCheck(array_column($list(), 0) === ['app://docs/slide-schema', 'kit://docs/readme'], 'with the capability off, both gated resources are absent and the static ones remain');
    $off = $read('app://reports/latest');
    httpCheck(($off['error'] ?? null) === 'capability_unavailable' && $off['code'] === -32602, 'reading the gated class resource anyway is refused as capability_unavailable, not as unknown');
    httpCheck(($read('kit://reports/summary')['error'] ?? null) === 'capability_unavailable', 'so is the gated descriptor');
    httpCheck($read('kit://docs/readme')['text'] === '# Read me', 'the static docs still read');
    app(Tey\Kit\Features\Access\Capabilities::class)->setForWorkspace('workspace.reports', $workspace, null);
    Laravel\Pennant\Feature::flushCache();
    httpCheck(count($list()) === 4 && $read('app://reports/latest')['text'] === 'Latest report of Acme', 'switched back on, the gated resources return');

    // The workspace server serves them: its resources are the registry's, and its resources/read is the kit's.
    $server = new ReflectionClass(Tey\Kit\Features\Mcp\WorkspaceServer::class);
    $instance = $server->newInstanceWithoutConstructor();
    $boot = $server->getMethod('boot');
    $boot->invoke($instance);
    $resources = $server->getProperty('resources')->getValue($instance);
    $methods = $server->getProperty('methods')->getValue($instance);
    httpCheck(count($resources) === 4 && $resources[0] instanceof DocsResource && $resources[3] instanceof Tey\Kit\Features\Mcp\DescriptorResource && $resources[3]->uri() === 'kit://reports/summary', 'the workspace server boots with the registered resources');
    httpCheck(($methods['resources/read'] ?? null) === Tey\Kit\Features\Mcp\Methods\ReadResource::class && ($methods['tools/call'] ?? null) === Tey\Kit\Features\Mcp\Methods\CallTool::class, 'its resources/read is the kit\'s method, beside tools/call');

    // Registration refusals.
    foreach ([
        'a class that is not a Resource' => [[ReportsTool::class], 'extends Laravel\\Mcp\\Server\\Resource'],
        'a capability resource without the gate' => [[UngatedResource::class], 'GatedByCapability'],
        'an undeclared capability on a class' => [[UndeclaredResource::class], 'declared capability'],
        'an undeclared capability on a descriptor' => [[['uri' => 'kit://x', 'name' => 'x', 'capability' => 'workspace.nope', 'read' => fn () => '']], 'declared capability'],
        'a descriptor without a uri' => [[['name' => 'x', 'read' => fn () => '']], 'needs a uri'],
        'a descriptor without a name' => [[['uri' => 'kit://x', 'read' => fn () => '']], 'needs a name'],
        'a descriptor without a read' => [[['uri' => 'kit://x', 'name' => 'x']], 'callable read'],
        'a relative uri' => [[['uri' => 'docs/x', 'name' => 'x', 'read' => fn () => '']], 'absolute'],
        'a uri registered twice' => [[['uri' => 'kit://docs/readme', 'name' => 'again', 'read' => fn () => '']], 'registered once'],
        'something else' => [[42], 'class name or a descriptor'],
    ] as $case => [$resources, $contains]) {
        try {
            $app->make(Registry::class)->mcpResources($resources);
            throw new LogicException('no refusal');
        } catch (RuntimeException $error) {
            httpCheck(str_contains($error->getMessage(), $contains), $case.' is refused at registration ('.$error->getMessage().')');
        }
    }
    httpCheck(count($app->make(Registry::class)->registeredMcpResources()) === 4, 'no refusal registered anything');
    $bare = new Tey\Kit\Features\Core\Registry;
    try {
        $bare->mcpResources([DocsResource::class]);
        throw new LogicException('no refusal');
    } catch (RuntimeException $error) {
        httpCheck(str_contains($error->getMessage(), 'mcp feature'), 'without the mcp feature, resources are refused');
    }
    $descriptorRead = $workspace->execute(fn () => (new Tey\Kit\Features\Mcp\DescriptorResource(['uri' => 'kit://bad', 'name' => 'bad', 'read' => fn () => 42]))->handle(new Laravel\Mcp\Request([])));
} catch (RuntimeException $error) {
    if (str_contains($error->getMessage(), 'returns a string or a Response')) {
        echo "PASS a descriptor read that returns neither a string nor a Response is refused\n";
        echo "MCP resources checks on SQLite memory with real Concierge, spatie teams and Pennant, through the server's resources/list and the kit's resources/read methods; no MCP transport, no browser, no consumer app.\n";
        exit(0);
    }
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
fwrite(STDERR, "Expected the bad descriptor read to be refused.\n");
exit(1);
