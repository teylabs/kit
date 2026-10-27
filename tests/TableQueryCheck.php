<?php

// The server side of the DataTable (TableQuery): sort, search, filter and page state from the query string; an unknown sort or filter is a 400; the per-page
// cap; tabs are not its concern; the empty state is first-use or no-results; a filter's declared options and its bulk actions, and TableSelection failing closed. SQLite memory with a synthetic table, no HTTP kernel.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Tables\InvalidFilterValue;
use Tey\Kit\Features\Core\Tables\TableQuery;
use Tey\Kit\Features\Core\Tables\TableSelection;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function tableCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class TablePerson extends Model
{
    protected $table = 'people';

    public $timestamps = false;

    protected $guarded = [];
}

$app = new Container;
Container::setInstance($app);
$app->instance('config', new Repository(['query-builder' => require dirname(__DIR__).'/vendor/spatie/laravel-query-builder/config/query-builder.php']));
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::schema()->create('people', function ($table): void {
    $table->id();
    $table->string('name');
    $table->string('role');
});
foreach ([['Ana', 'admin'], ['Ben', 'viewer'], ['Cleo', 'viewer'], ['Dara', 'viewer'], ['Eli', 'admin']] as [$name, $role]) {
    TablePerson::create(['name' => $name, 'role' => $role]);
}

$table = function (string $query) use ($app): Tey\Kit\Features\Core\Data\TableData {
    parse_str($query, $parameters);
    $request = Request::create('/people', 'GET', $parameters);
    $app->instance('request', $request);

    return TableQuery::for(TablePerson::query(), $request)
        ->column('name', 'Name', 'name')->column('role', 'Role')->column('actions', 'Actions', align: 'end', srOnlyLabel: true)
        ->search(fn ($q, $value) => $q->where('name', 'like', '%'.addcslashes($value, '%_\\').'%'))
        ->filter('role', fn ($q, $value) => $q->where('role', $value), 'Role', ['admin' => 'Admin', 'viewer' => 'Viewer'])
        ->filter('initial', fn ($q, $value) => $q->where('name', 'like', $value.'%'))
        ->bulk(new Action('Archive', '/people/archive', key: 'archive', destructive: true))
        ->defaultSort('name')
        ->build(fn (TablePerson $person) => ['id' => $person->id, 'name' => $person->name, 'role' => $person->role, 'actions' => []]);
};
$names = fn ($data) => array_column($data->rows, 'name');

