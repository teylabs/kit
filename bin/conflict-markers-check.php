<?php

// Fails when a file under the given roots (default: resources and src) holds an unresolved merge conflict: a `<<<<<<< ` or `>>>>>>> ` line, or a `=======`
// line inside such a region. Markers may be indented, so leading whitespace is allowed. Kit v0.6.10–v0.6.12 shipped one in Kit/Layout/Page.vue.
// Usage: php bin/conflict-markers-check.php [root ...]; exit 1 with each file:line on a find.

$roots = array_slice($argv, 1) ?: [dirname(__DIR__).'/resources', dirname(__DIR__).'/src'];
$found = [];
foreach ($roots as $root) {
    if (! is_dir($root)) {
        fwrite(STDERR, "No such directory: {$root}\n");
        exit(2);
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $open = false;
        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            $line = rtrim($line, "\r\n");
            $start = preg_match('/^\s*<{7}(\s|$)/', $line) === 1;
            $end = preg_match('/^\s*>{7}(\s|$)/', $line) === 1;
            $middle = $open && preg_match('/^\s*={7}\s*$/', $line) === 1;
            if ($start || $end || $middle) {
                $found[] = $file->getPathname().':'.($number + 1).': '.trim($line);
            }
            $open = $start ? true : ($end ? false : $open);
        }
    }
}

if ($found !== []) {
    fwrite(STDERR, "Unresolved merge conflict markers:\n".implode("\n", $found)."\n");
    exit(1);
}
echo 'No conflict markers under '.implode(', ', array_map(fn (string $root): string => basename($root), $roots)).".\n";
