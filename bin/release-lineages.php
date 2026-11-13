<?php

// Prints the release-notes line for the features a release changes: the features whose pin in src/Catalog.php differs between two refs. A stub fix
// never reaches the files an app already has (they are the app's own since 0.18); the line tells an app which features to review. Paste the output under
// the release's heading in CHANGELOG.md.
//   php bin/release-lineages.php [<from-ref> [<to-ref>]]    from: the newest vX.Y.Z tag before <to-ref> (not on it); to: HEAD
// Fails (exit 1) when either ref's src/Catalog.php cannot be read or names no pins.

/**
 * The feature pins a src/Catalog.php declares in its FEATURES constant: feature id => sha256 identity, in canonical order.
 *
 * @return array<string, string>
 */
function releaseLineagePins(string $source): array
{
    if (preg_match('/const FEATURES = \[(.*?)\];/s', $source, $block) !== 1) {
        throw new UnexpectedValueException('No FEATURES pins in src/Catalog.php.');
    }
    preg_match_all("/'([a-z0-9]+(?:-[a-z0-9]+)*)' => '(sha256:[0-9a-f]{64})'/", $block[1], $pins, PREG_SET_ORDER);
    if ($pins === []) {
        throw new UnexpectedValueException('No FEATURES pins in src/Catalog.php.');
    }

    return array_column($pins, 2, 1);
}

/**
 * The release-notes block for two sets of pins: the features whose pin changed (in $head's order), and any new ones.
 *
 * @param  array<string, string>  $base
 * @param  array<string, string>  $head
 */
function releaseLineagesBlock(array $base, array $head): string
{
    $changed = array_keys(array_filter($head, fn (string $pin, string $id): bool => isset($base[$id]) && $base[$id] !== $pin, ARRAY_FILTER_USE_BOTH));
    $added = array_keys(array_diff_key($head, $base));
    $lines = [];
    if ($changed !== []) {
        $lines[] = 'Features affected: '.implode(', ', $changed);
    }
    if ($added !== []) {
        $lines[] = 'New features: '.implode(', ', $added);
    }

    return $lines === [] ? 'Features affected: none.' : implode("\n", $lines);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$root = dirname(__DIR__);
$git = function (string $arguments) use ($root): string {
    exec('git -C '.escapeshellarg($root).' '.$arguments.' 2>/dev/null', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "git {$arguments} failed.\n");
        exit(1);
    }

    return implode("\n", $output);
};
$to = $argv[2] ?? 'HEAD';
// The newest release before <to-ref>: a tag on <to-ref> itself (the release being written up) is not its own base.
$from = $argv[1] ?? array_values(array_filter(explode("\n", $git('tag --list "v*" --sort=-v:refname --merged '.escapeshellarg($to).' --no-contains '.escapeshellarg($to))), fn (string $tag): bool => preg_match('/\Av\d+\.\d+\.\d+\z/', $tag) === 1))[0] ?? null;
if ($from === null) {
    fwrite(STDERR, "No vX.Y.Z tag before {$to}: name the base ref.\n");
    exit(1);
}
try {
    $block = releaseLineagesBlock(releaseLineagePins($git('show '.escapeshellarg($from.':src/Catalog.php'))), releaseLineagePins($git('show '.escapeshellarg($to.':src/Catalog.php'))));
} catch (UnexpectedValueException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
echo $block."\n";
