<?php

// The server side of ScrollList (ScrollListViewModel): the encrypted fail-closed cursor. A cursor round-trips and pages without gaps or repeats; one
// replayed across viewer, workspace, list or selection, a tampered one and a stray selection parameter are a 400; the wrong workspace is a 404; a failing
// query returns the scrubbed error state. SQLite memory with a synthetic table, no HTTP kernel.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use Inertia\ResponseFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tey\Kit\Features\Core\Lists\ListEmptyData;
use Tey\Kit\Features\Core\Lists\ListItemData;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function listCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class ListNote extends Model
{
    protected $table = 'notes';

    public $timestamps = false;

    protected $guarded = [];
}

class ListUser
{
    public function __construct(public int $id) {}

    public function getKey(): int
    {
        return $this->id;
    }
}

class NotesList extends ScrollListViewModel
{
    public static bool $broken = false;

    public array $reports = [];

    public function __construct(Request $request, int|string $workspace, public int|string|null $active, public string $listKey = 'notes') {
        parent::__construct($request, $workspace);
    }

    public function key(): string
    {
        return $this->listKey;
    }

    public function title(): string
    {
        return 'Notes';
    }

    protected function activeWorkspace(): int|string|null
    {
        return $this->active;
    }

    protected function perPage(): int
    {
        return 2;
    }

    protected function query(): Builder
    {
        return self::$broken ? ListNote::query()->whereRaw('missing_function(id) = 1')->orderBy('id') : ListNote::query()->orderBy('id');
    }

    protected function row(Model $model): ListItemData
    {
        return new ListItemData((string) $model->id, $model->title);
    }

    protected function empty(): ListEmptyData
    {
        return new ListEmptyData('first-use', 'No notes yet', 'Notes you add appear here.');
    }

    protected function report(Throwable $exception): void
    {
        $this->reports[] = $exception::class;
    }
}

$app = new Container;
Container::setInstance($app);
$app->instance('app', $app);
$app->instance('config', new Repository(['data' => [
    'date_format' => DATE_ATOM, 'date_timezone' => null, 'max_transformation_depth' => null, 'throw_when_max_transformation_depth_reached' => true, 'ignore_invalid_partials' => false, 'wrap' => null, 'var_dumper_caster_mode' => 'disabled',
    'features' => ['cast_and_transform_iterables' => false, 'ignore_exception_when_trying_to_set_computed_property_value' => false],
    'name_mapping_strategy' => ['input' => null, 'output' => null], 'structure_caching' => ['enabled' => false, 'cache' => ['store' => null, 'prefix' => 'laravel-data', 'duration' => null]],
    'normalizers' => [Spatie\LaravelData\Normalizers\ModelNormalizer::class, Spatie\LaravelData\Normalizers\FormRequestNormalizer::class, Spatie\LaravelData\Normalizers\ArrayableNormalizer::class, Spatie\LaravelData\Normalizers\ObjectNormalizer::class, Spatie\LaravelData\Normalizers\ArrayNormalizer::class, Spatie\LaravelData\Normalizers\JsonNormalizer::class],
]]));
$app->instance('encrypter', new Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
$app->instance(ResponseFactory::class, new ResponseFactory);
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::schema()->create('notes', function ($table): void {
    $table->id();
    $table->string('title');
});
foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $title) {
    ListNote::create(['title' => $title]);
}

$request = function (array $parameters = [], int $viewer = 1) use ($app): Request {
    $request = Request::create('/notes', 'GET', $parameters);
    $request->setUserResolver(fn () => new ListUser($viewer));
    $app->instance('request', $request);

    return $request;
};
/** @return array{0: array, 1: array} the window and its metadata */
$window = function (NotesList $list): array {
    $prop = $list->items();

    return [$prop(), $prop->metadata()];
};
$cursorOf = fn (array $metadata): ?string => $metadata['nextPage'];
$status = function (callable $call): int {
    try {
        $call();
    } catch (HttpException $exception) {
        return $exception->getStatusCode();
    }

    return 200;
};

