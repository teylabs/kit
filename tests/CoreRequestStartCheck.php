<?php

// The kit's request start is core's, so it runs on a host without session-ui. A core and shell host booted as Laravel boots one (Application::configure,
// the framework's own `web` group, the real HTTP kernel), with its own middleware appended to `web` before the kit boots: one that reads through the
// request memo twice, and one that counts the request memo's state. Through $kernel->handle(): StartKitRequest is first in `web` and in the priority
// list, exactly once; the host's second memo read resolves nothing (the memo is on before the host's middleware); after Inertia::flushShared() between
// two requests the second still has `kitUi`, `kitToasts` and `shell`; and a `shell` the host shares on the factory before the request keeps its value.
// Synthetic app in a temp directory; no database, network or browser.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Tey\Kit\Features\Core\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function startCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class StartUser extends User
{
    protected $table = 'users';
}

/** The host's own early middleware: it reads one value through the request memo twice, as a tenant resolver and a policy would. */
class HostMemoReader
{
    /** @var list<int> */
    public static array $resolved = [];

    public function handle(Request $request, Closure $next): mixed
    {
        $count = 0;
        foreach ([1, 2] as $_) {
            RequestMemo::instance()->get('host:tenant', function () use (&$count): string {
                $count++;

                return 'northwind';
            });
        }
        self::$resolved[] = $count;

        return $next($request);
    }
}

class CoreStartHostProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/page', fn () => Inertia::render('Probe'));
    }
}

try {
    $root = sys_get_temp_dir().'/kit-core-start-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.json', '{"name": "example/host", "autoload": {"psr-4": {"App\\\\": "app/"}}}');
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => ['core', 'shell'], 'models' => ['user' => StartUser::class]], true).';');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['name' => 'Acme Travel', 'env' => 'testing', 'debug' => false, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\\Handler\\NullHandler::class]]];");
    $app = Application::configure($root)
        ->withMiddleware(fn ($middleware) => $middleware->web(append: [HostMemoReader::class]))
        ->withExceptions(fn ($exceptions) => null)
        ->withProviders([\Inertia\ServiceProvider::class, LaravelDataServiceProvider::class, KitServiceProvider::class, FeaturesServiceProvider::class, CoreStartHostProvider::class])
        ->create();
    $app->make(ConsoleKernel::class)->bootstrap();
    $kernel = $app->make(Kernel::class);
    startCheck(! in_array('session-ui', (array) config('kit.features'), true), 'the host adopts core and shell, without session-ui');

    $group = $app['router']->getMiddlewareGroups()['web'];
    $priority = $app['router']->middlewarePriority;
    $count = fn (array $list): int => count(array_filter($list, fn (mixed $m): bool => $m === StartKitRequest::class));
    startCheck(($group[0] ?? null) === StartKitRequest::class && $count($group) === 1, 'StartKitRequest is first in `web`, once ('.implode(', ', array_map('class_basename', $group)).')');
    startCheck(($priority[0] ?? null) === StartKitRequest::class && $count($priority) === 1, 'StartKitRequest is first in the middleware priority list, once');

    $page = function (string $path) use ($kernel): array {
        $request = Request::create('https://kit.test'.$path, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body) || ! isset($body['props'])) {
            throw new RuntimeException('GET '.$path.' answered '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 300));
        }

        return $body['props'];
    };

    // The memo is on before the host's own middleware: its second read resolves nothing.
    $first = $page('/page');
    startCheck((HostMemoReader::$resolved[0] ?? null) === 1, 'memo: the host\'s early middleware reads through the request memo twice and resolves once (resolved: '.json_encode(HostMemoReader::$resolved).')');
    startCheck(isset($first['kitUi'], $first['kitToasts'], $first['shell']), 'first request: kitUi, kitToasts and shell are shared');

    // A flush between two requests (pest-plugin-browser's, an Octane-style reset) leaves the kit's props for core's StartKitRequest to put back.
    Inertia::flushShared();
    $second = $page('/page');
    startCheck(isset($second['kitUi'], $second['kitToasts'], $second['shell']) && is_array($second['shell']), 'after Inertia::flushShared(), the second request still has kitUi, kitToasts and shell ('.implode(', ', array_keys($second)).')');
    startCheck((HostMemoReader::$resolved[1] ?? null) === 1, 'memo: the second request\'s memo is its own, resolved once again');

    // The host still wins: restore only fills keys Inertia's factory doesn't hold.
    Inertia::flushShared();
    Inertia::share('shell', fn (): array => ['kind' => 'host shell']);
    $host = $page('/page');
    startCheck(($host['shell'] ?? null) === ['kind' => 'host shell'] && isset($host['kitUi'], $host['kitToasts']), 'host precedence: a `shell` the host shared before the request keeps the host\'s value, and the kit fills in kitUi and kitToasts');

    echo "Core request start: a synthetic core and shell host through the real HTTP kernel. No session-ui, database, Octane worker, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
