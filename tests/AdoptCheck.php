<?php

// kit:add and per-feature lineages on a minimal non-starter host app; synthetic fixtures, no network, no consumer acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const ADOPT_PINS = [
    'core' => 'sha256:edb5e28afeb7546a6ca4c289846201de6916ef125ac7712689d19bff5d7b7738',
    'workspaces' => 'sha256:7268f0b08883c256e145d454e447a6dad62fca0c70930d5f32b86c5afc165189',
    'access' => 'sha256:f2ef1f9c7baeda7feeb7549eaada405fc45da33ddb2cb311967f3eae664f86df',
    'settings' => 'sha256:b3b0e4ad7baae2d84f1a4c6b81c8e039759fdcd3928c35a9b695ebd103a88f17',
    'session-ui' => 'sha256:ccc34ab81cdea4fe59b9cf99b02d2ac4661a033bb718f092e1c8301d3a29718c',
    'mcp' => 'sha256:725df145421348cd191ac700410ea750631d3d18981be04d968d01dad6e9203d',
    'notes' => 'sha256:358299f2822501be5ff675d99feae6391e0a94d3b9cb3270a23647307a45d0eb',
];
const ADOPT_BASE = 'sha256:3a2a9567b899aaa739cdf595a5dd622c91edded60b1a637698faa5579f96944a';

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function refuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Expected refusal: '.$message);
}
function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
/** A disposable copy of the host app, with the host verification inputs a trusted binding names. */
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-adopt-'.bin2hex(random_bytes(8));
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
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () use ($operation) { return $operation(); };');

    return [realpath($root), $paths];
}
function bound(string $root, Closure $operation): Closure
{
    return require $root.'/verification/host-operations.txt';
}
/** Every file in the checkout with its bytes' hash: a write anywhere changes it. */
function tree(string $root): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
    }
    ksort($files);

    return $files;
}
function catalog(): Catalog
{
    return Catalog::at(__DIR__.'/fixtures/catalog', ADOPT_PINS, ADOPT_BASE);
}
/** Pins for an edited catalog copy, computed independently of Blueprint. */
function repin(string $root): Catalog
{
    $pins = [];
    foreach (array_keys(ADOPT_PINS) as $id) {
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
function adopted(string $root, string $appId): array
{
    return array_keys(Lineages::adopted($root, $appId, catalog()));
}

try {
    $lifecycle = new Lifecycle;
    $kit = new Lineages($lifecycle, catalog());
    $bindings = ['frontend' => 'SyntheticLayout'];
    $pass = fn () => true;

    // Clean adoption of workspaces: core expands, and the preview writes nothing.
    [$root, $paths] = hostApp();
    $before = tree($root);
    $preview = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    check(array_column($preview['features'], 'feature') === ['core', 'workspaces'] && $preview['executable'], 'workspaces expands to core first and plans as executable');
    check(array_unique(array_merge(...array_map(fn (array $row): array => array_column($row['files'], 'state'), $preview['features']))) === ['create'], 'every file of a clean adoption is a create');
    check(tree($root) === $before && ! is_dir($root.'/.mod-maintenance/installed'), 'the preview writes nothing');
    refuses(fn () => $kit->add($root, 'host', ['workspaces'], $bindings, $paths, 'sha256:wrong', null, bound($root, $pass)), 'expected plan', 'apply refuses a plan identity that was not reviewed');
    check(tree($root) === $before, 'a refused apply writes nothing');
    $result = $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $preview['plan'], 'adopt', bound($root, $pass));
    check($result['status'] === 'completed' && array_keys($result['features']) === ['core', 'workspaces'], 'apply adds core, then workspaces, one plan each');
    check(array_keys(Checkout::installed($root, 'host')) === ['tey/kit:core', 'tey/kit:workspaces'], 'each feature is its own lineage');
    check(is_file($root.'/app/Workspaces/Concerns/BelongsToWorkspace.php') && is_file($root.'/app/Support/Core/CoreMarker.php'), 'the features\' files are delivered');
    check(array_intersect_key(tree($root), $before) === $before, 'host files, app/Domain included, are untouched');
    $again = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    check(array_column($again['features'], 'action') === ['adopted', 'adopted'] && $again['executable'], 're-adopting an adopted feature plans as a no-op');
    $tree = tree($root);
    $noop = $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $again['plan'], null, bound($root, fn () => throw new RuntimeException('must not run')));
    check($noop['status'] === 'completed' && $noop['features'] === [] && tree($root) === $tree, 're-adopting writes nothing and runs no checks');
    check(FeaturesServiceProvider::adopted($root, 'host', null) === ['core', 'workspaces'] && FeaturesServiceProvider::adopted($root, 'host', ['core']) === ['core'], 'the adopted features are the installed lineages, unless kit.features overrides them');

    // Claim of identical pre-existing files.
    [$root, $paths] = hostApp();
    $marker = $kit->artifact('core', $bindings)->files['app/Support/Core/CoreMarker.php'];
    mkdir($root.'/app/Support/Core', 0700, true);
    file_put_contents($root.'/app/Support/Core/CoreMarker.php', $marker);
    touch($root.'/app/Support/Core/CoreMarker.php', 1000000000);
    $preview = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check($preview['features'][0]['files']['app/Support/Core/CoreMarker.php']['state'] === 'claim' && $preview['executable'], 'an identical pre-existing file plans as a claim');
    $result = $kit->add($root, 'host', ['core'], $bindings, $paths, $preview['plan'], null, bound($root, $pass));
    clearstatcache();
    check($result['status'] === 'completed' && filemtime($root.'/app/Support/Core/CoreMarker.php') === 1000000000 && isset(Checkout::installed($root, 'host')['tey/kit:core']['files']['app/Support/Core/CoreMarker.php']), 'the claimed file is owned without a write');

    // Refusal of differing pre-existing files, with no write.
    [$root, $paths] = hostApp();
    mkdir($root.'/app/Workspaces', 0700, true);
    file_put_contents($root.'/app/Workspaces/Workspace.php', '<?php // the host\'s own workspace');
    $before = tree($root);
    $preview = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    check(! $preview['executable'] && $preview['features'][1]['files']['app/Workspaces/Workspace.php']['state'] === 'conflict', 'a differing pre-existing file is a conflict');
    refuses(fn () => $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $preview['plan'], null, bound($root, $pass)), 'not executable', 'a conflicting plan refuses to apply');
    check(tree($root) === $before && adopted($root, 'host') === [], 'the conflict writes nothing, core included');

    // A host-owned overlap is refused.
    $copy = sys_get_temp_dir().'/kit-adopt-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $copy);
    $manifest = json_decode(file_get_contents($copy.'/notes/feature.json'), true);
    $manifest['owns'] = ['app/Domain/Notes'];
    $manifest['files_from']['target'] = 'app/Domain/Notes';
    $manifest['host_owned'] = [];
    file_put_contents($copy.'/notes/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $overlap = new Lineages($lifecycle, repin($copy));
    [$root, $paths] = hostApp();
    $result = $overlap->add($root, 'host', ['workspaces'], $bindings, $paths, $overlap->preview($root, 'host', ['workspaces'], $bindings, $paths)['plan'], null, bound($root, $pass));
    $before = tree($root);
    refuses(fn () => $overlap->preview($root, 'host', ['notes'], $bindings, $paths), 'host-owned', 'a feature owning a path another adopted feature leaves to the host refuses');
    check(tree($root) === $before && ! is_dir($root.'/app/Domain/Notes'), 'the host-owned refusal writes nothing');

    // Partial adoption, then notes later as a new lineage.
    [$root, $paths] = hostApp();
    $preview = $kit->preview($root, 'host', ['workspaces', 'settings'], $bindings, $paths);
    $kit->add($root, 'host', ['workspaces', 'settings'], $bindings, $paths, $preview['plan'], null, bound($root, $pass));
    check(adopted($root, 'host') === ['core', 'workspaces', 'access', 'settings'] && ! is_dir($root.'/app/Notes'), 'partial adoption: workspaces and settings with their requirements, no notes');
    $records = glob($root.'/.mod-maintenance/installed/*.json');
    $earlier = array_combine($records, array_map('file_get_contents', $records));
    $preview = $kit->preview($root, 'host', ['notes'], $bindings, $paths);
    check(array_column($preview['features'], 'feature') === ['core', 'workspaces', 'notes'] && array_column($preview['features'], 'action') === ['adopted', 'adopted', 'add'], 'notes later plans only notes as new');
    $result = $kit->add($root, 'host', ['notes'], $bindings, $paths, $preview['plan'], null, bound($root, $pass));
    check($result['status'] === 'completed' && adopted($root, 'host') === ['core', 'workspaces', 'access', 'settings', 'notes'], 'notes is added as its own lineage');
    check(array_map('file_get_contents', array_keys($earlier)) === array_values($earlier), 'earlier lineages\' records are untouched');

    // An edited owned file refuses that feature's update, not the others'.
    file_put_contents($root.'/app/Settings/SettingsPage.php', '<?php // edited by the host');
    check(! $kit->updatePlan($root, 'host', 'settings', $bindings, $paths)->executable(), 'the edited feature\'s update is a conflict');
    $notes = $kit->updatePlan($root, 'host', 'notes', $bindings, $paths);
    check($notes->executable(), 'another feature\'s update stays executable');
    $result = $kit->updateApply($notes, 'notes', $paths, 'update-notes', bound($root, $pass));
    check($result['status'] === 'completed' && file_get_contents($root.'/app/Settings/SettingsPage.php') === '<?php // edited by the host', 'updating notes leaves the edited settings file alone');
    refuses(fn () => $kit->updatePlan($root, 'host', 'mcp', $bindings, $paths), 'not adopted', 'kit:update refuses a feature that is not adopted');

    // The pending refusal, then resume.
    [$root, $paths] = hostApp();
    $passes = false;
    $host = bound($root, function () use (&$passes): bool {
        return $passes;
    });
    $preview = $kit->preview($root, 'host', ['access'], $bindings, $paths);
    $failed = $kit->add($root, 'host', ['access'], $bindings, $paths, $preview['plan'], 'pilot', $host);
    check($failed['status'] === 'verification_failed' && $failed['failed'] === 'core' && array_keys($failed['features']) === ['core'], 'a failed check stops at the first feature and reports it');
    check(adopted($root, 'host') === [] && ! is_dir($root.'/app/Workspaces'), 'nothing after the failure was attempted');
    refuses(fn () => $kit->preview($root, 'host', ['workspaces'], $bindings, $paths), 'pending:tey/kit:core', 'every other plan is refused while core is unfinished');
    refuses(fn () => $kit->updatePlan($root, 'host', 'core', $bindings, $paths), 'pending:tey/kit:core', 'kit:update is refused while a lineage is unfinished');
    $passes = true;
    $resumed = $kit->resume($root, 'host', ['access'], $bindings, $paths, 'pilot', $host);
    check($resumed['status'] === 'completed' && adopted($root, 'host') === ['core'], 'resume finishes the failed lineage');
    $preview = $kit->preview($root, 'host', ['access'], $bindings, $paths);
    $result = $kit->add($root, 'host', ['access'], $bindings, $paths, $preview['plan'], 'pilot-rest', $host);
    check($result['status'] === 'completed' && adopted($root, 'host') === ['core', 'workspaces', 'access'], 'a fresh plan then adds the rest');

    // kit:add on the console: the preview writes nothing.
    [$root, $paths] = hostApp();
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $app->instance(LifecycleHost::class, new class($lifecycle, $paths, bound($root, $pass)) implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths, private Closure $check) {}

        public function catalog(): Catalog
        {
            return catalog();
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
    });
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $before = tree($root);
    $code = $kernel->call('kit:add', ['features' => ['notes']]);
    $output = $kernel->output();
    check($code === 0 && str_contains($output, 'create app/Notes/Note.php') && str_contains($output, 'Nothing written'), 'kit:add previews per-file states by default');
    check($kernel->call('kit:add', ['features' => ['notes'], '--json' => true]) === 0, 'kit:add --json previews');
    $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    check(tree($root) === $before, 'kit:add preview writes nothing');
    check($kernel->call('kit:add', ['features' => ['notes'], '--apply' => true, '--expected-plan' => 'sha256:stale']) !== 0 && tree($root) === $before, 'kit:add --apply with a stale plan exits nonzero and writes nothing');
    check($kernel->call('kit:add', ['features' => ['notes'], '--apply' => true, '--expected-plan' => $preview['plan']]) === 0 && adopted($root, 'cli') === ['core', 'workspaces', 'notes'], 'kit:add --apply adopts the reviewed plan');
    file_put_contents($root.'/app/Notes/Note.php', '<?php // edited');
    check($kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'notes']) !== 0 && $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']) === 0, 'kit:update --lineage plans per lineage');
    echo "Adoption checks on a synthetic host app only; no package boot, migrations, HTTP or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
