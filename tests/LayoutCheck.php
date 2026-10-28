<?php

// The kit under a host's own tey/mod layout: a layout extending `modules` (app/Modules/{module} plus an app/UI mount) boots, and kit:install,
// kit:add, kit:status, kit:update and kit:adopt write and read kit files at the same canonical paths as under `laravel`. Synthetic SQLite memory, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lineages;
use Tey\Mod\Facades\Mod;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** A host's own layout, as an app defines it: modules under app/Modules, and a UI mount. */
final class HostLayoutProvider extends ServiceProvider
{
    public function register(): void
    {
        Mod::layout('host-modules')->extends('modules')->path('app/Modules/{module}')
            ->mounts('ui', 'App\\UI\\', 'app/UI', fn ($layout) => $layout->generates('ui-panel', in: 'Panels'));
    }
}
function copyTree(string $source, string $destination): void
{
    @mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? @mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
function freshApp(string $layout): string
{
    $root = sys_get_temp_dir().'/kit-layout-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/fresh-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.lock', "{\"packages\": []}\n");
    file_put_contents($root.'/config/mod.php', "<?php\n\nreturn ['layout' => '{$layout}'];\n");

    return realpath($root);
}
function console(string $root, array $providers = []): Kernel
{
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, HostLayoutProvider::class, KitServiceProvider::class, ...$providers])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return $kernel;
}
/** The app's files with their hashes, leaving out per-checkout state: maintenance records (their commit receipts bind the checkout), caches and the layout choice. */
function tree(string $root): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (! preg_match('#^(\.mod-maintenance/|storage/|bootstrap/cache/|config/mod\.php$)#', $relative)) {
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);

    return $files;
}
/** kit:install, then kit:add core through preview and apply, then status and an update plan, under one layout. */
function adopt(string $layout): array
{
    global $loader;
    $root = freshApp($layout);
    $loader->addPsr4('App\\', $root.'/app', true);
    $kernel = console($root);
    check(config('mod.layout') === $layout, $layout.': the host boots with the kit on its own layout');
    check($kernel->call('kit:install') === 0, $layout.': kit:install exits 0');
    $kernel = console($root);
    $kernel->call('migrate:install');
    app('db')->connection()->getSchemaBuilder()->create('users', fn ($table) => $table->id());
    $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
    $plan = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'];
    $code = $kernel->call('kit:add', ['features' => ['core'], '--apply' => true, '--expected-plan' => $plan, '--intent' => 'layout']);
    check($code === 0 && array_keys(Lineages::adopted($root, 'fresh-app')) === ['core'], $layout.': kit:add core plans and applies'.($code === 0 ? '' : ': '.$kernel->output()));
    check($kernel->call('kit:status', ['--json' => true]) === 0 && str_contains($kernel->output(), '"core"'), $layout.': kit:status reads the adopted lineage');
    $code = $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']);
    check($code === 0, $layout.': kit:update plans the core lineage'.($code === 0 ? '' : ': '.$kernel->output()));
    $kernel = console($root, [FeaturesServiceProvider::class]);
    check(config('mod.layout') === $layout, $layout.': the adopted features\' providers boot');

    return [$root, $kernel];
}

try {
    // A class loads once per process, and each fresh app has its own App\\ classes: each app runs in a child process of this script.
    $mode = $argv[2] ?? null;
    if ($mode === 'laravel' || $mode === 'host-modules') {
        [$root, $kernel] = adopt($mode);
        if ($mode === 'host-modules') {
            // The v0.1 kit:foundation scaffold places through the host's layout, so it alone refuses another one, when it runs, before writing.
            $before = tree($root);
            $refused = false;
            try {
                $refused = $kernel->call('kit:foundation', ['--no-interaction' => true]) !== 0;
            } catch (RuntimeException $error) {
                $refused = str_starts_with($error->getMessage(), 'The kit:foundation scaffold requires the laravel layout.');
            }
            check($refused && tree($root) === $before, 'only the v0.1 kit:foundation scaffold refuses another layout, when invoked, and writes nothing');
        }
        echo 'RESULT '.json_encode(['root' => $root, 'tree' => tree($root), 'identity' => Checkout::installed($root, 'fresh-app')['tey/kit:core']['identity']], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    if ($mode === 'adopt') {
        // kit:adopt reads a pre-lifecycle app's kit files at the canonical paths under the host's layout too.
        $legacy = freshApp('host-modules');
        $loader->addPsr4('App\\', $legacy.'/app', true);
        $source = $argv[3];
        foreach (tree($source) as $relative => $hash) {
            if (! is_file($legacy.'/'.$relative) && ! str_starts_with($relative, 'app/Kit/') && ! in_array($relative, ['config/kit-lifecycle.php', 'config/kit-executor.json', 'app/Providers/KitHostServiceProvider.php', 'bootstrap/providers.php'], true)) {
                @mkdir(dirname($legacy.'/'.$relative), 0700, true);
                copy($source.'/'.$relative, $legacy.'/'.$relative);
            }
        }
        $kernel = console($legacy);
        check($kernel->call('kit:install') === 0, 'host-modules: kit:install on an app with pre-lifecycle kit files');
        $kernel = console($legacy);
        $code = $kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']);
        check($code === 0, 'host-modules: kit:adopt core plans from the canonical paths'.($code === 0 ? '' : ': '.$kernel->output()));
        exit(0);
    }
    $run = function (array $arguments): array {
        exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, __FILE__, $GLOBALS['argv'][1], ...$arguments])).' 2>&1', $output, $code);
        $result = null;
        foreach ($output as $line) {
            str_starts_with($line, 'RESULT ') ? $result = json_decode(substr($line, 7), true, flags: JSON_THROW_ON_ERROR) : print($line."\n");
        }
        if ($code !== 0) {
            throw new RuntimeException('The '.$arguments[0].' run failed.');
        }

        return $result ?? [];
    };
    $laravel = $run(['laravel']);
    $custom = $run(['host-modules']);
    check($custom['tree'] === $laravel['tree'], 'kit files land at the same canonical paths, with the same bytes, as under laravel');
    check(! is_dir($custom['root'].'/app/Modules') && ! is_dir($custom['root'].'/app/UI') && isset($custom['tree']['resources/js/components/Kit/index.ts']), 'nothing lands in the host\'s module tree or UI mount; the kit\'s own resources/js tree is used');
    check($custom['identity'] === $laravel['identity'], 'the installed core lineage has the same identity under both layouts');
    $run(['adopt', $custom['root']]);
    echo "Layout checks on synthetic fresh apps only; package providers registered by hand, no HTTP, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
