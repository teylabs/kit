<?php

// Writes resources/features/history.json: each tagged release's rendered file hashes per feature, so kit:adopt can
// tell which release a pre-lifecycle app's files came from. Run from a git checkout after composer install:
//   php bin/catalog-history.php
// Each release renders with its own Blueprint, from `git archive`, in a separate process. Hashes only, no bytes.
// The last entry, `current`, is this checkout's catalog; `--check` fails when it no longer matches (run before tagging).

$root = dirname(__DIR__);
if (($argv[1] ?? null) === '--render') {
    // Child: render one extracted release with its own sources.
    $loader = require $root.'/vendor/autoload.php';
    $loader->addPsr4('Tey\\Kit\\', $argv[2].'/src', true);
    $files = [];
    foreach (Tey\Kit\Catalog::package()->featureIds() as $id) {
        $files[$id] = array_map(fn (string $bytes): string => hash('sha256', $bytes), Tey\Kit\Blueprint::lineage($id)['files']);
        ksort($files[$id]);
    }
    echo json_encode($files, JSON_THROW_ON_ERROR);
    exit(0);
}
$render = function (string $directory): array {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render '.escapeshellarg($directory), $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Could not render {$directory}.\n");
        exit(1);
    }

    return json_decode(implode('', $output), true, 16, JSON_THROW_ON_ERROR);
};
$file = $root.'/resources/features/history.json';
if (($argv[1] ?? null) === '--check') {
    $saved = json_decode((string) @file_get_contents($file), true)['releases']['current'] ?? null;
    if ($saved !== $render($root)) {
        fwrite(STDERR, "resources/features/history.json is stale: run php bin/catalog-history.php and commit it.\n");
        exit(1);
    }
    echo "Catalog history is current.\n";
    exit(0);
}
$git = fn (string $command): string => (string) shell_exec('git -C '.escapeshellarg($root).' '.$command);
$tags = array_values(array_filter(explode("\n", trim($git('tag --list "v*" --sort=v:refname'))), fn (string $tag): bool => preg_match('/\Av\d+\.\d+\.\d+\z/', $tag) === 1));
$releases = [];
foreach ($tags as $tag) {
    $directory = sys_get_temp_dir().'/kit-history-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    exec('git -C '.escapeshellarg($root).' archive '.escapeshellarg($tag).' src resources | tar -x -C '.escapeshellarg($directory), result_code: $code);
    if ($code !== 0) {
        fwrite(STDERR, "Could not extract {$tag}.\n");
        exit(1);
    }
    $releases[$tag] = $render($directory);
    exec('rm -rf '.escapeshellarg($directory));
}
$releases['current'] = $render($root);
file_put_contents($file, json_encode(['format' => 1, 'releases' => $releases], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo 'Wrote '.(count($releases) - 1)." releases and current.\n";
