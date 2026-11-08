<?php

// kit:update skips an installed lineage whose feature the app took out of kit.features (it never re-creates the removed files), and kit:remove
// lists, then with --force deletes, the files still unchanged from the kit's copy and drops the lineage record. Synthetic host and catalog.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
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
function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
/** Pins for an edited catalog copy, computed independently of Blueprint. */
function repin(string $root): Catalog
{
    $pins = [];
    // The fixture's features in requires order, then any a test adds (after the ones they require).
    foreach (array_values(array_unique(['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes', ...array_map('basename', glob($root.'/*', GLOB_ONLYDIR))])) as $id) {
        $directory = $root.'/'.$id;
        $stubs = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if ($relative !== 'feature.json' && ! str_contains('/'.$relative, '/.')) {
                $stubs[$relative] = hash('sha256', file_get_contents($file->getPathname()));
            }
        }
        ksort($stubs);
        $pins[$id] = 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $directory.'/feature.json'), $stubs]));
    }

    return Catalog::at($root, $pins, 'sha256:'.hash_file('sha256', $root.'/blueprint.json'));
}
/** A disposable host app, booted, with the host verification inputs a trusted binding names. */
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-remove-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/host-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'verification'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () { return true; };');
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return [$root, $paths, $app, $kernel];
}

