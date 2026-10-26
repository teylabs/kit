<?php

// Prints each catalog feature's identity as its stubs and manifest stand now, next to the pin in src/Catalog.php, so a stub change can be re-pinned without guessing:
//   php bin/catalog-pins.php            every feature and the base, marked `ok` or `changed`
//   php bin/catalog-pins.php --check    exits 1 when any pin is stale
// The identity is Blueprint's: the manifest's hash and every stub's hash. Re-pin src/Catalog.php and tests/StarterCatalogCheck.php, then run php bin/catalog-history.php.

$root = dirname(__DIR__);
foreach (array_slice($argv, 1) as $argument) {
    if ($argument !== '--check') {
        fwrite(STDERR, "Usage: php bin/catalog-pins.php [--check]\n");
        exit(2);
    }
}
$loader = require $root.'/vendor/autoload.php';
$loader->addPsr4('Tey\\Kit\\', $root.'/src', true);

$catalog = Tey\Kit\Catalog::package();
$featureAt = new ReflectionMethod(Tey\Kit\Blueprint::class, 'featureAt');
$stubIn = new ReflectionMethod(Tey\Kit\Blueprint::class, 'stubIn');
$rows = [];
foreach ($catalog->featureIds() as $id) {
    $feature = $featureAt->invoke(null, $catalog, $id);
    $directory = $catalog->directory($id);
    $stubs = [];
    foreach ($feature['files'] as $entry) {
        $stubs[$entry['stub']] = hash('sha256', file_get_contents($stubIn->invoke(null, $directory, $entry['stub'])));
    }
    ksort($stubs);
    $rows[$id] = [$catalog->pins[$id], 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $directory.'/feature.json'), $stubs], JSON_THROW_ON_ERROR))];
}
$rows['(base)'] = [$catalog->basePin, 'sha256:'.hash('sha256', file_get_contents($catalog->root.'/blueprint.json'))];

$stale = 0;
foreach ($rows as $id => [$pinned, $current]) {
    $changed = $pinned !== $current;
    $stale += (int) $changed;
    printf("%-11s %s  %s%s\n", $id, $current, $changed ? 'changed' : 'ok', $changed ? ' (pinned '.$pinned.')' : '');
}
if (in_array('--check', $argv, true) && $stale > 0) {
    fwrite(STDERR, $stale." pin(s) are stale: update src/Catalog.php and tests/StarterCatalogCheck.php, then run php bin/catalog-history.php.\n");
    exit(1);
}
