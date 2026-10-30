<?php

// The adoption inventory's expected outcomes: tey/standards' `standards adoption` (the supported CLI) reads each fixture app under tests/fixtures/adoption with
// this checkout's resources/adoption/surfaces.json, and every case in expected.json must get the row and the facts it names. A surfaces.json change that breaks
// an expected outcome fails here, in kit's own CI. A rule signal that can't run here (ui.kit-shell needs Pest, which kit doesn't install) is reported by standards as
// `skipped` and the row is decided by the surface's other signals; each case lists the skips it expects (signal index, kind and rule, with a reason), and a skip
// it doesn't list fails, so a rule can never drop out silently.
$root = dirname(__DIR__);
$bin = $root.'/vendor/bin/standards';
$inventory = $root.'/resources/adoption/surfaces.json';
$expected = json_decode((string) file_get_contents($root.'/tests/fixtures/adoption/expected.json'), true, flags: JSON_THROW_ON_ERROR);
$failures = 0;

if (! is_file($bin)) {
    fwrite(STDERR, "FAIL tey/standards is not installed (composer install): vendor/bin/standards is missing\n");
    exit(1);
}

foreach ($expected['cases'] as $case) {
    $base = $root.'/tests/fixtures/adoption/'.$case['app'];
    $output = [];
    exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, $bin, 'adoption', '--base', $base, '--inventory', $inventory, '--json'])).' 2>&1', $output, $code);
    $report = json_decode(implode("\n", $output), true);
    $label = "{$case['app']} / {$case['surface']}";
    if ($code !== 0 || ! is_array($report)) {
        echo "FAIL {$label}: standards adoption exited {$code}: ".implode("\n", array_slice($output, 0, 5))."\n";
        $failures++;

        continue;
    }
    $row = array_values(array_filter($report['rows'], fn (array $row): bool => $row['package'] === 'tey/kit' && $row['id'] === $case['surface']))[0] ?? null;
    if ($row === null) {
        echo "FAIL {$label}: no such row in the report\n";
        $failures++;

        continue;
    }
    $problems = [];
    if ($row['status'] !== $case['row']) {
        $problems[] = "row is {$row['status']}, expected {$case['row']}";
    }
    foreach ($case['facts'] as $fact => $value) {
        $actual = $report['facts']['tey/kit'][$fact]['value'] ?? null;
        if ($actual !== $value) {
            $problems[] = "fact {$fact} is ".var_export($actual, true).', expected '.var_export($value, true);
        }
    }
    $skipped = array_map(fn (array $skip): array => ['signal' => $skip['signal'] ?? null, 'kind' => $skip['kind'] ?? null, 'rule' => $skip['rule'] ?? null], $row['skipped'] ?? []);
    if (! array_key_exists('skipped', $row)) {
        $problems[] = 'the report row has no `skipped` field (tey/standards 0.11.48 or later reports it)';
    } elseif ($skipped !== ($case['skipped'] ?? [])) {
        $problems[] = 'skipped is '.json_encode($skipped).', expected '.json_encode($case['skipped'] ?? []);
    } elseif (array_filter($row['skipped'], fn (array $skip): bool => trim((string) ($skip['reason'] ?? '')) === '') !== []) {
        $problems[] = 'a skipped signal has no reason';
    }
    if ($problems === []) {
        echo "PASS {$label}: {$row['status']}".($skipped === [] ? '' : ' (skipped: '.implode(', ', array_map(fn (array $skip): string => "{$skip['kind']} {$skip['rule']}", $skipped)).')')."\n";
    } else {
        echo "FAIL {$label}: ".implode('; ', $problems)."\n";
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
