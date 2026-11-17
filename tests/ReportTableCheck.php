<?php

// ReportTable (LineItems' report props from computed rows) and Action's `download` intent: the declared columns, rows kept to the declared keys and their currency
// fields, totals, the refusals, and `download` serialised on the descriptor (and kept out of SeeAll). No database, no HTTP kernel.
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\SeeAll;
use Tey\Kit\Features\Core\Tables\ReportTable;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
// laravel-data reads its config when an Action serialises.
$app = new Illuminate\Container\Container;
Illuminate\Container\Container::setInstance($app);
$app->instance('app', $app);
$app->instance('config', new Illuminate\Config\Repository(['data' => [
    'date_format' => DATE_ATOM, 'date_timezone' => null, 'max_transformation_depth' => null, 'throw_when_max_transformation_depth_reached' => true, 'ignore_invalid_partials' => false, 'wrap' => null, 'var_dumper_caster_mode' => 'disabled',
    'features' => ['cast_and_transform_iterables' => false, 'ignore_exception_when_trying_to_set_computed_property_value' => false],
    'name_mapping_strategy' => ['input' => null, 'output' => null], 'structure_caching' => ['enabled' => false, 'cache' => ['store' => null, 'prefix' => 'laravel-data', 'duration' => null]],
    'normalizers' => [Spatie\LaravelData\Normalizers\ArrayableNormalizer::class, Spatie\LaravelData\Normalizers\ObjectNormalizer::class, Spatie\LaravelData\Normalizers\ArrayNormalizer::class],
]]));

function reportCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function reportRefuses(callable $build, string $needle, string $message): void
{
    try {
        $build();
    } catch (InvalidArgumentException $exception) {
        reportCheck(str_contains($exception->getMessage(), $needle), $message);

        return;
    }
    throw new RuntimeException('not refused: '.$message);
}

$rows = [
    ['room' => 'Harbour', 'nights' => 12, 'revenue' => '1840.50', 'currency' => 'CAD', 'occupancy' => 81.25, 'internal_note' => 'synthetic'],
    (object) ['room' => 'Garden', 'nights' => 3, 'revenue' => null, 'currency' => 'USD', 'occupancy' => null],
];
$report = ReportTable::make()
    ->column('room', 'Room')
    ->column('nights', 'Nights', kind: 'number', sortable: true)
    ->column('revenue', 'Revenue', kind: 'money', currency: 'CAD', currencyKey: 'currency', sortable: true)
    ->column('occupancy', 'Occupancy', kind: 'percent')
    ->rowHeader('room')
    ->caption('Revenue by room')
    ->empty('No stays in this period.')
    ->rows($rows)
    ->totals([['label' => 'Nights', 'value' => 15], ['label' => 'Total', 'value' => '1840.50', 'emphasis' => true]])
    ->toArray();

reportCheck($report['columns'] === [
    ['key' => 'room', 'label' => 'Room', 'kind' => 'text'],
    ['key' => 'nights', 'label' => 'Nights', 'kind' => 'number', 'sortable' => true],
    ['key' => 'revenue', 'label' => 'Revenue', 'kind' => 'money', 'currency' => 'CAD', 'currencyKey' => 'currency', 'sortable' => true],
    ['key' => 'occupancy', 'label' => 'Occupancy', 'kind' => 'percent'],
], 'columns serialise as LineItems columns, leaving unset options out');
reportCheck($report['rows'] === [
    ['room' => 'Harbour', 'nights' => 12, 'revenue' => '1840.50', 'currency' => 'CAD', 'occupancy' => 81.25],
    ['room' => 'Garden', 'nights' => 3, 'revenue' => null, 'currency' => 'USD', 'occupancy' => null],
], 'rows keep only the declared columns and the currency field (an undeclared field never reaches the page); an object row is read too');
reportCheck($report['totals'] === [['label' => 'Nights', 'value' => 15], ['label' => 'Total', 'value' => '1840.50', 'emphasis' => true]], 'totals serialise with emphasis only where set');
reportCheck($report['caption'] === 'Revenue by room' && $report['empty'] === 'No stays in this period.' && $report['rowHeader'] === 'room', 'caption, empty and rowHeader are passed through for v-bind');
reportCheck(json_encode(ReportTable::make()->column('a', 'A')) === '{"columns":[{"key":"a","label":"A","kind":"text"}],"rows":[],"totals":[]}', 'it serialises as JSON (an Inertia prop) without the unset caption, empty and rowHeader');

reportRefuses(fn () => ReportTable::make()->column('a', 'A')->column('a', 'B'), 'declared twice', 'a duplicate column key is refused');
reportRefuses(fn () => ReportTable::make()->column('a', 'A', kind: 'currency'), 'unknown kind', 'an unknown kind is refused');
reportRefuses(fn () => ReportTable::make()->column('a', 'A', align: 'left'), 'unknown align', 'an unknown align is refused');
reportRefuses(fn () => ReportTable::make()->column('a', 'A', kind: 'number', currencyKey: 'c'), 'not money', 'a currency on a non-money column is refused');
reportRefuses(fn () => ReportTable::make()->column('a', 'A')->rowHeader('b'), 'not a declared column', 'a rowHeader that is not a column is refused');

$export = (new Action('Export CSV', '/reports/rooms.csv', 'get'))->download();
reportCheck($export->toArray()['download'] === true && $export->toArray()['newTab'] === null, 'Action->download() serialises download: true and no new tab');
reportCheck((new Action('Export', '/x', 'get'))->toArray()['download'] === null, 'an ordinary action serialises download: null');
reportCheck((new Action('Export', '/x', 'get', download: true))->toArray()['download'] === true, 'download is a named constructor field too');
reportCheck($export->download(false)->toArray()['download'] === null && $export->toArray()['download'] === true, 'download(false) clears it on a copy, leaving the original');
reportCheck(SeeAll::fromAction($export) === null && SeeAll::fromAction(new Action('View all cases', '/x', 'get')) !== null && SeeAll::fromAction((new Action('View all cases', '/x', 'get'))->download()) === null, 'a download stays an action, never folded into a see-all link');
