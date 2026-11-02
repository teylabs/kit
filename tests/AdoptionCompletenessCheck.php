<?php

// The adoption inventory can't go blind to a kit surface. Coverage: every page, layout and shell component a feature installs (a `.vue` file under
// resources/js/pages in some feature.json) is named by a surfaces.json row's uses.files, uses.components or uses.imports, or is listed in
// resources/adoption/internal.json with a reason. Proof: every row with handRolled signals has an adoption fixture case (tests/fixtures/adoption/expected.json,
// run by AdoptionFixturesCheck) expecting it `hand-rolled`, or an internal.json `unproven` entry with a reason. A stale internal.json entry fails too.
// A row with no `adopted` expectation is reported as a note, not a failure.
$root = dirname(__DIR__);
$read = fn (string $path): array => json_decode((string) file_get_contents($root.'/'.$path), true, flags: JSON_THROW_ON_ERROR);
$inventory = $read('resources/adoption/surfaces.json');
$internal = $read('resources/adoption/internal.json');
$expected = $read('tests/fixtures/adoption/expected.json');
$failures = [];

$installed = [];
foreach (glob($root.'/resources/features/*/feature.json') as $manifest) {
    foreach (array_keys(json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR)['files'] ?? []) as $path) {
        if (str_starts_with($path, 'resources/js/pages/') && str_ends_with($path, '.vue')) {
            $installed[$path] = basename(dirname($manifest));
        }
    }
}
ksort($installed);
if ($installed === []) {
    $failures[] = 'no feature installs a page under resources/js/pages: the feature manifests were not read';
}

$files = $components = $imports = [];
foreach ($inventory['surfaces'] as $row) {
    foreach ($row['uses']['files'] ?? [] as $file) {
        $files[$file] = true;
    }
    foreach ($row['uses']['components'] ?? [] as $component) {
        $components[$component] = true;
    }
    foreach ($row['uses']['imports'] ?? [] as $import) {
        [$module, $name] = array_pad(explode(':', $import, 2), 2, '');
        $imports[$name] = true;
        $imports[preg_replace('~^@/~', 'resources/js/', $module)] = true;
    }
}
// A component or import name only identifies a named partial: every page is Page.vue and every settings frame Layout.vue, so those need uses.files.
$named = function (string $path) use ($files, $components, $imports): bool {
    $name = basename($path, '.vue');
    $generic = in_array($name, ['Page', 'Layout'], true);

    return isset($files[$path]) || isset($imports[substr($path, 0, -4)]) || (! $generic && (isset($components[$name]) || isset($imports[$name])));
};

$allowed = [];
foreach ($internal['files'] ?? [] as $entry) {
    $path = (string) ($entry['path'] ?? '');
    if (trim((string) ($entry['reason'] ?? '')) === '') {
        $failures[] = "internal.json files: {$path} has no reason";
    }
    if (! isset($installed[$path])) {
        $failures[] = "internal.json files: {$path} is stale (no feature installs it); remove the entry";
    } elseif ($named($path)) {
        $failures[] = "internal.json files: {$path} is stale (a surfaces.json row names it now); remove the entry";
    }
    $allowed[$path] = true;
}
foreach ($installed as $path => $feature) {
    if (! $named($path) && ! isset($allowed[$path])) {
        $failures[] = "unmapped: {$path} (feature {$feature}) is installed but no surfaces.json row names it. Add it to a row's uses.files (or uses.components), or add an internal.json files entry with a one-line reason";
    }
}

$cases = ['hand-rolled' => [], 'adopted' => []];
foreach ($expected['cases'] as $case) {
    if (isset($cases[$case['row']])) {
        $cases[$case['row']][$case['surface']][] = $case['app'];
    }
}
$ids = array_column($inventory['surfaces'], 'id');
$unproven = [];
foreach ($internal['unproven'] ?? [] as $entry) {
    $id = (string) ($entry['surface'] ?? '');
    if (trim((string) ($entry['reason'] ?? '')) === '') {
        $failures[] = "internal.json unproven: {$id} has no reason";
    }
    $row = array_values(array_filter($inventory['surfaces'], fn (array $row): bool => $row['id'] === $id))[0] ?? null;
    if ($row === null || ($row['handRolled'] ?? []) === []) {
        $failures[] = "internal.json unproven: {$id} is stale (no such row with handRolled signals); remove the entry";
    } elseif (isset($cases['hand-rolled'][$id])) {
        $failures[] = "internal.json unproven: {$id} is stale (".implode(', ', $cases['hand-rolled'][$id]).' expects it hand-rolled now); remove the entry';
    }
    $unproven[$id] = true;
}
$notes = [];
foreach ($inventory['surfaces'] as $row) {
    if (($row['handRolled'] ?? []) !== [] && ! isset($cases['hand-rolled'][$row['id']]) && ! isset($unproven[$row['id']])) {
        $failures[] = "unproven: {$row['id']} has handRolled signals but no adoption fixture expects it hand-rolled. Add a minimal fixture under tests/fixtures/adoption that hand-rolls it (use the row's signals) with a case in expected.json, or an internal.json unproven entry with a reason";
    }
    if (! isset($cases['adopted'][$row['id']])) {
        $notes[] = $row['id'];
    }
}
foreach (array_keys($cases['hand-rolled'] + $cases['adopted']) as $id) {
    if (! in_array($id, $ids, true)) {
        $failures[] = "expected.json names surface {$id}, which surfaces.json has no row for";
    }
}

foreach ($failures as $failure) {
    echo "FAIL {$failure}\n";
}
if ($failures === []) {
    echo 'PASS '.count($installed).' installed pages, layouts and shell components are named by a row or internal.json ('.count($allowed).' internal); '
        .count(array_filter($inventory['surfaces'], fn (array $row): bool => ($row['handRolled'] ?? []) !== []))." rows with handRolled signals are proven by a fixture or internal.json\n";
}
if ($notes !== []) {
    echo 'NOTE no fixture expects these rows adopted: '.implode(', ', $notes)."\n";
}

exit($failures === [] ? 0 : 1);