// Paging: three windows (2, 2, 1), no gaps or repeats, the last has no continuation.
[$first, $meta] = $window(new NotesList($request(), 7, 7));
listCheck(array_column($first['data'], 'title') === ['One', 'Two'] && $first['state'] === 'ready' && $first['key'] === 'notes', 'the first window is the first page');
listCheck($meta['pageName'] === 'notes_cursor' && is_string($cursorOf($meta)), 'the continuation is named by the list key');
$cursor = $cursorOf($meta);
listCheck(! str_contains($cursor, 'Two') && ! str_contains(base64_decode($cursor, true) ?: '', 'fixed-v1'), 'the continuation is opaque');
[$second, $meta] = $window(new NotesList($request(['notes_cursor' => $cursor]), 7, 7));
listCheck(array_column($second['data'], 'title') === ['Three', 'Four'], 'the cursor round-trips to the next window');
[$third, $meta] = $window(new NotesList($request(['notes_cursor' => $cursorOf($meta)]), 7, 7));
listCheck(array_column($third['data'], 'title') === ['Five'] && $cursorOf($meta) === null, 'the last window has no continuation');

// Replay and tampering fail closed with a 400, never a first-page request.
$replay = fn (NotesList $list) => $status(fn () => $list->items()());
listCheck($replay(new NotesList($request(['notes_cursor' => $cursor], 2), 7, 7)) === 400, 'another viewer cannot replay a cursor');
listCheck($replay(new NotesList($request(['notes_cursor' => $cursor]), 8, 8)) === 400, 'another workspace cannot replay a cursor');
listCheck($replay(new NotesList($request(['others_cursor' => $cursor]), 7, 7, 'others')) === 400, 'another list cannot replay a cursor');
$selection = Crypt::encryptString(json_encode(['scope' => ['viewer' => 1, 'workspace' => 7, 'list' => 'notes', 'selection' => 'other', 'mode' => 'cursor'], 'position' => 'x'], JSON_THROW_ON_ERROR));
listCheck($replay(new NotesList($request(['notes_cursor' => $selection]), 7, 7)) === 400, 'another selection cannot replay a cursor');
listCheck($replay(new NotesList($request(['notes_cursor' => substr($cursor, 0, -4).'AAAA']), 7, 7)) === 400, 'a tampered cursor is refused');
listCheck($replay(new NotesList($request(['notes_cursor' => 'garbage']), 7, 7)) === 400, 'a malformed cursor is refused');
listCheck($replay(new NotesList($request(['notes' => 'x']), 7, 7)) === 400, 'a selection parameter is refused');

// Scope: the wrong or missing workspace and an anonymous request are a 404.
listCheck($replay(new NotesList($request(), 7, 8)) === 404, 'a list built for another workspace is a 404');
listCheck($replay(new NotesList($request(), 7, null)) === 404, 'no active workspace is a 404');
$anonymous = Request::create('/notes');
$app->instance('request', $anonymous);
listCheck($replay(new NotesList($anonymous, 7, 7)) === 404, 'an anonymous request is a 404');

// Empty: first use on an empty first window, no-results on a later one that emptied.
Capsule::table('notes')->delete();
[$empty] = $window(new NotesList($request(), 7, 7));
listCheck($empty['state'] === 'empty' && $empty['empty']['variant'] === 'first-use', 'an empty first window is first use');
ListNote::create(['title' => 'Back']);
$stale = Crypt::encryptString(json_encode(['scope' => ['viewer' => 1, 'workspace' => 7, 'list' => 'notes', 'selection' => 'fixed-v1', 'mode' => 'cursor'], 'position' => (new Illuminate\Pagination\Cursor(['id' => 99], true))->encode()], JSON_THROW_ON_ERROR));
[$gone] = $window(new NotesList($request(['notes_cursor' => $stale]), 7, 7));
listCheck($gone['state'] === 'empty' && $gone['empty']['variant'] === 'no-results', 'an emptied later window is no-results');

// Failure: the error state carries a fixed message, no exception text, and the failure is reported.
NotesList::$broken = true;
$list = new NotesList($request(), 7, 7);
[$failed] = $window($list);
listCheck($failed['state'] === 'error' && $failed['data'] === [] && $failed['error']['message'] === "This couldn't load." && $failed['error']['retry']['label'] === 'Retry', 'a failing query returns the error state with Retry');
listCheck(! str_contains(json_encode($failed), 'missing_function') && count($list->reports) === 1, 'the error state carries no exception text and the failure is reported');
echo "ScrollList checks only; no browser, Inertia protocol or client coverage.\n";
