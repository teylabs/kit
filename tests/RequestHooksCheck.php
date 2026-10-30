<?php

// The registry's request hooks run on real requests. A host booted as Laravel boots one (Application::configure, the framework's own `web` group, the
// real HTTP kernel), with a host provider that boots after the kit's and calls appendMiddlewareToGroup: the re-sync that rebuilds the router's groups
// from the kernel's and dropped a router-only push. Two requests through $kernel->handle(): the hooks run on each, the request memo is live within
// each and holds that request's values, and the access feature's permission, capability and entitlement memos start empty on each. Then
// Inertia::flushShared() between two page requests, as pest-plugin-browser does before each one: the second page still has every kit prop, and a
// key the host shared itself keeps the host's value.
// Synthetic app in a temp directory, SQLite memory; no network, no browser.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function hooksCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** A middleware some host or package appends to `web` once the kit has booted. */
class LateHostMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}

/** The host's own provider, registered after the kit's: a probe hook on the registry, a page, and the late append that re-syncs the router's groups. */
class LateHostProvider extends ServiceProvider
{
    public static int $hooks = 0;

    public static int $resolves = 0;

    public function register(): void
    {
        $this->app->extend(Registry::class, fn (Registry $registry): Registry => $registry->beforeRequestUsing(function (): void {
            self::$hooks++;
        }));
    }

    public function boot(): void
    {
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', LateHostMiddleware::class);
        Route::middleware('web')->get('/page', fn () => Inertia::render('Probe'));
        Route::middleware('web')->get('/hooks', function (Request $request) {
            $memo = RequestMemo::instance();
            $viewer = fn (): string => (string) $request->query('as', '');
            $first = $memo->get('viewer', function () use ($viewer): string {
                self::$resolves++;

                return $viewer();
            });
            $again = $memo->get('viewer', function () use ($viewer): string {
                self::$resolves++;

                return $viewer();
            });
            $memos = [];
            foreach ([Permissions::class, Capabilities::class, Entitlements::class] as $class) {
                $memos[class_basename($class)] = (new ReflectionProperty($class, 'memo'))->getValue(app($class));
            }

            return response()->json(['hooks' => self::$hooks, 'viewer' => $first, 'again' => $again, 'memos' => $memos]);
        });
    }
}

try {
    $root = sys_get_temp_dir().'/kit-request-hooks-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.json', '{"name": "example/host", "autoload": {"psr-4": {"App\\\\": "app/"}}}');
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => ['core', 'workspaces', 'access', 'shell', 'session-ui'], 'models' => ['user' => User::class]], true).';');
    file_put_contents($root.'/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]];");
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['name' => 'Northwind Farms', 'env' => 'testing', 'debug' => true, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\\Handler\\NullHandler::class]]];");
    // The framework's own `web` group (withMiddleware), Inertia (package discovery's, in a real host), the kit through its composition root, then the host's provider.
    $app = Application::configure($root)->withMiddleware(fn ($middleware) => null)->withExceptions(fn ($exceptions) => $exceptions->render(fn (Throwable $error) => response(get_class($error).': '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine(), 500)))->withProviders([\Inertia\ServiceProvider::class, LaravelDataServiceProvider::class, KitServiceProvider::class, FeaturesServiceProvider::class, LateHostProvider::class])->create();
    $app->make(ConsoleKernel::class)->bootstrap();
    $kernel = $app->make(Kernel::class);
    hooksCheck(in_array(LateHostMiddleware::class, $app['router']->getMiddlewareGroups()['web'], true), 'the host\'s late append re-synced the router\'s `web` group from the kernel\'s');

    $send = function (string $as) use ($kernel): array {
        $request = Request::create('https://kit.test/hooks?as='.$as);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body)) {
            throw new RuntimeException('GET /hooks answered '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 300));
        }

        return $body;
    };
    $first = $send('ada');
    hooksCheck($first['hooks'] === 1 && LateHostProvider::$resolves === 1 && $first['viewer'] === 'ada' && $first['again'] === 'ada', 'request 1: the registry\'s hooks ran, and the request memo is live (one resolve for two reads) with Ada');
    // Stale answers left behind, as a long-lived worker would hold them from the last request.
    foreach ([Permissions::class, Capabilities::class, Entitlements::class] as $class) {
        (new ReflectionProperty($class, 'memo'))->setValue($app->make($class), ['stale' => true]);
    }
    $second = $send('grace');
    hooksCheck($second['hooks'] === 2 && LateHostProvider::$resolves === 2 && $second['viewer'] === 'grace' && $second['again'] === 'grace', 'request 2: the hooks ran again, and the memo answers Grace, not Ada, once');
    hooksCheck($second['memos'] === ['Permissions' => [], 'Capabilities' => [], 'Entitlements' => []], 'request 2: the permission, capability and entitlement memos began empty, not with request 1\'s answers ('.json_encode($second['memos']).')');

    $page = function () use ($kernel): array {
        $request = Request::create('https://kit.test/page', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body) || ! isset($body['props'])) {
            throw new RuntimeException('GET /page answered '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 300));
        }

        return $body['props'];
    };
    $kitKeys = ['kitUi', 'kitToasts', 'shell', 'environment', 'kitPassword', 'passkeyOffer'];
    $missing = fn (array $props): array => array_values(array_diff($kitKeys, array_keys($props)));
    $one = $page();
    hooksCheck($missing($one) === [], 'page 1 through the kernel has every kit prop (missing: '.implode(', ', $missing($one)).')');
    Inertia::flushShared();
    $two = $page();
    hooksCheck($missing($two) === [] && ($two['shell']['kind'] ?? null) === 'guest', 'page 2, after Inertia::flushShared() as pest-plugin-browser calls it, still has kitUi, kitToasts, shell, environment, kitPassword and passkeyOffer (missing: '.implode(', ', $missing($two)).')');
    Inertia::flushShared();
    Inertia::share('shell', fn (): array => ['kind' => 'host shell']);
    $three = $page();
    hooksCheck(($three['shell'] ?? null) === ['kind' => 'host shell'] && $missing($three) === [], 'page 3, after a flush and the host\'s own `shell` share, keeps the host\'s shell and has the kit\'s other props');
    $four = $page();
    hooksCheck(($four['shell'] ?? null) === ['kind' => 'host shell'], 'page 4, with no flush, still keeps the host\'s shell');

    echo "Request hooks: a synthetic host through the real HTTP kernel on SQLite memory. No Octane worker, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
