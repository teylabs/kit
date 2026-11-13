<?php

// kit:scaffold on a synthetic app: writes once, refuses to overwrite, --force, --only, a read-only --diff, and no records.
$autoload = $argv[1] ?? null;
if (! is_string($autoload) || ! is_file($autoload)) {
    fwrite(STDERR, "Pass a Composer autoload.php.\n");
    exit(2);
}
$loader = require $autoload;
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src');
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\KitServiceProvider;
use Tey\Mod\ModServiceProvider;

function scaffoldCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** Every file under $root, relative path => sha256. */
function scaffoldTree(string $root): array
{
    $tree = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $tree[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
    }
    ksort($tree);

    return $tree;
}

function scaffoldApp(): array
{
    $root = sys_get_temp_dir().'/kit-scaffold-cmd-'.bin2hex(random_bytes(6));
    foreach (['bootstrap/cache', 'config', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/config/app.php', "<?php return ['name'=>'Synthetic consumer','env'=>'testing','key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','cipher'=>'AES-256-CBC'];");
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return [$root, function (array $parameters) use ($kernel): array {
        $exit = $kernel->call('kit:scaffold', $parameters + ['--no-interaction' => true]);

        return [$exit, $kernel->output()];
    }];
}

try {
    $stubs = dirname(__DIR__).'/resources/features/notes';
    $notes = array_map(fn (array $entry): string => (string) file_get_contents($stubs.'/'.$entry['stub']), json_decode((string) file_get_contents($stubs.'/feature.json'), true)['files']);
    ksort($notes);
    $page = 'resources/js/pages/Workspace/Notes/Index/Page.vue';
    $card = 'resources/js/pages/Workspace/Notes/Index/Partials/NoteCard.vue';
    $widget = 'resources/js/widgets/Notes.vue';
    scaffoldCheck(count($notes) === 5 && isset($notes[$page], $notes[$card], $notes[$widget]), 'the notes fixture lists its page, partials and widget');

    [$root, $scaffold] = scaffoldApp();
    $before = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'notes']);
    $written = array_diff_key(scaffoldTree($root), $before);
    scaffoldCheck($exit === 0 && array_keys($written) === array_keys($notes), 'the first run writes exactly the listed files, at the paths kit:add uses');
    foreach ($notes as $path => $bytes) {
        scaffoldCheck(file_get_contents($root.'/'.$path) === $bytes && str_contains($output, 'wrote '.$path."\n"), 'wrote the kit bytes and said so: '.$path);
    }
    scaffoldCheck(str_contains($output, '5 written, 0 skipped.'), 'the run prints its totals');
    scaffoldCheck(! is_dir($root.'/database'), 'migrations are not scaffolded');

    file_put_contents($root.'/'.$page, "<template>app's own page</template>\n");
    file_put_contents($root.'/'.$card, "<template>app's own card</template>\n");
    $edited = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'notes']);
    scaffoldCheck($exit === 0 && scaffoldTree($root) === $edited, 'a second run overwrites nothing');
    scaffoldCheck(str_contains($output, 'skipped '.$page.' (exists; --force='.$page.' overwrites it)') && str_contains($output, '0 written, 5 skipped.'), 'the second run lists every file it skipped');

    [$exit, $output] = $scaffold(['feature' => 'notes', '--diff' => true]);
    scaffoldCheck($exit === 0 && scaffoldTree($root) === $edited, '--diff writes nothing');
    $firstKitLine = strtok($notes[$page], "\n");
    scaffoldCheck(str_contains($output, "--- a/$page\n+++ b/$page\n@@ -1 +1,") && str_contains($output, "\n-<template>app's own page</template>\n") && str_contains($output, "\n+".$firstKitLine."\n"), '--diff shows a unified diff from the app\'s file to the kit\'s version');
    scaffoldCheck(str_contains($output, 'unchanged '.$widget) && ! str_contains($output, '--- a/'.$widget), '--diff says an unchanged file is unchanged');

    [$exit, $output] = $scaffold(['feature' => 'notes', '--force' => [$page]]);
    scaffoldCheck($exit === 0 && file_get_contents($root.'/'.$page) === $notes[$page] && str_contains($output, 'overwrote '.$page), '--force overwrites the path it names');
    scaffoldCheck(file_get_contents($root.'/'.$card) === "<template>app's own card</template>\n" && str_contains($output, 'skipped '.$card), '--force leaves every other existing file alone');

    $edited = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'notes', '--force' => ['resources/js/pages/Nope.vue']]);
    scaffoldCheck($exit !== 0 && str_contains($output, 'names no file') && scaffoldTree($root) === $edited, '--force naming no listed file refuses and writes nothing');
    [$exit] = $scaffold(['feature' => 'notes', '--only' => [$card], '--force' => [$page]]);
    scaffoldCheck($exit !== 0 && scaffoldTree($root) === $edited, '--force outside --only refuses and writes nothing');
    scaffoldCheck(! file_exists($root.'/.mod-maintenance'), 'no records: nothing under .mod-maintenance');

    [$root, $scaffold] = scaffoldApp();
    $before = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'notes', '--only' => [$widget.','.$card]]);
    scaffoldCheck($exit === 0 && array_keys(array_diff_key(scaffoldTree($root), $before)) === [$card, $widget] && str_contains($output, '2 written, 0 skipped.'), '--only writes the named files and nothing else');
    [$exit, $output] = $scaffold(['feature' => 'notes', '--only' => ['resources/js/pages/Workspace/Notes']]);
    scaffoldCheck($exit === 0 && is_file($root.'/'.$page) && str_contains($output, 'skipped '.$card) && ! str_contains($output, $widget), '--only takes a folder and still skips existing files');
    [$exit, $output] = $scaffold(['feature' => 'notes', '--only' => [$widget], '--diff' => true]);
    scaffoldCheck($exit === 0 && trim($output) === 'unchanged '.$widget, '--only filters --diff too');
    $tree = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'notes', '--only' => ['resources/js/pages/Workspace/Note']]);
    scaffoldCheck($exit !== 0 && str_contains($output, 'names no file') && scaffoldTree($root) === $tree, '--only naming no listed path (not a prefix match) refuses');

    [$root, $scaffold] = scaffoldApp();
    $before = scaffoldTree($root);
    [$exit, $output] = $scaffold(['feature' => 'core', '--diff' => true]);
    scaffoldCheck($exit === 0 && str_contains($output, "missing resources/css/kit.css (kit:scaffold writes it)\n--- /dev/null\n+++ b/resources/css/kit.css\n@@ -0,0 +1,") && scaffoldTree($root) === $before, '--diff on a missing file diffs from /dev/null and writes nothing');
    [$exit, $output] = $scaffold(['feature' => 'core']);
    scaffoldCheck($exit === 0 && array_keys(array_diff_key(scaffoldTree($root), $before)) === ['resources/css/kit.css'] && ! is_dir($root.'/resources/js/components'), 'core scaffolds its CSS only: the shared components load from the package');
    [$exit, $output] = $scaffold(['feature' => 'access']);
    scaffoldCheck($exit === 0 && str_contains($output, 'access has no files to scaffold.'), 'a feature with only migrations has nothing to scaffold');
    foreach (['nope' => 'Unknown feature: nope', '../core' => 'Invalid feature id'] as $feature => $error) {
        [$exit, $output] = $scaffold(['feature' => $feature]);
        scaffoldCheck($exit !== 0 && str_contains($output, $error), 'refuses feature '.$feature);
    }
    scaffoldCheck(! file_exists($root.'/.mod-maintenance'), 'no records in a fresh app either');
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage()."\n");
    exit(1);
}
