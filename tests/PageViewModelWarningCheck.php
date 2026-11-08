<?php

// A page rendered from a plain ViewModel gets no breadcrumb bar or module tabs (only PageViewModel has `layout()`), so in local and testing
// `Inertia::render()` with one logs a warning naming the class. A PageViewModel, its child and widget ViewModels, a ViewModel serialised for JSON
// or a modal, and a `$standalone` page stay silent; production stays silent and nothing throws. No database, no HTTP.
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Inertia\ResponseFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tey\Kit\Tests\Fixtures\PageWarning\ChildProps;
use Tey\Kit\Tests\Fixtures\PageWarning\PlainPage;
use Tey\Kit\Tests\Fixtures\PageWarning\PublicPage;
use Tey\Kit\Tests\Fixtures\PageWarning\TypedPage;
use Tey\Kit\Tests\Fixtures\PageWarning\WidgetCard;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
require __DIR__.'/fixtures/page-warning/Fixtures.php';

function pageWarningCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $app = new Application(sys_get_temp_dir());
    Facade::setFacadeApplication($app);
    $app->instance('config', new Repository(['inertia' => []]));
    $app->instance('env', 'testing');
    $handler = new TestHandler;
    $app->instance('log', new Logger('test', [$handler]));
    $app->instance('session', new Store('test', new ArraySessionHandler(1)));
    $warnings = fn (): array => array_values(array_filter(array_map(fn ($record): string => (string) $record['message'], $handler->getRecords()), fn (string $message): bool => str_contains($message, 'is not a PageViewModel')));
    $factory = new ResponseFactory;

    $factory->render('Plain/Page', new PlainPage);
    pageWarningCheck(count($warnings()) === 1 && str_starts_with($warnings()[0], PlainPage::class.' ') && str_contains($warnings()[0], 'extend Tey\\Kit\\Features\\Core\\ViewModels\\PageViewModel'), 'in testing, Inertia::render() with a plain ViewModel warns once, naming the class and PageViewModel');

    $handler->clear();
    $factory->render('Typed/Page', new TypedPage);
    pageWarningCheck($warnings() === [], 'a PageViewModel page and its widget and child ViewModels do not warn');

    (new WidgetCard)->toArray();
    (new ChildProps)->toArray();
    json_encode(new PlainPage);
    (new PlainPage)->toArray();
    pageWarningCheck($warnings() === [], 'a widget or child ViewModel serialised outside Inertia::render() (JSON, a modal\'s toArray()) does not warn');

    $factory->render('Public/Page', new PublicPage);
    pageWarningCheck($warnings() === [], 'a $standalone page (a public document) does not warn');

    $app->instance('env', 'local');
    $factory->render('Plain/Page', new PlainPage);
    pageWarningCheck(count($warnings()) === 1, 'in local the plain page warns too');

    $handler->clear();
    $app->instance('env', 'production');
    $factory->render('Plain/Page', new PlainPage);
    pageWarningCheck($warnings() === [] && $handler->getRecords() === [], 'in production the same render is silent and does not throw');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".(getenv('TRACE') ? $error->getTraceAsString() : ''));
    exit(1);
}
