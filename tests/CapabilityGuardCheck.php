<?php

// Off means absent: for every capability the Registry declares, switched off in a workspace, each route it gates is a 404 and nothing it contributes reaches shared props,
// page props, capability listings or the MCP tool list; switched back on, all of it returns. SQLite memory, real Concierge, spatie teams and Pennant, synthetic users.
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

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-capability-guard-'.bin2hex(random_bytes(4));
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
        ->capabilityTools([ReportsTool::class]);
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
    $get = function (string $path) use ($app, $owner): array {
        Auth::guard('web')->setUser($owner);
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $owner);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };
    // Every string in a page's JSON: the shared shell and the page's own props alike.
    $strings = function (string $body): array {
        $page = json_decode($body, true);
        $found = [];
        array_walk_recursive($page, function ($value, $key) use (&$found): void {
            if (is_string($value)) {
                $found[] = $key.'='.$value;
            }
        });

        return $found;
    };
    $workspaceGet = fn (string $suffix): array => $get('/@acme'.($suffix === '' ? '' : '/'.$suffix));
    $tool = fn (string $class): bool => $workspace->execute(function () use ($app, $class, $owner): bool {
        $request = Request::create('https://kit.test/mcp');
        $request->setUserResolver(fn () => $owner);
        $app->instance('request', $request);

        return $app->make($class)->shouldRegister();
    });
    // A call to the tool by name, through the server's tools/call with the tools it lists here: the refusal text, or null when the tool ran.
    $call = fn (string $class): ?string => $workspace->execute(function () use ($app, $class, $owner): ?string {
        $request = Request::create('https://kit.test/mcp');
        $request->setUserResolver(fn () => $owner);
        $app->instance('request', $request);
        $tools = array_values(array_filter($app->make(Registry::class)->mcpTools(), fn (string $tool): bool => $app->make($tool)->eligibleForRegistration()));
        $context = new Laravel\Mcp\Server\ServerContext(['2025-06-18'], [], new Laravel\Mcp\Schema\Implementation('Kit', '0.1.0'), '', 50, 15, $tools, [], []);
        try {
            $response = $app->make(Tey\Kit\Features\Mcp\Methods\CallTool::class)->handle(new Laravel\Mcp\Transport\JsonRpcRequest(1, 'tools/call', ['name' => $app->make($class)->name(), 'arguments' => []]), $context);
        } catch (Laravel\Mcp\Exceptions\JsonRpcException $e) {
            return 'not found';
        }
        $result = $response->toArray()['result'] ?? [];

        return ($result['isError'] ?? false) ? ($result['content'][0]['text'] ?? '') : null;
    });

    // Leaving a TenantBoundary flushes Pennant's cache only for a store with a backend. Under the array store the cache is the store, so a flag set off stays off across the boundary.
    $config->set('pennant.stores.database', ['driver' => 'database']);
    $config->set('pennant.default', 'database');
    httpCheck(Tey\Kit\Features\Workspaces\TenantBoundary::flushes() === true, 'with a store that has a backend (database), the boundary flushes Pennant\'s cache so the next tenant reads fresh flags');
    $config->set('pennant.default', 'array');
    Feature::define('kit.boundary.flag', fn (): bool => true);
    Feature::for($workspace)->deactivate('kit.boundary.flag');
    httpCheck(Tey\Kit\Features\Workspaces\TenantBoundary::flushes() === false, 'under the array store the boundary does not flush');
    $inside = $app->make(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run((int) $workspace->id, fn (): bool => Feature::for($workspace)->active('kit.boundary.flag'));
    httpCheck($inside === false && Feature::for($workspace)->active('kit.boundary.flag') === false, 'a flag deactivated before a request through the boundary is still off inside it and after it (array store)');
    Feature::flushCache();

    // What each capability contributes, read from the Registry alone.
    $abilities = $registry->abilityMap();
    $surfaces = [];
    foreach ($registry->workspaceCapabilities() as $capability) {
        $gates = fn (?string $ability): bool => $ability !== null && ($abilities[$ability] ?? null) === $capability;
        $surfaces[$capability] = [
            'navigation' => array_values(array_filter($registry->navigationLinks(), fn (array $link): bool => $gates($link['ability']))),
            'sections' => array_values(array_filter($registry->settingsSections('workspace'), fn (array $section): bool => $gates($section['ability']))),
            'placeholders' => array_values(array_filter($registry->placeholders(), fn (array $placeholder): bool => ! $placeholder['settings'] && $gates($placeholder['ability']))),
            'tools' => array_values(array_filter($registry->mcpTools(), fn (string $class): bool => ($attribute = Tey\Kit\Features\Mcp\Capability::of($class)) !== null ? $attribute->capability === $capability : defined($class.'::ABILITY') && $gates($class::ABILITY))),
            'abilities' => array_keys(array_filter($abilities, fn (string $gate): bool => $gate === $capability)),
        ];
    }
    httpCheck(count($surfaces) >= 3 && isset($surfaces['workspace.reports']), 'the guard covers every capability the Registry declares, including one it never names');
    httpCheck($surfaces['workspace.reports']['tools'] === [ReportsTool::class] && $app->make(ReportsTool::class)->name() === 'workspace_reports' && Tey\Kit\Features\Mcp\Capability::of(ReportsTool::class)->destructive(), 'an attribute-registered tool is seen under the capability it names, named after it');
    foreach ([fn () => $registry->capabilityTools([Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings::class]), fn () => $registry->capabilityTools([UndeclaredTool::class])] as $wrong) {
        try {
            $wrong();
            $refused = false;
        } catch (RuntimeException) {
            $refused = true;
        }
        httpCheck($refused, 'capabilityTools() refuses a tool with no attribute, or one naming an undeclared capability');
    }
    // Pages the guard reads props from: every page any capability contributes, the settings landing page and an account page outside the workspace.
    $pages = ['settings', 'me'];
    foreach ($surfaces as $surface) {
        foreach ($surface['navigation'] as $link) {
            $pages[] = $link['suffix'];
        }
        foreach ($surface['sections'] as $section) {
            $pages[] = 'settings/'.$section['key'];
        }
        foreach ($surface['placeholders'] as $placeholder) {
            $pages[] = $placeholder['uri'];
        }
    }
    $pages = array_values(array_unique($pages));
    $render = function () use ($pages, $workspaceGet, $get, $strings): array {
        $seen = [];
        foreach ($pages as $page) {
            [$status, $body] = $page === 'me' ? $get('/me/profile') : $workspaceGet($page);
            $seen[$page] = [$status, $status === 200 ? $strings($body) : []];
        }

        return $seen;
    };
    // Whether what the capability contributes is present across the rendered pages, surface by surface; the routes it gates are checked by status.
    $present = function (array $surface, array $seen): array {
        $all = array_merge(...array_column($seen, 1));
        $found = [];
        foreach ($surface['navigation'] as $link) {
            $found['navigation '.$link['label']] = in_array('label='.$link['label'], $all, true);
        }
        foreach ($surface['sections'] as $section) {
            $found['settings section '.$section['key']] = in_array('key='.$section['key'], $all, true);
        }
        foreach (['navigation' => 'suffix', 'sections' => 'key', 'placeholders' => 'uri'] as $kind => $field) {
            foreach ($surface[$kind] as $entry) {
                $path = $kind === 'sections' ? 'settings/'.$entry[$field] : $entry[$field];
                // The dashboard is the workspace's root, "/" in the short form, which is also every trail's home: its link is checked by label above.
                if ($path !== '') {
                    $found['link to '.$path] = in_array('url=/'.$path, $all, true) || in_array('url=/@acme/'.$path, $all, true);
                }
            }
        }

        return $found;
    };

    foreach ($surfaces as $capability => $surface) {
        $count = count($surface['navigation']) + count($surface['sections']) + count($surface['placeholders']) + count($surface['tools']);
        // Fail closed: a capability with nothing the guard can see would pass vacuously.
        httpCheck($count > 0, $capability.' contributes at least one surface the guard can see');
        $on = $render();
        foreach ($surface['navigation'] as $link) {
            httpCheck($on[$link['suffix']][0] === 200, $capability.' on: /'.$link['suffix'].' opens');
        }
        foreach ($present($surface, $on) as $what => $here) {
            httpCheck($here, $capability.' on: '.$what.' is present');
        }
        foreach ($surface['tools'] as $class) {
            httpCheck($tool($class), $capability.' on: MCP tool '.class_basename($class).' is listed');
        }

        app(Capabilities::class)->setForWorkspace($capability, $workspace, false);
        $off = $render();
        foreach (array_unique(array_merge(array_column($surface['navigation'], 'suffix'), array_map(fn (array $section): string => 'settings/'.$section['key'], $surface['sections']), array_column($surface['placeholders'], 'uri'))) as $page) {
            // The workspace root is not absent without its dashboard: it opens another page (test:workspace-root).
            if ($page === '' && $capability === 'workspace.dashboard') {
                httpCheck($off[$page][0] === 302, $capability.' off: /@acme opens another page instead of the dashboard');

                continue;
            }
            httpCheck($off[$page][0] === 404, $capability.' off: /@acme'.($page === '' ? '' : '/'.$page).' is a 404');
        }
        foreach ($present($surface, $off) as $what => $here) {
            httpCheck(! $here, $capability.' off: '.$what.' is absent from shared and page props');
        }
        foreach ($surface['tools'] as $class) {
            httpCheck(! $tool($class), $capability.' off: MCP tool '.class_basename($class).' is not listed');
            httpCheck($call($class) === 'capability_unavailable', $capability.' off: a call to MCP tool '.class_basename($class).' is refused as capability_unavailable');
        }
        foreach ($surface['abilities'] as $ability) {
            try {
                $workspace->execute(fn () => app(Tey\Kit\Features\Access\Access::class)->authorize(new Tey\Kit\Features\Core\Principal((int) $owner->id), $ability));
                $refused = null;
            } catch (RuntimeException $error) {
                $refused = $error->getMessage();
            }
            httpCheck($refused === 'capability_unavailable', $capability.' off: '.$ability.' is refused as unavailable, so its writes and tool calls are too');
        }
        $listed = collect($workspace->execute(fn () => app(Capabilities::class)->overview($workspace)))->firstWhere('capability', $capability);
        httpCheck($listed !== null && $listed['workspace'] === false, $capability.' off: the capability listing shows it off');

        app(Capabilities::class)->setForWorkspace($capability, $workspace, null);
        $back = $render();
        httpCheck(array_map(fn (array $page): int => $page[0], $back) === array_map(fn (array $page): int => $page[0], $on), $capability.' back on: every page answers as it did before');
        httpCheck($present($surface, $back) === $present($surface, $on), $capability.' back on: everything it contributes is present again');
        foreach ($surface['tools'] as $class) {
            httpCheck($tool($class), $capability.' back on: MCP tool '.class_basename($class).' is listed again');
        }
    }
    echo "Capability guard on SQLite memory with real Concierge, spatie teams and Pennant; Inertia JSON through router dispatch without the global middleware stack. Covered surfaces: routes from navigation, settings sections and placeholders, the shell's navigation and user menu, settings listings, page props, the capability listing, the MCP tool list (shouldRegister) and a call to an absent tool (the server's tools/call method, no MCP transport). Dashboard widgets and search providers are gated by their abilities (test:dashboard, test:search), and the search page has no navigation link, so neither is discovered here; the kit has no per-feature action-descriptor registry; a route a feature serves without a navigation link, settings section or placeholder is not discovered.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
