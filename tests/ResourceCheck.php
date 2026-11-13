<?php

// Tamper only exclusive disposable copies, never immutable package resources. Each copy is loaded in a child run of this check (autoload, copy, release).
use Tey\Kit\Blueprint;

function copyPackage(string $source, string $destination): void
{
    mkdir($destination, 0700);
    // The check reads the package's own src and resources: dependencies and build tooling (vendor, node_modules, the browser and type-check workspaces) are not copied.
    $skip = fn (SplFileInfo $entry): bool => ! ($entry->isDir() && in_array($entry->getFilename(), ['vendor', 'node_modules', 'browser', 'typecheck', '.git'], true) && $entry->getPath() === $source);
    foreach (new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), fn (SplFileInfo $entry) => $skip($entry)), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Linked package fixture refused.');
        }
        $path = $destination.substr($entry->getPathname(), strlen($source));
        if ($entry->isDir()) {
            mkdir($path, 0700);
        } else {
            $stream = fopen($path, 'xb');
            fwrite($stream, file_get_contents($entry->getPathname()));
            fclose($stream);
        }
    }
}
try {
    if (isset($argv[2])) {
        $loader = require $argv[1];
        $loader->addPsr4('Tey\\Kit\\', $argv[2].'/src', true);
        if ((new ReflectionClass(Blueprint::class))->getFileName() !== $argv[2].'/src/Blueprint.php') {
            throw new RuntimeException('Blueprint did not load from the disposable copy.');
        }
        try {
            Blueprint::release($argv[3]);
        } catch (RuntimeException $error) {
            if (str_contains($error->getMessage(), 'Immutable blueprint')) {
                exit(0);
            }
            throw $error;
        }
        throw new RuntimeException('Expected refusal: tampered immutable resource or manifest refuses before rendering');
    }
    $cases = [];
    foreach (['A', 'B', 'C', 'D', 'E'] as $release) {
        foreach (glob(dirname(__DIR__).'/resources/releases/'.$release.'/*') as $resource) {
            $cases[] = [$release, basename($resource)];
        }
    }
    foreach ($cases as [$release, $resource]) {
        $copy = sys_get_temp_dir().'/blueprint-resource-copy-'.bin2hex(random_bytes(8));
        copyPackage(dirname(__DIR__), $copy);
        $path = $copy.'/resources/releases/'.$release.'/'.$resource;
        $bytes = file_get_contents($path);
        file_put_contents($path, $resource === 'blueprint.json' ? str_replace('private-workspace', 'changed-workspace', $bytes) : $bytes.'// changed');
        $process = proc_open([PHP_BINARY, __FILE__, $argv[1], $copy, $release], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($output.$error);
        }
        echo 'PASS disposable '.$release.' '.$resource.' tamper refused'."\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
