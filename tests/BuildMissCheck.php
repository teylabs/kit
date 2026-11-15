<?php

// A missing file under the Vite build directory answers a plain 404 before the `web` group (Tey\Kit\Http\MissingBuildAsset): a synthetic host booted as
// Laravel boots one (Application::configure, the framework's own `web` group, the real HTTP kernel), with a `web` route and a `web` fallback, as an app's
// catch-all page would be. Through $kernel->handle(): a missing /build/… file is a text/plain 404 with no Set-Cookie and never reaches the fallback; a normal
// route still gets its session and XSRF cookies; a file that exists, a path that only starts with `build`, and the default directory once the app sets
// another with Vite::useBuildDirectory() go through to the app. Synthetic app in a temp directory; no database, web server, network or browser.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Http\MissingBuildAsset;
use Tey\Kit\KitServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function buildMissCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class BuildMissHostProvider extends ServiceProvider
{
    public static int $fallbacks = 0;

    public function boot(): void
    {
        Route::middleware('web')->get('/page', fn () => 'page');
        Route::fallback(function () {
            self::$fallbacks++;

            return response('the app\'s own page', 404);
        })->middleware('web');
    }
}

try {
    $root = sys_get_temp_dir().'/kit-build-miss-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'config', 'public/build/assets'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/public/build/assets/app-1a2b3c.js', 'export {};');
    file_put_contents($root.'/composer.json', '{"name": "example/host", "autoload": {"psr-4": {"App\\\\": "app/"}}}');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['name' => 'Acme Travel', 'env' => 'testing', 'debug' => false, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array', 'cookie' => 'acme_session'];");
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\\Handler\\NullHandler::class]]];");
    $app = Application::configure($root)
        ->withMiddleware(fn ($middleware) => null)
        ->withExceptions(fn ($exceptions) => null)
        ->withProviders([KitServiceProvider::class, BuildMissHostProvider::class])
        ->create();
    $app->make(ConsoleKernel::class)->bootstrap();
    $kernel = $app->make(Kernel::class);
    buildMissCheck($kernel->hasMiddleware(MissingBuildAsset::class), 'MissingBuildAsset is global middleware, so it runs before routing and the `web` group');

    $get = function (string $path) use ($kernel): array {
        $before = BuildMissHostProvider::$fallbacks;
        $request = Request::create('https://acme.test'.$path);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $cookies = array_map(fn ($cookie): string => $cookie->getName(), $response->headers->getCookies());
        sort($cookies);

        return ['status' => $response->getStatusCode(), 'type' => (string) $response->headers->get('Content-Type'), 'body' => (string) $response->getContent(), 'cookies' => $cookies, 'app' => BuildMissHostProvider::$fallbacks > $before];
    };
    $miss = fn (array $answer): bool => $answer['status'] === 404 && str_starts_with($answer['type'], 'text/plain') && $answer['body'] === 'Not Found' && $answer['cookies'] === [] && ! $answer['app'];

    $missing = $get('/build/assets/Page-0ld.js');
    buildMissCheck($miss($missing), 'a missing /build/assets/Page-0ld.js answers a text/plain 404 with a short body, no Set-Cookie, and never reaches the app ('.json_encode($missing).')');
    buildMissCheck($miss($get('/build/x.js')), 'a missing /build/x.js answers the same plain 404');

    $page = $get('/page');
    buildMissCheck($page['status'] === 200 && $page['body'] === 'page' && in_array('acme_session', $page['cookies'], true) && in_array('XSRF-TOKEN', $page['cookies'], true), 'a normal route is unaffected: 200, with its session and XSRF cookies ('.implode(', ', $page['cookies']).')');

    // Through to the app: what the web server would serve, and paths outside the directory. Each reaches the fallback, which starts the session.
    $file = $get('/build/assets/app-1a2b3c.js');
    buildMissCheck($file['app'] && $file['cookies'] !== [], 'a file that exists under public/build goes through to the app (a server that did not serve it), not answered here');
    $near = $get('/buildings/x.js');
    buildMissCheck($near['app'] && $near['body'] === 'the app\'s own page', 'a path that only starts with "build" (/buildings/x.js) goes through to the app');
    buildMissCheck($get('/build')['app'], 'the directory itself (/build) goes through to the app');

    // The directory is the app's Vite build directory, read at request time.
    Vite::useBuildDirectory('/assets/dist/');
    buildMissCheck($miss($get('/assets/dist/Page-0ld.js')), 'after Vite::useBuildDirectory(\'assets/dist\'), a missing /assets/dist/Page-0ld.js is the plain 404');
    buildMissCheck($get('/build/x.js')['app'], 'and /build/x.js, no longer the build directory, goes through to the app');

    echo "Missing build files: a synthetic host through the real HTTP kernel. No web server (its own config should serve the build directory), database, Octane worker or consumer app.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
