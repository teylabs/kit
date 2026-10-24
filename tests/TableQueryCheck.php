<?php

// The server side of the DataTable (TableQuery): sort, search, filter and page state from the query string; an unknown sort or filter is a 400; the per-page
// cap; tabs are not its concern; the empty state is first-use or no-results. SQLite memory with a synthetic table, no HTTP kernel.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Tey\Kit\Features\Core\Tables\TableQuery;

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
        ->filter('role', fn ($q, $value) => $q->where('role', $value))
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
foreach ([['sort=role', InvalidSortQuery::class, 'a column that cannot be sorted'], ['sort=secret', InvalidSortQuery::class, 'an undeclared sort'], ['filter[secret]=1', InvalidFilterQuery::class, 'an undeclared filter']] as [$query, $class, $what]) {
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
echo "TableQuery checks on SQLite memory; the HTTP and People behaviour are the starter's tests.\n";
