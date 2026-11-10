<?php

// Compares the field names each kit form submits (tests/fixtures/form-payloads/<form>.json, written by the browser Save tests) at a base ref with the
// working tree. A removed or renamed field, or a removed fixture, changes what a kit form submits: that is a breaking change, released as a minor with
// an upgrade note. Added fields are reported but pass, since the server drops undeclared fields. Values are ignored; only field names count.
//   php bin/form-payload-check.php <base-ref> [--allow]
// `--allow`, or a line "Form payload: breaking" under "## Unreleased" in CHANGELOG.md, reports the break and passes (for a minor release).

const FORM_PAYLOAD_DIRECTORY = 'tests/fixtures/form-payloads';
const FORM_PAYLOAD_MARKER = 'Form payload: breaking';

/**
 * The field names a fixture submits: an object's keys, or the names of a FormData entry list ([[name, value], ...]). Sorted and unique.
 *
 * @return list<string>
 */
function formPayloadFields(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($payload)) {
        return [];
    }
    $names = array_is_list($payload) && $payload !== [] && array_filter($payload, fn ($entry): bool => ! (is_array($entry) && array_is_list($entry) && is_string($entry[0] ?? null))) === []
        ? array_map(fn (array $entry): string => $entry[0], $payload)
        : array_map('strval', array_keys($payload));
    $names = array_values(array_unique($names));
    sort($names);

    return $names;
}

/**
 * Compares two sets of fixtures (form => field names). A form that lost fields and gained others is reported as renamed, pairing them in order.
 *
 * @param  array<string, list<string>>  $base
 * @param  array<string, list<string>>  $head
 * @return array{lines: list<string>, breaking: bool}
 */
function formPayloadCompare(array $base, array $head): array
{
    $lines = [];
    $breaking = false;
    $forms = array_unique(array_merge(array_keys($base), array_keys($head)));
    sort($forms);
    foreach ($forms as $form) {
        if (! array_key_exists($form, $head)) {
            $lines[] = "{$form}: fixture removed (breaking)";
            $breaking = true;

            continue;
        }
        if (! array_key_exists($form, $base)) {
            $lines[] = "{$form}: new form (".implode(', ', $head[$form]).')';

            continue;
        }
        $removed = array_values(array_diff($base[$form], $head[$form]));
        $added = array_values(array_diff($head[$form], $base[$form]));
        $renamed = min(count($removed), count($added));
        for ($i = 0; $i < $renamed; $i++) {
            $lines[] = "{$form}: renamed {$removed[$i]} -> {$added[$i]} (breaking)";
        }
        foreach (array_slice($removed, $renamed) as $field) {
            $lines[] = "{$form}: removed {$field} (breaking)";
        }
        foreach (array_slice($added, $renamed) as $field) {
            $lines[] = "{$form}: added {$field} (not breaking: the server drops undeclared fields)";
        }
        $breaking = $breaking || $removed !== [];
    }

    return ['lines' => $lines, 'breaking' => $breaking];
}

/** Whether CHANGELOG.md's "## Unreleased" section carries the "Form payload: breaking" marker. */
function formPayloadMarked(string $changelog): bool
{
    if (preg_match('/^## Unreleased\s*$(.*?)(?=^## |\z)/ms', $changelog, $match) !== 1) {
        return false;
    }

    return str_contains($match[1], FORM_PAYLOAD_MARKER);
}

// Required by tests/FormPayloadCheck.php for the functions alone.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$root = getcwd() ?: dirname(__DIR__);
$arguments = array_slice($argv, 1);
$allow = in_array('--allow', $arguments, true);
$ref = array_values(array_filter($arguments, fn (string $argument): bool => $argument !== '--allow'))[0] ?? null;
if ($ref === null) {
    fwrite(STDERR, "Usage: php bin/form-payload-check.php <base-ref> [--allow]\n");
    exit(2);
}

$git = function (string $command, ?int &$code = null) use ($root): string {
    exec('git -C '.escapeshellarg($root).' '.$command.' 2>/dev/null', $output, $code);

    return implode("\n", $output);
};
$git('rev-parse --verify --quiet '.escapeshellarg($ref.'^{commit}'), $code);
if ($code !== 0) {
    fwrite(STDERR, "Unknown ref {$ref}: fetch it first (CI needs the base branch's history, not a shallow clone).\n");
    exit(2);
}

$base = [];
foreach (array_filter(explode("\n", $git('ls-tree --name-only '.escapeshellarg($ref).' '.FORM_PAYLOAD_DIRECTORY.'/'))) as $path) {
    if (str_ends_with($path, '.json')) {
        $base[basename($path, '.json')] = formPayloadFields($git('show '.escapeshellarg($ref.':'.$path)));
    }
}
$head = [];
foreach (glob($root.'/'.FORM_PAYLOAD_DIRECTORY.'/*.json') ?: [] as $file) {
    $head[basename($file, '.json')] = formPayloadFields((string) file_get_contents($file));
}

$result = formPayloadCompare($base, $head);
foreach ($result['lines'] as $line) {
    echo $line."\n";
}
if (! $result['breaking']) {
    echo 'Form payloads: no field removed or renamed since '.$ref.' ('.count($head)." forms).\n";
    exit(0);
}
$marked = formPayloadMarked((string) @file_get_contents($root.'/CHANGELOG.md'));
if ($allow || $marked) {
    echo 'Form payloads: breaking since '.$ref.', allowed by '.($allow ? '--allow' : '"'.FORM_PAYLOAD_MARKER.'" under ## Unreleased').". Release it as a minor with an upgrade note.\n";
    exit(0);
}
fwrite(STDERR, "\nThis changes what a kit form submits, which breaks apps that read those fields. Release it as a minor (not a patch) with an upgrade note,\n"
    .'and add a line "'.FORM_PAYLOAD_MARKER."\" under ## Unreleased in CHANGELOG.md (or run with --allow). See docs/releasing.md.\n");
exit(1);
