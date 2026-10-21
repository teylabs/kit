<?php

// External catalogs, stub trees and the catalog host; no consumer acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Mod\ModServiceProvider;
use Tey\Kit\Blueprint;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const FIXTURE_PINS = [
    'core' => 'sha256:edb5e28afeb7546a6ca4c289846201de6916ef125ac7712689d19bff5d7b7738',
    'workspaces' => 'sha256:7268f0b08883c256e145d454e447a6dad62fca0c70930d5f32b86c5afc165189',
    'access' => 'sha256:f2ef1f9c7baeda7feeb7549eaada405fc45da33ddb2cb311967f3eae664f86df',
    'settings' => 'sha256:b3b0e4ad7baae2d84f1a4c6b81c8e039759fdcd3928c35a9b695ebd103a88f17',
    'session-ui' => 'sha256:ccc34ab81cdea4fe59b9cf99b02d2ac4661a033bb718f092e1c8301d3a29718c',
    'mcp' => 'sha256:725df145421348cd191ac700410ea750631d3d18981be04d968d01dad6e9203d',
    'notes' => 'sha256:358299f2822501be5ff675d99feae6391e0a94d3b9cb3270a23647307a45d0eb',
];
const FIXTURE_BASE = 'sha256:3a2a9567b899aaa739cdf595a5dd622c91edded60b1a637698faa5579f96944a';

function catalogCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function catalogRefuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        catalogCheck(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Expected refusal: '.$message);
}
function fixtureCatalog(): Catalog
{
    return Catalog::at(__DIR__.'/fixtures/catalog', FIXTURE_PINS, FIXTURE_BASE);
}
/** A disposable copy of the fixture catalog, with the pins its callers re-derive after editing it. */
function copyCatalog(): string
{
    $copy = sys_get_temp_dir().'/blueprint-catalog-copy-'.bin2hex(random_bytes(8));
    $source = __DIR__.'/fixtures/catalog';
    mkdir($copy, 0700);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $copy.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }

    return $copy;
}
/** Pins computed independently of Blueprint: manifest bytes plus every file under the feature except dotfiles. */
function pinsOf(string $root): array
{
    $pins = [];
    foreach (array_keys(FIXTURE_PINS) as $id) {
        $stubs = [];
        $directory = $root.'/'.$id;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $listed = str_contains(file_get_contents($directory.'/feature.json'), '"'.$relative.'"');
            if ($relative !== 'feature.json' && ! is_dir($file->getPathname()) && (! str_contains('/'.$relative, '/.') || $listed)) {
                $stubs[$relative] = hash('sha256', file_get_contents($file->getPathname()));
            }
        }
        ksort($stubs);
        $pins[$id] = 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $directory.'/feature.json'), $stubs]));
    }

    return $pins;
}
function repin(string $root): Catalog
{
    return Catalog::at($root, pinsOf($root), 'sha256:'.hash_file('sha256', $root.'/blueprint.json'));
}
function editManifest(string $root, string $id, Closure $edit): void
{
    $path = $root.'/'.$id.'/feature.json';
    file_put_contents($path, json_encode($edit(json_decode(file_get_contents($path), true)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

try {
    // The package catalog is the starter catalog; foundation/defaults are the v0.1 fixture catalog.
    $package = Catalog::package();
    catalogCheck($package->featureIds() === ['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp'] && Blueprint::featureIds() === $package->featureIds(), 'the package catalog is the six starter features');
    catalogRefuses(fn () => Blueprint::features(['foundation']), 'Unknown feature: foundation', 'foundation is not in the package catalog');
    $v01 = require __DIR__.'/fixtures/v01.php';
    catalogCheck($v01->featureIds() === ['foundation', 'defaults'], 'the v0.1 fixture catalog lists foundation then defaults');
    catalogCheck(Blueprint::all($v01)->sourceReference() === 'sha256:40df4082450855d896de13b2ec86802b0bc9ac0b9482bcbffd359773246c9b98', 'the v0.1 fixture keeps its pinned identity after the move');
    $releases = ['A' => 'sha256:a59a38d43c2a471ad2d887b6c0f790692d092b491adf65bbe6b9aaf129ac8260', 'B' => 'sha256:53e31b60ebcf21053cc934c860f2fee62fad5ae2bb06da12717d7b7385f74d59', 'C' => 'sha256:8699fd47655acf05e1704c681166fe766c5883e2f0c2abf6bf5e14f3405caf73', 'D' => 'sha256:0bab54b840743e0fb61273223b8e808e0db06a76c279418aa9765e75d1783672', 'E' => 'sha256:5c353100ab7dea9e497e01f7c1bef3ef1922fae8c5fd39e549c5a103ab5331c6'];
    foreach ($releases as $release => $identity) {
        catalogCheck(Blueprint::release($release)->sourceReference() === $identity, 'release '.$release.' identity is unchanged');
    }

    // An external catalog builds with its own pins.
    $catalog = fixtureCatalog();
    $all = Blueprint::all($catalog);
    catalogCheck($all->selection() === array_keys(FIXTURE_PINS) && $all->manifest['id'] === 'fixture/catalog-app', 'the external catalog loads all seven features in its own order');
    catalogCheck($all->sourceReference() !== Blueprint::all($v01)->sourceReference(), 'an external catalog has its own identity');
    catalogCheck(Blueprint::features(['notes', 'workspaces', 'core'], $catalog)->sourceReference() === Blueprint::features(['core', 'workspaces', 'notes'], $catalog)->sourceReference(), 'selection order does not change an external identity');
    catalogCheck($all->sourceReference() === Blueprint::all($catalog)->sourceReference(), 'the same external selection produces the same identity');
    catalogCheck(count($all->snapshot()['files']) === 10, 'the whole fixture app generates ten files');
    $files = Blueprint::features(['core', 'workspaces'], $catalog)->snapshot()['files'];
    catalogCheck(str_contains($files['app/Workspaces/Concerns/BelongsToWorkspace.php'], 'namespace App\\Workspaces\\Concerns;') && str_contains($files['app/Workspaces/Concerns/BelongsToWorkspace.php'], 'final class BelongsToWorkspace'), 'tree stubs render with their own namespace and class');
    catalogCheck(Blueprint::features(['core'], $catalog)->sourceReference() !== Blueprint::features(['core', 'workspaces'], $catalog)->sourceReference(), 'a larger external selection has a different identity');
    catalogCheck(Blueprint::features(['core'], $catalog)->stub('core-marker.php.stub') !== '', 'an external stub reads from the catalog');
    Blueprint::assertGrowth(Blueprint::features(['core'], $catalog), Blueprint::features(['core', 'workspaces'], $catalog));
    catalogRefuses(fn () => Blueprint::assertGrowth(Blueprint::features(['core', 'workspaces'], $catalog), Blueprint::features(['core'], $catalog)), 'Feature removal is refused: workspaces', 'external features can only be added');

    // Catalog values are validated.
    catalogRefuses(fn () => Catalog::at(__DIR__.'/fixtures/missing', FIXTURE_PINS, FIXTURE_BASE), 'catalog root', 'a missing root refuses');
    catalogRefuses(fn () => Catalog::at(__DIR__.'/fixtures/catalog', ['../core' => FIXTURE_PINS['core']], FIXTURE_BASE), 'Invalid catalog feature id', 'a path-like feature id refuses');
    catalogRefuses(fn () => Catalog::at(__DIR__.'/fixtures/catalog', ['core' => 'sha256:abc'], FIXTURE_BASE), 'Invalid catalog pin', 'a malformed pin refuses');
    catalogRefuses(fn () => Catalog::at(__DIR__.'/fixtures/catalog', FIXTURE_PINS, 'nope'), 'base pin', 'a malformed base pin refuses');
    catalogRefuses(fn () => Catalog::at(__DIR__.'/fixtures/catalog', [], FIXTURE_BASE), 'at least one', 'an empty catalog refuses');

    // Identity checks: a wrong pin, a tampered stub, manifest or base is refused.
    $wrong = FIXTURE_PINS;
    $wrong['workspaces'] = FIXTURE_PINS['core'];
    $bad = Catalog::at(__DIR__.'/fixtures/catalog', $wrong, FIXTURE_BASE);
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], $bad), 'identity mismatch', 'a wrong feature pin refuses');
    catalogCheck(Blueprint::features(['core'], $bad)->selection() === ['core'], 'a wrong pin on an unselected feature does not block the others');
    catalogRefuses(fn () => Blueprint::all(Catalog::at(__DIR__.'/fixtures/catalog', FIXTURE_PINS, FIXTURE_PINS['core'])), 'identity mismatch', 'a wrong base pin refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    catalogCheck(Blueprint::all($pinned)->sourceReference() === $all->sourceReference(), 'an unmodified copy re-derives the same pins and identity');
    file_put_contents($copy.'/workspaces/stubs/Workspace.php.stub', file_get_contents($copy.'/workspaces/stubs/Workspace.php.stub').'// changed');
    catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'a tampered tree stub refuses');
    $repinned = repin($copy);
    $built = Blueprint::all($repinned);
    file_put_contents($copy.'/core/core-marker.php.stub', 'tampered after build');
    catalogRefuses(fn () => $built->sourceReference(), 'identity mismatch', 'a stub tampered after the blueprint was built refuses');
    catalogRefuses(fn () => $built->snapshot(), 'identity mismatch', 'a tampered stub never reaches a snapshot');
    $copy = copyCatalog();
    $pinned = repin($copy);
    file_put_contents($copy.'/mcp/mcp-entry.php.stub', 'tampered');
    catalogRefuses(fn () => Blueprint::features(['workspaces', 'core', 'mcp'], $pinned), 'identity mismatch', 'a tampered explicit stub refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    editManifest($copy, 'access', fn (array $m): array => $m + ['note' => 'changed']);
    catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'a tampered feature manifest refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    file_put_contents($copy.'/blueprint.json', str_replace('fixture/catalog-app', 'other/app', file_get_contents($copy.'/blueprint.json')));
    catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'a tampered base manifest refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    file_put_contents($copy.'/workspaces/stubs/Extra.php.stub', '<?php // added');
    catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'a stub added to a tree refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    unlink($copy.'/notes/stubs/Note.php.stub');
    catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'a stub removed from a tree refuses');
    $copy = copyCatalog();
    $pinned = repin($copy);
    unlink($copy.'/core/feature.json');
    catalogRefuses(fn () => Blueprint::features(['core'], $pinned), 'Missing or linked feature manifest', 'a missing feature manifest refuses');

    // Requires and conflicts over the seven-feature graph.
    $graph = ['workspaces' => ['core'], 'access' => ['workspaces'], 'settings' => ['workspaces', 'access'], 'session-ui' => ['workspaces'], 'mcp' => ['workspaces'], 'notes' => ['workspaces']];
    foreach ($graph as $id => $requires) {
        $manifest = json_decode(file_get_contents(__DIR__.'/fixtures/catalog/'.$id.'/feature.json'), true);
        catalogCheck($manifest['requires']['features'] === $requires, 'fixture '.$id.' requires '.implode(' and ', $requires));
    }
    catalogRefuses(fn () => Blueprint::features(['workspaces'], $catalog), 'requires feature core', 'workspaces without core refuses');
    catalogRefuses(fn () => Blueprint::features(['core', 'notes'], $catalog), 'Feature notes requires feature workspaces', 'notes without workspaces refuses');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces', 'settings'], $catalog), 'Feature settings requires feature access', 'settings without access refuses');
    catalogRefuses(fn () => Blueprint::features(['core', 'access'], $catalog), 'Feature access requires feature workspaces', 'access without workspaces refuses');
    catalogRefuses(fn () => Blueprint::features(['core', 'nope'], $catalog), 'Unknown feature: nope', 'a feature the catalog does not pin refuses');
    catalogRefuses(fn () => Blueprint::features(['foundation'], $catalog), 'Unknown feature: foundation', 'a v0.1 fixture feature is not in another catalog');
    catalogRefuses(fn () => Blueprint::features(['core', 'core'], $catalog), 'once', 'a duplicate selection refuses');
    foreach ([['core', 'workspaces'], ['core', 'workspaces', 'access', 'settings'], ['core', 'workspaces', 'session-ui', 'mcp']] as $selection) {
        catalogCheck(Blueprint::features($selection, $catalog)->selection() === $selection, 'selection '.implode(',', $selection).' resolves');
    }
    $copy = copyCatalog();
    editManifest($copy, 'mcp', fn (array $m): array => ['conflicts' => ['notes']] + $m);
    $conflicting = repin($copy);
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces', 'mcp', 'notes'], $conflicting), 'conflicts with feature notes', 'a conflict refuses');
    catalogCheck(Blueprint::features(['core', 'workspaces', 'mcp'], $conflicting)->selection() === ['core', 'workspaces', 'mcp'], 'a conflict only matters when both are selected');
    $copy = copyCatalog();
    editManifest($copy, 'notes', function (array $m): array {
        $m['owns'][] = 'app/Mcp';

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces', 'mcp', 'notes'], repin($copy)), 'overlapping paths', 'overlapping ownership across features refuses');

    // files_from: expansion.
    $workspaces = Blueprint::features(['core', 'workspaces'], $catalog)->manifest['generated'];
    catalogCheck(array_keys($workspaces) === ['app/Support/Core/CoreMarker.php', 'app/Workspaces/Concerns/BelongsToWorkspace.php', 'app/Workspaces/Workspace.php'], 'a tree expands sorted, drops .stub from targets and skips dotfiles');
    catalogCheck($workspaces['app/Workspaces/Workspace.php'] === ['stub' => 'stubs/Workspace.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated'], 'expanded entries are generated and carry their stub path');
    $settings = Blueprint::features(['core', 'workspaces', 'access', 'settings'], $catalog)->manifest['generated'];
    catalogCheck(array_slice(array_keys($settings), -2) === ['app/Settings/SettingsPage.php', 'app/Settings/Preferences.php'], 'explicit files come first, then the expanded tree');
    $first = Blueprint::all($catalog);
    catalogCheck(json_encode($first->manifest['generated']) === json_encode(Blueprint::all($catalog)->manifest['generated']), 'expansion is deterministic');

    // Equivalence with an explicit files map, and identity over every expanded byte.
    $copy = copyCatalog();
    $explicit = [];
    foreach (['Workspace.php.stub' => 'Workspace.php', 'Concerns/BelongsToWorkspace.php.stub' => 'Concerns/BelongsToWorkspace.php'] as $stub => $target) {
        $explicit['app/Workspaces/'.$target] = ['stub' => 'stubs/'.$stub, 'scaffold' => 'kit', 'ownership' => 'generated'];
    }
    ksort($explicit, SORT_STRING);
    $treeManifest = Blueprint::features(['core', 'workspaces'], $catalog)->manifest;
    editManifest($copy, 'workspaces', function (array $m) use ($explicit): array {
        unset($m['files_from']);
        $m['files'] = $explicit;

        return $m;
    });
    $listed = Blueprint::features(['core', 'workspaces'], repin($copy));
    catalogCheck(array_slice($listed->manifest['generated'], 1) === array_slice($treeManifest['generated'], 1) && array_keys($listed->manifest['generated']) === ['app/Support/Core/CoreMarker.php', 'app/Workspaces/Concerns/BelongsToWorkspace.php', 'app/Workspaces/Workspace.php'], 'a stub tree expands to exactly the explicit files map');
    catalogCheck($listed->snapshot()['files'] === Blueprint::features(['core', 'workspaces'], $catalog)->snapshot()['files'], 'both give the same generated bytes');
    catalogCheck($listed->sourceReference() !== Blueprint::features(['core', 'workspaces'], $catalog)->sourceReference(), 'the manifest declaration itself is part of the identity');
    $bytes = [];
    foreach (['workspaces' => ['Workspace.php.stub', 'Concerns/BelongsToWorkspace.php.stub'], 'notes' => ['Note.php.stub', 'Policies/NotePolicy.php.stub'], 'settings' => ['Preferences.php.stub']] as $id => $stubs) {
        foreach ($stubs as $stub) {
            $copy = copyCatalog();
            $pinned = repin($copy);
            $path = $copy.'/'.$id.'/stubs/'.$stub;
            file_put_contents($path, file_get_contents($path)."\n");
            catalogRefuses(fn () => Blueprint::all($pinned), 'identity mismatch', 'identity covers the bytes of '.$id.'/stubs/'.$stub);
            $bytes[] = $stub;
        }
    }
    catalogCheck(count($bytes) === 5, 'every expanded stub was tampered with once');

    // files_from: refusals.
    $copy = copyCatalog();
    symlink($copy.'/core/core-marker.php.stub', $copy.'/workspaces/stubs/Linked.php.stub');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Linked stub refused', 'a symlinked stub in a tree refuses');
    $copy = copyCatalog();
    mkdir($copy.'/outside');
    file_put_contents($copy.'/outside/Escape.php.stub', '<?php // outside');
    symlink($copy.'/outside', $copy.'/workspaces/stubs/Escape');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Linked stub refused', 'a symlinked directory in a tree refuses');
    $copy = copyCatalog();
    rename($copy.'/workspaces/stubs', $copy.'/stubs-elsewhere');
    symlink($copy.'/stubs-elsewhere', $copy.'/workspaces/stubs');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'linked or escaping', 'a symlinked stub tree root refuses');
    foreach (['../core', '/etc', 'stubs/../../core', 'stubs//x', './stubs', 'stu bs'] as $stubs) {
        $copy = copyCatalog();
        editManifest($copy, 'workspaces', function (array $m) use ($stubs): array {
            $m['files_from']['stubs'] = $stubs;

            return $m;
        });
        catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Invalid catalog path', 'files_from stubs "'.$stubs.'" cannot leave the feature');
    }
    foreach (['../Elsewhere', '/app/Workspaces', 'app/Workspaces/../Access'] as $target) {
        $copy = copyCatalog();
        editManifest($copy, 'workspaces', function (array $m) use ($target): array {
            $m['files_from']['target'] = $target;

            return $m;
        });
        catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Invalid catalog path', 'files_from target "'.$target.'" cannot leave the catalog');
    }
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files_from']['target'] = 'app/Access';

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'outside feature ownership', 'a target outside the feature owns refuses');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files_from']['target'] = 'app/Workspaces-other';

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'outside feature ownership', 'a sibling path that only shares a prefix refuses');
    $copy = copyCatalog();
    file_put_contents($copy.'/workspaces/stubs/Workspace.php', '<?php // same target as Workspace.php.stub');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Duplicate target path', 'two stubs for one target refuse');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files'] = ['app/Workspaces/Workspace.php' => ['stub' => 'stubs/Other.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated']];

        return $m;
    });
    file_put_contents($copy.'/workspaces/stubs/Other.php.stub', '<?php // listed twice');
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Duplicate target path', 'a tree target that an explicit file also names refuses');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files_from']['extra'] = true;

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Invalid files_from', 'an unknown files_from key refuses');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files_from'] = 'stubs';

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Invalid files_from', 'a malformed files_from refuses');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['host_owned'][] = 'app/Workspaces/Concerns';

        return $m;
    });
    catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'host-owned', 'an expanded path inside a host-owned path refuses');
    foreach ([['uses', 'implements'], ['implements', 'uses']] as $order) {
        $copy = copyCatalog();
        editManifest($copy, 'workspaces', function (array $m) use ($order): array {
            $m['host_steps'] = ['user_model' => array_combine($order, array_map(fn (string $key): string => $key === 'uses' ? 'Vendor\\Concerns\\Joins' : 'Vendor\\Contracts\\Member', $order))];

            return $m;
        });
        catalogCheck(Blueprint::features(['core', 'workspaces'], repin($copy))->manifest['features'] === ['core', 'workspaces'], 'a user_model host step is accepted with its keys '.implode(', ', $order));
    }
    foreach (['an extra key' => ['implements' => 'A', 'uses' => 'B', 'extra' => 'C'], 'a missing key' => ['uses' => 'B'], 'a non-string value' => ['uses' => 'B', 'implements' => ['A']]] as $label => $step) {
        $copy = copyCatalog();
        editManifest($copy, 'workspaces', function (array $m) use ($step): array {
            $m['host_steps'] = ['user_model' => $step];

            return $m;
        });
        catalogRefuses(fn () => Blueprint::features(['core', 'workspaces'], repin($copy)), 'Invalid host step', 'a user_model host step with '.$label.' refuses');
    }
    $copy = copyCatalog();
    file_put_contents($copy.'/workspaces/stubs/.dotfile', 'skipped');
    mkdir($copy.'/workspaces/stubs/.cache');
    file_put_contents($copy.'/workspaces/stubs/.cache/x.php.stub', 'skipped');
    catalogCheck(array_keys(Blueprint::features(['core', 'workspaces'], repin($copy))->manifest['generated']) === array_keys($workspaces), 'dotfiles and dot directories in a tree are skipped');
    $copy = copyCatalog();
    editManifest($copy, 'workspaces', function (array $m): array {
        $m['files']['app/Workspaces/.hidden'] = ['stub' => 'stubs/.hidden', 'scaffold' => 'kit', 'ownership' => 'generated'];

        return $m;
    });
    $listedDot = Blueprint::features(['core', 'workspaces'], repin($copy));
    catalogCheck(isset($listedDot->manifest['generated']['app/Workspaces/.hidden']) && $listedDot->snapshot()['files']['app/Workspaces/.hidden'] === "skipped\n", 'a dotfile listed in files is included');

    // CatalogHost drives kit:update.
    $adapter = new Lifecycle;
    $root = sys_get_temp_dir().'/blueprint-catalog-host-'.bin2hex(random_bytes(8));
    foreach (['app', 'bootstrap/cache', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = $id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/executor-provenance.txt', json_encode($adapter->runtime()));
    file_put_contents($root.'/host-operations.txt', '<?php return function () use ($operation) { return $operation(); };');
    $root = realpath($root);
    $operation = fn () => true;
    $check = (require $root.'/host-operations.txt');
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $plain = new class($adapter, $paths, $check) implements LifecycleHost
    {
        public function __construct(private Lifecycle $adapter, private array $paths, private Closure $check) {}

        public function lifecycle(): Lifecycle
        {
            return $this->adapter;
        }

        public function appId(): string
        {
            return 'catalog-host';
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
    $owned = new class($adapter, $paths, $check, fixtureCatalog()) implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $adapter, private array $paths, private Closure $check, private Catalog $catalog) {}

        public function catalog(): Catalog
        {
            return $this->catalog;
        }

        public function lifecycle(): Lifecycle
        {
            return $this->adapter;
        }

        public function appId(): string
        {
            return 'catalog-host';
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
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $app->instance(LifecycleHost::class, $plain);
    catalogCheck($kernel->call('kit:update', ['action' => 'plan', '--features' => 'core,workspaces,mcp']) !== 0 && str_contains($kernel->output(), 'requires feature access'), 'a host without a catalog uses the package catalog, whose mcp requires access');
    catalogCheck($kernel->call('kit:update', ['action' => 'plan', '--features' => 'foundation,defaults']) !== 0 && str_contains($kernel->output(), 'Unknown feature: foundation'), 'a host without a catalog no longer plans the v0.1 fixture features');
    catalogCheck($kernel->call('kit:list', ['--json' => true]) === 0 && array_column(json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR), 'id') === $package->featureIds(), 'kit:list prints the package catalog');
    $app->instance(LifecycleHost::class, $owned);
    catalogCheck($kernel->call('kit:list', ['--json' => true]) === 0 && array_column(json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR), 'id') === Blueprint::featureIds(fixtureCatalog()), 'kit:list prints a catalog host\'s own features');
    $app->instance(LifecycleHost::class, $plain);
    $app->instance(LifecycleHost::class, $owned);
    catalogCheck($kernel->call('kit:update', ['action' => 'plan', '--features' => 'core,workspaces,notes']) === 0, 'a catalog host plans features from its own catalog');
    $plan = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    $expected = $adapter->plan($root, 'catalog-host', null, Blueprint::features(['core', 'workspaces', 'notes'], fixtureCatalog()), ['frontend' => 'SyntheticLayout'], $paths);
    catalogCheck($plan['plan'] === $expected->identity(), 'the command plan is the plan of the catalog host\'s blueprint');
    catalogCheck($kernel->call('kit:update', ['action' => 'plan', '--features' => 'foundation']) !== 0, 'a catalog host does not fall back to other features');
    catalogCheck($kernel->call('kit:update', ['action' => 'plan', '--features' => 'notes']) !== 0 && str_contains($kernel->output(), 'requires feature'), 'a catalog host refuses a missing requirement');
    catalogCheck($kernel->call('kit:update', ['action' => 'apply', '--features' => 'core,workspaces,notes', '--intent' => 'catalog', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && is_file($root.'/app/Workspaces/Concerns/BelongsToWorkspace.php') && is_file($root.'/app/Notes/Policies/NotePolicy.php'), 'a catalog host applies its tree features');
    catalogCheck(! is_file($root.'/app/Support/Starter/BlueprintPolicy.php'), 'no package feature is delivered by a catalog host');
    echo "Catalog checks only; no consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
