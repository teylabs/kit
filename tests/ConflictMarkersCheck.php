<?php

// bin/conflict-markers-check.php on synthetic trees (it flags indented markers and a separator only inside a region, and passes a markdown rule), on this
// repository, and history.json still knowing the v0.6.10–v0.6.12 Page.vue that shipped with markers, so kit:update replaces it as a prior version.

function markersCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$script = dirname(__DIR__).'/bin/conflict-markers-check.php';
$run = function (string ...$roots) use ($script): array {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $roots)).' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};
$tree = function (array $files): string {
    $root = sys_get_temp_dir().'/kit-markers-'.bin2hex(random_bytes(6));
    foreach ($files as $path => $content) {
        @mkdir(dirname($root.'/'.$path), 0777, true);
        file_put_contents($root.'/'.$path, $content);
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));

    return $root;
};

[$code, $out] = $run($tree(['a/Page.vue.stub' => "<template>\n    <<<<<<< HEAD\n    <h1>a</h1>\n    =======\n    <h1>b</h1>\n    >>>>>>> origin/main\n</template>\n"]));
markersCheck($code === 1 && str_contains($out, 'Page.vue.stub:2') && str_contains($out, 'Page.vue.stub:4') && str_contains($out, 'Page.vue.stub:6'), 'indented markers and the separator inside them are flagged, with file:line');
[$code, $out] = $run($tree(['x.php' => "<?php\n<<<<<<< HEAD\n\$a = 1;\n"]));
markersCheck($code === 1 && str_contains($out, 'x.php:2'), 'a lone opening marker is flagged');
[$code] = $run($tree(['doc.md' => "Title\n=======\n\nText\n", 'ok.php' => "<?php\n// <<<<<<<< not a marker (eight)\n\$s = '<<<<<<< inside a string';\n"]));
markersCheck($code === 0, 'a markdown rule outside a region, eight chevrons and a mid-line string pass');
[$code, $out] = $run();
markersCheck($code === 0, 'resources and src carry no conflict markers ('.trim($out).')');

$history = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/features/history.json'), true, 512, JSON_THROW_ON_ERROR)['releases'];
$shipped = 'dcb473541a319392cfe82d7b8b1e6b7fa2b508c2c25b1a2e67a6e6d50dfa664a';
foreach (['v0.6.10', 'v0.6.11', 'v0.6.12'] as $tag) {
    markersCheck(str_contains(json_encode($history[$tag] ?? [], JSON_THROW_ON_ERROR), $shipped), "history.json knows {$tag}'s marker-laden Page.vue, so kit:update replaces it as a prior version");
}

echo "Conflict markers: synthetic trees and this repository. kit:update's replacement of the shipped Page.vue is covered by history.json, not run here.\n";