try {
    $lifecycle = new Lifecycle;
    $bindings = ['frontend' => 'SyntheticLayout'];
    $catalogRoot = sys_get_temp_dir().'/kit-remove-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $catalogRoot);
    $kit = new Lineages($lifecycle, repin($catalogRoot));
    [$root, $paths, $app, $kernel] = hostApp();
    $check = require $root.'/verification/host-operations.txt';
    $preview = $kit->preview($root, 'cli', ['settings'], $bindings, $paths);
    check($kit->add($root, 'cli', ['settings'], $bindings, $paths, $preview['plan'], null, $check)['status'] === 'completed', 'settings and its requirements install');
    $hostFor = fn (Lineages $kit, array $paths, Closure $check): LifecycleHost => new class($lifecycle, $paths, $kit, $check) implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths, private Lineages $kit, private Closure $check) {}

        public function catalog(): Catalog
        {
            return (new ReflectionProperty(Lineages::class, 'catalog'))->getValue($this->kit);
        }

        public function lifecycle(): Lifecycle
        {
            return $this->lifecycle;
        }

        public function appId(): string
        {
            return 'cli';
        }

        public function bindings(): array
        {
            return ['frontend' => 'SyntheticLayout'];
        }

        public function inputPaths(): array
        {
            return $this->paths;
        }

        public function operationCheck(): Closure
        {
            return $this->check;
        }
    };
    $app->instance(LifecycleHost::class, $hostFor($kit, $paths, $check));
    $installed = array_keys(Lineages::adopted($root, 'cli'));
    $settingsFiles = array_keys(Lineages::adopted($root, 'cli')['settings']['files']);
    check(in_array('settings', $installed, true) && in_array('app/Settings/SettingsPage.php', $settingsFiles, true), 'the settings lineage records its page');

    // The list as the app boots it: session-ui keeps the shell on, so neither kit:update nor kit:remove treats the shell as removed.
    $switchedOn = fn (array $configured): array => (fn () => self::switchedOn($root, 'cli', $configured))->call(new Lineages($lifecycle));
    check(in_array('shell', $switchedOn(['core', 'session-ui']), true) && ! in_array('shell', $switchedOn(['core']), true), 'a kit.features list naming session-ui keeps the shell on');
    // Following the lineages (kit.features null), nothing is removed.
    check(Lineages::removed($root, 'cli', null) === [], 'with kit.features null, no lineage counts as removed');

    // The app removes settings: kit.features lists the rest, and its page is gone.
    $on = array_values(array_diff($installed, ['settings']));
    config(['kit.features' => $on]);
    $page = file_get_contents($root.'/app/Settings/SettingsPage.php');
    unlink($root.'/app/Settings/SettingsPage.php');
    check(Lineages::removed($root, 'cli', $on) === ['settings'], 'a lineage kit.features no longer names counts as removed');
    $code = $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'settings']);
    $out = $kernel->output();
    check($code === 1 && str_contains($out, 'settings is installed but not in kit.features') && str_contains($out, 'kit:remove settings'), 'kit:update --lineage refuses a removed feature and names kit:remove');
    check(! file_exists($root.'/app/Settings/SettingsPage.php'), 'and re-creates none of its files');
    $kernel->call('kit:update', ['action' => 'plan']);
    $order = $kernel->output();
    check(! str_contains($order, '--lineage=settings') && str_contains($order, '--lineage=core') && str_contains($order, 'WARNING: skipped settings.'), 'the update order leaves the removed feature out, with a warning');
    check($kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']) === 0, 'a lineage still in kit.features plans as before');

    // kit:remove refuses what it must not drop.
    check($kernel->call('kit:remove', ['feature' => 'workspaces']) === 1 && str_contains($kernel->output(), 'is still on'), 'kit:remove refuses a feature kit.features still names');
    config(['kit.features' => array_values(array_diff($on, ['access']))]);
    check($kernel->call('kit:remove', ['feature' => 'access']) === 1 && str_contains($kernel->output(), 'settings requires access'), 'kit:remove refuses a feature another installed lineage requires');
    config(['kit.features' => $on]);
    check($kernel->call('kit:remove', ['feature' => 'notes']) === 1 && str_contains($kernel->output(), 'No installed lineage for notes'), 'kit:remove refuses a feature that is not installed');

    // A dry run lists, and writes nothing.
    $kernel->call('kit:remove', ['feature' => 'settings', '--json' => true]);
    $dry = json_decode($kernel->output(), true);
    check($dry['removed'] === false && in_array('app/Settings/SettingsPage.php', $dry['missing'], true) && is_file($root.'/'.Lineages::recordPath('settings')), 'without --force kit:remove lists the files and writes nothing');

    // The page restored as the kit wrote it, and every other recorded file changed by the app: the page goes, the rest stay.
    file_put_contents($root.'/app/Settings/SettingsPage.php', $page);
    $mine = array_values(array_diff($settingsFiles, ['app/Settings/SettingsPage.php']));
    foreach ($mine as $path) {
        file_put_contents($root.'/'.$path, "\n// the app's change\n", FILE_APPEND);
    }
    check($mine !== [], 'the fixture has a second settings file to change');
    $kernel->call('kit:remove', ['feature' => 'settings']);
    $text = $kernel->output();
    check(str_contains($text, 'would delete: app/Settings/SettingsPage.php') && str_contains($text, 'kept (changed since the kit wrote it, so it is yours): '.$mine[0]) && str_contains($text, 'kit:remove settings --force'), 'the text lists what it would delete, what it keeps, and the --force command');
    $deleting = ['app/Settings/SettingsPage.php'];
    check($kernel->call('kit:remove', ['feature' => 'settings', '--force' => true]) === 0 && str_contains($kernel->output(), 'settings removed'), 'kit:remove --force removes the feature');
    check(! isset(Checkout::installed($root, 'cli')[Lineages::name('settings')]) && ! is_file($root.'/'.Lineages::recordPath('settings')), 'the lineage record is gone');
    check(array_filter($deleting, fn (string $path): bool => file_exists($root.'/'.$path)) === [], 'every unchanged file is deleted');
    check(array_filter($mine, fn (string $path): bool => ! is_file($root.'/'.$path)) === [], 'the changed files stay');
    check(array_keys(Lineages::adopted($root, 'cli')) === $on && Lineages::removed($root, 'cli', $on) === [], 'the other lineages stay installed, and nothing counts as removed');
    $kernel->call('kit:update', ['action' => 'plan']);
    check(! str_contains($kernel->output(), 'WARNING'), 'kit:update has nothing left to skip');
    $code = $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'access']);
    $out = $kernel->output();
    check($code === 0, 'and the remaining lineages still plan ('.substr($out, 0, 300).')');

    // A catalog with a workspace-ui the fixture lacks: a pre-split session-ui carries it, as the app boots.
    $splitRoot = sys_get_temp_dir().'/kit-remove-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $splitRoot);
    mkdir($splitRoot.'/workspace-ui');
    file_put_contents($splitRoot.'/workspace-ui/page.php.stub', "<?php\n\n// workspace pages\n");
    file_put_contents($splitRoot.'/workspace-ui/feature.json', json_encode(['format' => 1, 'id' => 'workspace-ui', 'owns' => ['app/WorkspacePages'], 'files' => ['app/WorkspacePages/Page.php' => ['stub' => 'page.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated']], 'requires' => ['features' => ['workspaces'], 'packages' => (object) []], 'conflicts' => [], 'host_owned' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $split = new Lineages($lifecycle, repin($splitRoot));
    [$root, $paths, $app, $kernel] = hostApp();
    $check = require $root.'/verification/host-operations.txt';
    $preview = $split->preview($root, 'cli', ['session-ui', 'workspace-ui'], $bindings, $paths);
    check($split->add($root, 'cli', ['session-ui', 'workspace-ui'], $bindings, $paths, $preview['plan'], null, $check)['status'] === 'completed', 'session-ui and workspace-ui install');
    $app->instance(LifecycleHost::class, $hostFor($split, $paths, $check));
    $installed = array_keys(Lineages::adopted($root, 'cli'));

    // 1. A feature the app still boots is refused, though kit.features doesn't name it: session-ui carries workspace-ui.
    $carrying = array_values(array_diff($installed, ['workspace-ui']));
    config(['kit.features' => $carrying]);
    check(Lineages::removed($root, 'cli', $carrying) === [], 'workspace-ui carried by session-ui is not removed, though kit.features leaves it out');
    $code = $kernel->call('kit:remove', ['feature' => 'workspace-ui', '--force' => true]);
    $out = $kernel->output();
    check($code === 1 && str_contains($out, 'kit.features names session-ui') && is_file($root.'/'.Lineages::recordPath('workspace-ui')) && is_file($root.'/app/WorkspacePages/Page.php'), 'kit:remove --force refuses workspace-ui while session-ui carries it, and deletes nothing');

    // 2. A stale journal (a no-op update's, left behind) doesn't stop --force; it goes with the record.
    $noop = fn (string $intent): array => $split->updateApply($split->updatePlan($root, 'cli', 'workspace-ui', $bindings, $paths), 'workspace-ui', $paths, $intent, $check);
    $staleJournal = $root.'/.mod-maintenance/journal-'.hash('sha256', 'noop-one').'.json';
    check($noop('noop-one')['status'] === 'completed', 'a no-op update of workspace-ui completes');
    $staleBytes = file_get_contents($staleJournal);
    file_put_contents($root.'/'.$paths['dependency-lock'], 'synthetic fixture dependency-lock, updated');
    check($noop('noop-two')['status'] === 'completed', 'a second no-op update completes');
    file_put_contents($staleJournal, $staleBytes);
    check(array_column(Lineages::journals($root, 'cli'), 'state', 'lineage') === ['tey/kit:workspace-ui' => 'stale'], 'workspace-ui\'s only unfinished-looking journal is stale');
    $off = array_values(array_diff($installed, ['workspace-ui', 'session-ui']));
    config(['kit.features' => $off]);
    $code = $kernel->call('kit:remove', ['feature' => 'workspace-ui', '--force' => true]);
    $out = $kernel->output();
    check($code === 0 && ! is_file($root.'/'.Lineages::recordPath('workspace-ui')) && ! is_file($root.'/app/WorkspacePages/Page.php') && ! is_file($staleJournal), 'kit:remove --force removes a feature whose only journal is stale, with that journal');
    check(Lineages::journals($root, 'cli') === [] && $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']) === 0, 'nothing is left pending, and the remaining lineages plan');

    // 3. A delete that fails: the command fails, says which file, and keeps the lineage record; once fixed, it runs again.
    [$root, $paths, $app, $kernel] = hostApp();
    $check = require $root.'/verification/host-operations.txt';
    $preview = $kit->preview($root, 'cli', ['settings'], $bindings, $paths);
    $kit->add($root, 'cli', ['settings'], $bindings, $paths, $preview['plan'], null, $check);
    $app->instance(LifecycleHost::class, $hostFor($kit, $paths, $check));
    config(['kit.features' => array_values(array_diff(array_keys(Lineages::adopted($root, 'cli')), ['settings']))]);
    chmod($root.'/app/Settings', 0500);
    try {
        $code = $kernel->call('kit:remove', ['feature' => 'settings', '--force' => true]);
        $out = $kernel->output();
    } finally {
        chmod($root.'/app/Settings', 0700);
    }
    check($code === 1 && str_contains($out, 'Could not delete app/Settings/SettingsPage.php'), 'a failed delete fails kit:remove and names the file');
    check(is_file($root.'/'.Lineages::recordPath('settings')) && isset(Checkout::installed($root, 'cli')[Lineages::name('settings')]) && is_file($root.'/app/Settings/SettingsPage.php'), 'the lineage record is kept, so settings is still installed');
    check($kernel->call('kit:remove', ['feature' => 'settings', '--force' => true]) === 0 && ! is_file($root.'/'.Lineages::recordPath('settings')) && ! is_file($root.'/app/Settings/SettingsPage.php'), 'once the file can be deleted, kit:remove --force finishes');
    echo "Removed-feature checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n");
    exit(1);
}
