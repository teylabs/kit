<?php

// Kit's resources/adoption/surfaces.schema.json is the format-1 schema other packages copy and validate against, so it must accept everything the installed
// tey/standards accepts. This reads standards' own validator (Inventory::SIGNALS, its required signal fields, GROUPS and Facts::ANALYSES) and fails when a signal
// kind, a signal field, a required field, a surface group or a fact analysis differs from the schema, in either direction. Bumping tey/standards then fails here
// until the schema catches up. Static: no app, no network.
use Tey\Standards\Adoption\Facts;
use Tey\Standards\Adoption\Inventory;

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';

$schema = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/adoption/surfaces.schema.json'), true, flags: JSON_THROW_ON_ERROR);
$failures = 0;
$check = function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$label."\n";
    $failures += $ok ? 0 : 1;
};
$same = function (array $schemaSide, array $standardsSide): string {
    sort($schemaSide);
    sort($standardsSide);
    $missing = array_values(array_diff($standardsSide, $schemaSide));
    $extra = array_values(array_diff($schemaSide, $standardsSide));

    return ($missing ? ' schema lacks: '.implode(', ', $missing).'.' : '').($extra ? ' standards rejects: '.implode(', ', $extra).'.' : '');
};

$branches = [];
foreach ($schema['$defs']['signal']['oneOf'] as $branch) {
    $branches[$branch['properties']['kind']['const']] = $branch;
}
$signals = Inventory::SIGNALS;
$required = (new ReflectionClassConstant(Inventory::class, 'REQUIRED_SIGNAL_FIELDS'))->getValue();
$diff = $same(array_keys($branches), array_keys($signals));
$check($diff === '', 'the schema has a branch for every signal kind standards accepts, and no other ('.count($signals).' kinds).'.$diff);
foreach ($signals as $kind => $fields) {
    if (! isset($branches[$kind])) {
        continue;
    }
    $properties = array_values(array_diff(array_keys($branches[$kind]['properties']), ['kind']));
    $diff = $same($properties, array_keys($fields));
    $check($diff === '' && ($branches[$kind]['additionalProperties'] ?? true) === false, "{$kind}: the schema allows exactly the fields standards does, and nothing else.".$diff);
    $diff = $same(array_values(array_diff($branches[$kind]['required'] ?? [], ['kind'])), $required[$kind] ?? []);
    $check($diff === '', "{$kind}: the schema requires exactly the fields standards requires.".$diff);
}

$diff = $same($schema['$defs']['surface']['properties']['group']['enum'], Inventory::GROUPS);
$check($diff === '', 'surface groups match standards.'.$diff);
$diff = $same($schema['properties']['facts']['items']['properties']['computed']['oneOf'][0]['properties']['analysis']['enum'], Facts::ANALYSES);
$check($diff === '', 'fact analyses match standards.'.$diff);

exit($failures === 0 ? 0 : 1);
