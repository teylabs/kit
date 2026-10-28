<?php

// Fails when a composer `test:*` script is neither run by .github/workflows/tests.yml nor listed below with the reason it is not, so a new check cannot silently miss CI;
// and when a check file is run by no script: a tests/*Check.php that no script runs and no other check requires, or a tests/**/*.test.mjs that no script's
// `node --test` names or matches by glob. A merge that drops a file from a script's list fails here instead of quietly not running it.
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

$commands = implode("\n", array_map(fn ($command): string => implode(' ', (array) $command), json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['scripts'] ?? []));
// Each argument of a script's command: a path, or a glob such as tests/js/*.test.mjs (quotes stripped).
$patterns = array_map(fn (string $word): string => trim($word, '\'"'), preg_split('/\s+/', $commands) ?: []);
$covered = fn (string $file): bool => array_filter($patterns, fn (string $pattern): bool => $pattern === $file || (str_contains($pattern, '*') && fnmatch($pattern, $file, FNM_PATHNAME))) !== [];
$required = implode("\n", array_map(fn (string $check): string => (string) file_get_contents($check), glob($root.'/tests/*Check.php') ?: []));
$orphans = [];
foreach (glob($root.'/tests/*Check.php') ?: [] as $check) {
    $file = 'tests/'.basename($check);
    if (! $covered($file) && ! str_contains($required, "'/".basename($check)."'")) {
        $orphans[] = $file;
    }
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $test) {
    $file = substr($test->getPathname(), strlen($root) + 1);
    if (str_ends_with($file, '.test.mjs') && ! $covered($file)) {
        $orphans[] = $file;
    }
}
sort($orphans);
foreach ($orphans as $file) {
    fwrite(STDERR, "{$file} is run by no composer script: add it to one (or a glob), or require it from a check that is.\n");
}
if ($missing !== [] || $unknown !== [] || $orphans !== []) {
    exit(1);
}
echo count($scripts)." test scripts: ".(count($scripts) - count(NOT_IN_CI))." run in CI, ".count(NOT_IN_CI)." listed with a reason; every check file is run by one.\n";