tableCheck($names($table('')) === ['Ana', 'Ben', 'Cleo', 'Dara', 'Eli'] && $table('')->sort?->column === 'name' && $table('')->sort->direction === 'asc', 'the default sort is declared and reported');
tableCheck($names($table('sort=-name')) === ['Eli', 'Dara', 'Cleo', 'Ben', 'Ana'] && $table('sort=-name')->sort->direction === 'desc', 'a descending sort reorders the rows and is reported');
tableCheck($names($table('filter[search]=a')) === ['Ana', 'Dara'] && $table('filter[search]=a')->search === 'a', 'search narrows the rows and is echoed back');
tableCheck($names($table('filter[role]=admin')) === ['Ana', 'Eli'] && $table('filter[role]=admin')->filters === ['role' => 'admin'], 'a declared filter narrows the rows and is echoed back');
tableCheck($names($table('per_page=2&page=2')) === ['Cleo', 'Dara'] && $table('per_page=2&page=2')->pagination->lastPage === 3 && $table('per_page=2&page=2')->pagination->from === 3 && $table('per_page=2&page=2')->pagination->to === 4 && $table('per_page=2&page=2')->pagination->total === 5, 'a page carries its range and total');
tableCheck($table('per_page=9999')->pagination->perPage === 100 && $table('per_page=0')->pagination->perPage === 1, 'the per-page is capped between 1 and 100');
tableCheck($table('')->choices[0]->key === 'role' && $table('')->choices[0]->options === [['value' => 'admin', 'label' => 'Admin'], ['value' => 'viewer', 'label' => 'Viewer']] && count($table('')->choices) === 1, 'a labelled filter is a toolbar choice with its options; an unlabelled one is honoured but not shown');
tableCheck($names($table('filter[initial]=C')) === ['Cleo'], 'an unlabelled filter still narrows the rows');
tableCheck(count($table('')->bulkActions) === 1 && $table('')->bulkActions[0]->key === 'archive', 'bulk actions are carried to the client');
foreach ([['sort=role', InvalidSortQuery::class, 'a column that cannot be sorted'], ['filter[role]=owner', InvalidFilterValue::class, 'a value outside a filter\'s options'], ['sort=secret', InvalidSortQuery::class, 'an undeclared sort'], ['filter[secret]=1', InvalidFilterQuery::class, 'an undeclared filter']] as [$query, $class, $what]) {
    try {
        $table($query);
        throw new RuntimeException($what.' was not refused');
    } catch (Throwable $error) {
        tableCheck($error instanceof $class && $error->getStatusCode() === 400, $what.' is a 400, not ignored');
    }
}
tableCheck($table('filter[search]=zzz')->emptyState === 'no-results' && $table('filter[search]=zzz')->rows === [], 'filters that remove every row are no-results');
Capsule::table('people')->delete();
tableCheck($table('')->emptyState === 'first-use', 'an empty unfiltered table is first-use');
tableCheck(array_map(fn ($column) => [$column->key, $column->sortable], $table('')->columns) === [['name', true], ['role', false], ['actions', false]], 'the columns are declared in order with their sortability');
// The client stub's phone cards label every field after the first column (the card's title) by its visible position, not by :first-child (the label is the first child of every cell).
$stub = file_get_contents(dirname(__DIR__).'/resources/features/core/components/Kit/Table/DataTable.vue.stub');
tableCheck(str_contains($stub, 'v-for="(cell, index) in row.getVisibleCells()"') && str_contains($stub, ":class=\"index > 0 && !meta(cell.column).srOnlyLabel ? 'max-sm:inline' : ''\"") && ! str_contains($stub, 'not-first:inline'), 'phone card field labels show by visible column position');

foreach (['Ana', 'Ben', 'Cleo'] as $name) {
    TablePerson::create(['name' => $name, 'role' => 'viewer']);
}
$visible = fn () => TablePerson::query()->where('role', 'viewer');
$ids = TablePerson::query()->orderBy('id')->pluck('id')->all();
$select = fn (mixed $value) => TableSelection::from(Request::create('/people/archive', 'POST', ['ids' => $value]), $visible());
tableCheck($select([$ids[0], (string) $ids[1], $ids[1]])->pluck('name')->sort()->values()->all() === ['Ana', 'Ben'], 'a selection resolves its ids inside the scope, de-duplicated');
$scope = $visible();
TableSelection::from(Request::create('/x', 'POST', ['ids' => [$ids[0]]]), $scope);
tableCheck($scope->count() === 3, 'the scope the handler passes is not narrowed by the selection');
TablePerson::create(['name' => 'Hidden', 'role' => 'admin']);
$hidden = TablePerson::query()->where('name', 'Hidden')->value('id');
foreach ([[[$ids[0], $hidden], 'a selection with a row outside the scope'], [[], 'an empty selection'], [null, 'a missing selection'], [range(1, 101), 'a selection over the cap'], [[['x']], 'a malformed id'], [['a' => $ids[0]], 'ids that are not a list']] as [$value, $what]) {
    try {
        $select($value);
        throw new RuntimeException($what.' was not refused');
    } catch (HttpException $error) {
        tableCheck($error->getStatusCode() === 422, $what.' refuses the whole request (422)');
    }
}
echo "TableQuery checks on SQLite memory; the HTTP and People behaviour are the starter's tests.\n";
