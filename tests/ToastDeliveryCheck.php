<?php

// Toast delivery over real requests: the kit's `toast` key (driver `kit`) and inertiaui/notifications' list through the shim (driver `inertiaui`), and the
// assertToast() / assertNoToasts() test macros on both, with the package's own provider booted (whose macros the kit's take precedence over). Synthetic
// app, array session, real Inertia and the real package; no network, no browser.
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\Inertia;
use Inertia\Middleware;
use InertiaUI\Notifications\FlashedToasts;
use InertiaUI\Notifications\NotificationsServiceProvider;
use InertiaUI\Notifications\Toast as PackageToast;
use PHPUnit\Framework\AssertionFailedError;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Testing\ToastAssertions;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function deliveryCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function fails(callable $assertion): bool
{
    try {
        $assertion();
    } catch (AssertionFailedError) {
        return true;
    }

    return false;
}

// Outside a PHPUnit run, an assertion's failure message needs PHPUnit's configuration: the default one, as a bare `phpunit` has.
PHPUnit\TextUI\Configuration\Registry::init((new PHPUnit\TextUI\CliArguments\Builder)->fromParameters([]), PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration::create());

$root = sys_get_temp_dir().'/kit-toast-delivery-'.bin2hex(random_bytes(6));
register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
mkdir($root.'/storage/framework/views', 0700, true);
mkdir($root.'/views', 0700, true);
file_put_contents($root.'/views/app.blade.php', '<div id="app" data-page="{{ json_encode($page) }}"></div>');
$app = new Application($root);
$app->useStoragePath($root.'/storage');
$app->singleton(ExceptionHandler::class, Handler::class);
$app->instance('env', 'testing');
$app->instance('config', $config = new Repository([
    'app' => ['name' => 'Kit', 'env' => 'testing', 'debug' => true, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'locale' => 'en'],
    'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false],
    'view' => ['paths' => [$root.'/views'], 'compiled' => $root.'/storage/framework/views'],
    'kit' => ['toasts' => []],
]));
$app->instance('request', Request::create('https://kit.test'));
Facade::clearResolvedInstances();
Facade::setFacadeApplication($app);
foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, RoutingServiceProvider::class, SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class, \Inertia\ServiceProvider::class, NotificationsServiceProvider::class] as $provider) {
    $app->register($provider);
}
$app->boot();
// What KitServiceProvider does once the app has booted (after the package's provider registered its own macros).
ToastAssertions::register();

$web = [StartSession::class, Middleware::class];
Route::middleware($web)->post('/save', function () {
    Toast::success('Changes saved')->from('save')->style('fly-out');

    return back();
});
Route::middleware($web)->post('/role', function () {
    Toast::success('Role changed')->from('change-role')->style('anchored');

    return back();
});
Route::middleware($web)->post('/both', function () {
    Toast::info('Import finished', '42 rows', ['label' => 'View rows', 'href' => '/rows']);
    PackageToast::warning('Quota at 90%')->flash();
    Toast::warning('Reconnect the bank', null, ['label' => 'Undo', 'href' => '/undo', 'method' => 'post']);

    return back();
});
Route::middleware($web)->post('/unanchor', function () {
    Toast::success('Moved')->from('move')->style('anchored')->style('pulse');

    return back();
});
Route::middleware($web)->get('/page', function () {
    Toast::success('Welcome back');

    return Inertia::render('Dashboard');
});
Route::middleware($web)->get('/plain', function () {
    Toast::success('Left for later');

    return response('ok');
});
Route::middleware($web)->get('/quiet', fn () => back());
$app['router']->getRoutes()->refreshNameLookups();

/** @return TestResponse<Symfony\Component\HttpFoundation\Response> */
$send = function (string $method, string $uri, array $server = []) use ($app): TestResponse {
    $request = Request::create('https://kit.test'.$uri, $method, [], [], [], [...$server, 'HTTP_REFERER' => 'https://kit.test/from']);
    $request->setLaravelSession($app['session']->driver());
    $app->instance('request', $request);
    $app['session']->driver()->flush();

    return TestResponse::fromBaseResponse($app['router']->dispatch($request), $request);
};
$inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml'];

