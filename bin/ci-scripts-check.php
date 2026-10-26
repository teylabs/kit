<?php

// Fails when a composer `test:*` script is neither run by .github/workflows/tests.yml nor listed below with the reason it is not, so a new check cannot silently miss CI.
//   php bin/ci-scripts-check.php

// Scripts deliberately left out of CI: script => reason. Keep each reason specific.
const NOT_IN_CI = [];

$root = dirname(__DIR__);
$scripts = array_values(array_filter(array_keys(json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['scripts'] ?? []), fn (string $name): bool => str_starts_with($name, 'test:')));
preg_match_all('/composer (test:[a-z0-9-]+)\b/', (string) file_get_contents($root.'/.github/workflows/tests.yml'), $matches);
$ran = array_unique($matches[1]);

$missing = array_values(array_diff($scripts, $ran, array_keys(NOT_IN_CI)));
$unknown = array_values(array_diff(array_merge($ran, array_keys(NOT_IN_CI)), $scripts));
foreach ($missing as $name) {
    fwrite(STDERR, "{$name} is not run in tests.yml: add a step, or list it in NOT_IN_CI with the reason.\n");
}
foreach ($unknown as $name) {
    fwrite(STDERR, "{$name} is named in tests.yml or NOT_IN_CI but is not a composer script.\n");
}
if ($missing !== [] || $unknown !== []) {
    exit(1);
}
echo count($scripts)." test scripts: ".(count($scripts) - count(NOT_IN_CI))." run in CI, ".count(NOT_IN_CI)." listed with a reason.\n";
