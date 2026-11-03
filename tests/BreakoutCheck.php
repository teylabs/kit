<?php

// A breakout page (a full-screen page that leaves its parent's layout) gets "Back to <parent>", derived from its breadcrumb parent, in place of the trail: through
// its route's `kit.breakout` default or its ViewModel's breaksOut(). A page's own navigationHeader() still wins. Two mistakes, a breakout with no parent crumb
// and a breakout inside a settings section, throw in local and testing, and draw no strip in production. Real gretel routes dispatched through the router;
// the page shell is a stub that records the strip it is given. No database, no browser (the trail giving way to the strip is in browser/run.mjs).
use Glhd\Gretel\Support\GretelServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Data\PageLayout;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

$loader = require $argv[1];
$root = dirname(__DIR__);
$loader->addPsr4('Tey\\Kit\\', $root.'/src', true);

function breakoutCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

final class BreakoutBench extends PageViewModel
{
    public function __construct(private readonly ?bool $breaks = null, private readonly ?string $inSection = null, private readonly ?NavigationHeader $own = null) {}

    public function title(): string
    {
        return 'Bench';
    }

    protected function breaksOut(): bool
    {
        return $this->breaks ?? parent::breaksOut();
    }

    protected function section(): ?string
    {
        return $this->inSection;
    }

    protected function navigationHeader(): ?NavigationHeader
    {
        return $this->own;
    }
}

try {
    $base = sys_get_temp_dir().'/kit-breakout-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'env' => 'testing', 'debug' => false],
        'kit' => ['features' => ['core']],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, RoutingServiceProvider::class, LaravelDataServiceProvider::class, GretelServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->instance('request', Request::create('https://kit.test'));
    // The page shell: records the strip each page hands it.
    $app->instance(PageLayouts::class, new class implements PageLayouts
    {
        public function layout(string $title, ?string $section, ?NavigationHeader $navigationHeader = null): PageLayout
        {
            return new PageLayout([], $title, $title, [], $navigationHeader);
        }
    });
    $app->boot();

    $page = null;
    Route::get('/assignments', fn () => 'list')->name('assignments.index')->breadcrumb('Assignments');
    Route::get('/assignments/{id}', fn () => 'case')->name('assignments.show')->breadcrumb('A-0001', 'assignments.index');
    // The bench breaks out through its route; the details tab is inside the case's tabs and does not.
    Route::get('/assignments/{id}/bench', function () use (&$page) { return $page(); })->name('assignments.bench')->breadcrumb('Valuation', 'assignments.show')->defaults('kit.breakout', true);
    Route::get('/assignments/{id}/details', function () use (&$page) { return $page(); })->name('assignments.details')->breadcrumb('Details', 'assignments.show');
    Route::get('/orphan', function () use (&$page) { return $page(); })->name('orphan')->breadcrumb('Orphan')->defaults('kit.breakout', true);
    $app['router']->getRoutes()->refreshNameLookups();
    $strip = function (string $path, Closure $make) use ($app, &$page): mixed {
        $page = fn () => new Illuminate\Http\JsonResponse(['header' => $make()->layout()->navigationHeader?->toArray()]);
        $request = Request::create('https://kit.test'.$path);
        $app->instance('request', $request);
        $response = $app['router']->dispatch($request);
        if ($response->exception ?? null) {
            throw $response->exception;
        }

        return json_decode((string) $response->getContent(), true)['header'];
    };

    $header = $strip('/assignments/1/bench', fn () => new BreakoutBench);
    breakoutCheck($header === ['label' => 'Back to A-0001', 'url' => 'https://kit.test/assignments/1'], 'a route with kit.breakout gets "Back to <parent crumb>", going to the parent (got '.json_encode($header).')');
    breakoutCheck($strip('/assignments/1/details', fn () => new BreakoutBench) === null, 'a page inside its parent\'s layout (a tab) gets no strip: it keeps the trail');
    breakoutCheck($strip('/assignments/1/details', fn () => new BreakoutBench(breaks: true)) === ['label' => 'Back to A-0001', 'url' => 'https://kit.test/assignments/1'], 'a ViewModel\'s breaksOut() makes a page a breakout without the route default');
    breakoutCheck($strip('/assignments/1/bench', fn () => new BreakoutBench(breaks: false)) === null, 'a ViewModel\'s breaksOut() false overrides the route default');
    breakoutCheck($strip('/assignments/1/bench', fn () => new BreakoutBench(own: new NavigationHeader('Back to the board', '/board'))) === ['label' => 'Back to the board', 'url' => '/board'], 'a page\'s own navigationHeader() wins over the derived one');

    $refused = function (Closure $call): ?string {
        try {
            $call();
        } catch (LogicException $error) {
            return $error->getMessage();
        }

        return null;
    };
    $noParent = $refused(fn () => $strip('/orphan', fn () => new BreakoutBench));
    breakoutCheck($noParent !== null && str_contains($noParent, 'needs a breadcrumb parent') && str_contains($noParent, 'orphan'), 'in testing, a breakout with no parent crumb throws and names the route');
    $inSection = $refused(fn () => $strip('/assignments/1/bench', fn () => new BreakoutBench(inSection: 'workspace')));
    breakoutCheck($inSection !== null && str_contains($inSection, 'no settings section (workspace)'), 'in testing, a breakout inside a settings section throws');

    $app->instance('env', 'production');
    breakoutCheck($strip('/orphan', fn () => new BreakoutBench) === null, 'in production, a breakout with no parent crumb draws no strip (the trail stays) instead of failing the page');
    breakoutCheck($strip('/assignments/1/bench', fn () => new BreakoutBench(inSection: 'workspace')) === null, 'in production, a breakout inside a settings section draws no strip instead of failing the page');

    echo "Breakout: real gretel routes through the router, a stub page shell; no database or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