try {
    foreach (['kit', 'inertiaui'] as $driver) {
        $config->set('kit.toasts.driver', $driver);

        $save = $send('POST', '/save');
        $save->assertToast('Changes saved', 'success')->assertToast(fn (array $toast): bool => $toast['data']['kit'] === ['from' => 'save', 'style' => 'fly-out']);
        deliveryCheck(count(ToastAssertions::from($save)) === 1, "{$driver}: a toast re-flashed by ->from() and ->style() is one toast, with its trigger and style");
        deliveryCheck(fails(fn () => $save->assertToast('Changes saved', 'error')) && fails(fn () => $save->assertToast('Saved')) && fails(fn () => $save->assertNoToasts()), "{$driver}: assertToast fails on another type or title, and assertNoToasts fails on a delivered toast");

        $role = $send('POST', '/role');
        $role->assertToast(fn (array $toast): bool => $toast['title'] === 'Role changed' && $toast['data']['kit']['style'] === 'anchored');
        $flashed = Inertia::getFlashed($role->baseRequest);
        deliveryCheck(($flashed['toast']['style'] ?? null) === 'anchored' && ($flashed['inertiaui']['toasts'] ?? []) === [], "{$driver}: an anchored toast stays on the kit's own key, never a corner toast in the package's list");

        $page = $send('GET', '/page', $inertia);
        $page->assertToast('Welcome back', 'success');
        deliveryCheck(true, "{$driver}: an Inertia page delivers the toast in its flash data");
        $send('GET', '/plain')->assertNoToasts();
        $send('GET', '/quiet')->assertNoToasts();
        deliveryCheck(true, "{$driver}: a plain response leaves a flashed toast for a later page, and a redirect without one delivers none");
    }

    $config->set('kit.toasts.driver', 'inertiaui');
    $both = $send('POST', '/both');
    $list = FlashedToasts::get($both->baseRequest);
    deliveryCheck(array_column($list, 'title') === ['Import finished', 'Quota at 90%', 'Reconnect the bank'], 'inertiaui: kit toasts and the package\'s own share one list, in order (the kit no longer keeps only the last)');
    $kit = $list[0];
    deliveryCheck($kit['uuid'] === $kit['id'] && $kit['type'] === 'info' && $kit['description'] === '42 rows' && $kit['actions'] === [['label' => 'View rows', 'href' => '/rows']] && $kit['duration'] === Toast::ACTION_DURATION && $kit['data'] === ['kit' => ['style' => 'plain']], 'inertiaui: a kit toast becomes the package\'s shape: id as uuid, a GET action without a method, the kit\'s duration');
    deliveryCheck($list[2]['actions'] === [['label' => 'Undo', 'href' => '/undo', 'method' => 'post']] && $list[2]['duration'] === null, 'inertiaui: a POST action keeps its method, and a warning stays until dismissed (null duration)');
    $both->assertToast('Quota at 90%', 'warning')->assertToast('Import finished', 'info');
    deliveryCheck(count(FlashedToasts::get($send('POST', '/save')->baseRequest)) === 1, 'inertiaui: a re-flash (->from(), ->style()) replaces its own entry in the package\'s list, never appends');

    $role = $send('POST', '/role');
    deliveryCheck((Inertia::getFlashed($role->baseRequest)['inertiaui']['toasts'] ?? []) === [] && Inertia::getFlashed($role->baseRequest)['toast']['from'] === 'change-role', 'inertiaui: a toast turned anchored by ->style() leaves the package\'s list');
    $back = Inertia::getFlashed($send('POST', '/unanchor')->baseRequest);
    deliveryCheck(! isset($back['toast']) && array_column($back['inertiaui']['toasts'], 'title') === ['Moved'], 'inertiaui: a toast turned back from anchored leaves the kit\'s key for the package\'s list');
    $config->set('kit.toasts.driver', 'carrier-pigeon');
    deliveryCheck((function (): bool {
        try {
            Toast::success('Saved');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    })(), 'an unknown kit.toasts.driver is refused');
    $config->set('kit.toasts.driver', 'inertiaui');
    deliveryCheck(Toast::shared() === ['position' => 'auto', 'driver' => 'inertiaui'], 'kitToasts shares the driver beside the position');
    $config->set('kit.toasts.driver', 'kit');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
