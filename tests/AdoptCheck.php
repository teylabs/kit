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
/**
 * Pins for an edited catalog copy, computed independently of Blueprint.
 *
 * @param  list<string>|null  $ids  the copy's features in canonical order, when it adds one
 */
function repin(string $root, ?array $ids = null): Catalog
{
    $pins = [];
    foreach ($ids ?? array_keys(ADOPT_PINS) as $id) {
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

    // Claims of identical pre-existing files come only from an explicit, reviewed list.
    [$root, $paths] = hostApp();
    $marker = $kit->artifact('core', $bindings)->files['app/Support/Core/CoreMarker.php'];
    mkdir($root.'/app/Support/Core', 0700, true);
    file_put_contents($root.'/app/Support/Core/CoreMarker.php', $marker);
    touch($root.'/app/Support/Core/CoreMarker.php', 1000000000);
    $before = tree($root);
    $unlisted = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check($unlisted['features'][0]['files']['app/Support/Core/CoreMarker.php']['state'] === 'unowned' && ! $unlisted['executable'] && $unlisted['claimable'] === ['app/Support/Core/CoreMarker.php'], 'an unlisted identical pre-existing file is a claimable candidate, left unowned');
    refuses(fn () => $kit->add($root, 'host', ['core'], $bindings, $paths, $unlisted['plan'], null, bound($root, $pass)), '--claim', 'apply refuses until every identical file is claimed, and names --claim');
    check(tree($root) === $before && adopted($root, 'host') === [], 'the unclaimed refusal writes nothing');
    refuses(fn () => $kit->preview($root, 'host', ['core'], $bindings, $paths, ['app/Elsewhere.php']), 'Claims must name', 'a claim no feature delivers is refused');
    $preview = $kit->preview($root, 'host', ['core'], $bindings, $paths, ['app/Support/Core/CoreMarker.php']);
    check($preview['features'][0]['files']['app/Support/Core/CoreMarker.php']['state'] === 'claim' && $preview['executable'] && $preview['claim'] === ['app/Support/Core/CoreMarker.php'], 'a listed identical file plans as a claim');
    check($preview['plan'] !== $unlisted['plan'], 'the claim list is part of the plan identity');
    refuses(fn () => $kit->add($root, 'host', ['core'], $bindings, $paths, $preview['plan'], null, bound($root, $pass)), 'expected plan', 'a reviewed claim plan does not apply without its claim list');
    $result = $kit->add($root, 'host', ['core'], $bindings, $paths, $preview['plan'], null, bound($root, $pass), ['app/Support/Core/CoreMarker.php']);
    clearstatcache();
    check($result['status'] === 'completed' && filemtime($root.'/app/Support/Core/CoreMarker.php') === 1000000000 && isset(Checkout::installed($root, 'host')['tey/kit:core']['files']['app/Support/Core/CoreMarker.php']), 'the claimed file is owned without a write');

    // Refusal of differing pre-existing files, with no write.
    [$root, $paths] = hostApp();
    mkdir($root.'/app/Workspaces', 0700, true);
    file_put_contents($root.'/app/Workspaces/Workspace.php', '<?php // the host\'s own workspace');
    $before = tree($root);
    $preview = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    check(! $preview['executable'] && $preview['features'][1]['files']['app/Workspaces/Workspace.php']['state'] === 'unowned' && $preview['claimable'] === [], 'a differing unowned pre-existing file is refused, and is not claimable');
    refuses(fn () => $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $preview['plan'], null, bound($root, $pass)), 'not executable', 'the unowned plan refuses to apply');
    check(tree($root) === $before && adopted($root, 'host') === [], 'the unowned refusal writes nothing, core included');
    $listed = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths, ['app/Workspaces/Workspace.php']);
    check(! $listed['executable'] && $listed['features'][1]['files']['app/Workspaces/Workspace.php']['state'] === 'conflict', 'claiming a differing file leaves it a conflict');

    // An unowned pre-existing file with differing bytes: Mod refuses it at apply time, with no write.
    [$root, $paths] = hostApp();
    $kit->add($root, 'host', ['core'], $bindings, $paths, $kit->preview($root, 'host', ['core'], $bindings, $paths)['plan'], null, bound($root, $pass));
    $preview = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    mkdir($root.'/app/Workspaces', 0700, true);
    file_put_contents($root.'/app/Workspaces/Workspace.php', '<?php // appeared after review');
    $before = tree($root);
    refuses(fn () => $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $preview['plan'], null, bound($root, $pass)), 'expected plan', 'a differing unowned file that appears after review refuses the apply');
    $fresh = $kit->preview($root, 'host', ['workspaces'], $bindings, $paths);
    refuses(fn () => $kit->add($root, 'host', ['workspaces'], $bindings, $paths, $fresh['plan'], null, bound($root, $pass)), 'not executable', 'a fresh plan over the differing unowned file refuses');
    check(tree($root) === $before && adopted($root, 'host') === ['core'], 'the unowned differing refusal writes nothing');

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
    $status = $lifecycle->status($root);
    check($status['blueprint_features'] === ['access', 'core', 'settings', 'workspaces'] && array_column($status['lineages'], 'name') === ['tey/kit:access', 'tey/kit:core', 'tey/kit:settings', 'tey/kit:workspaces'], 'kit:status lists one lineage per adopted feature');
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
    $hint = implode("\n", Lineages::pendingHint($root, 'Another lineage has an unfinished update (pending:tey/kit:core).'));
    check(str_contains($hint, 'Pending lineage: tey/kit:core') && str_contains($hint, 'Intent: pilot:core') && str_contains($hint, 'kit:add core --resume --intent=pilot'), 'the pending hint names the lineage, its intent and the resume command');
    check(Lineages::pendingHint($root, 'Something else.') === null, 'other refusals get no pending hint');
    refuses(fn () => $kit->resume($root, 'host', ['access'], $bindings, $paths, 'other', $host), 'No unfinished adoption of core', 'resume refuses an intent with no pending record');
    $passes = true;
    $resumed = $kit->resume($root, 'host', ['access'], $bindings, $paths, 'pilot', $host);
    check($resumed['status'] === 'completed' && array_keys($resumed['features']) === ['core', 'workspaces', 'access'] && adopted($root, 'host') === ['core', 'workspaces', 'access'], 'resume finishes the failed lineage, then every remaining feature in requires order');
    check($resumed['done'] === ['core', 'workspaces', 'access'] && $resumed['remaining'] === [], 'resume reports every feature done');
    refuses(fn () => $kit->resume($root, 'host', ['access'], $bindings, $paths, 'pilot', $host), 'nothing to resume', 'resuming a finished request refuses');

    // A resume that stops again reports what is done and what remains.
    [$root, $paths] = hostApp();
    $calls = 0;
    $host = bound($root, function () use (&$calls): bool {
        return ++$calls !== 1 && $calls !== 3;
    });
    $preview = $kit->preview($root, 'host', ['access'], $bindings, $paths);
    $kit->add($root, 'host', ['access'], $bindings, $paths, $preview['plan'], 'twice', $host);
    $resumed = $kit->resume($root, 'host', ['access'], $bindings, $paths, 'twice', $host);
    check($resumed['status'] === 'verification_failed' && $resumed['failed'] === 'workspaces' && $resumed['done'] === ['core'] && $resumed['remaining'] === ['workspaces', 'access'], 'a resume that stops lists the done and remaining features');
    $resumed = $kit->resume($root, 'host', ['access'], $bindings, $paths, 'twice', $host);
    check($resumed['status'] === 'completed' && adopted($root, 'host') === ['core', 'workspaces', 'access'], 'resuming again finishes the rest');

    // Upgrade to a new catalog version: the original is rebuilt from the installed record, not the old catalog.
    $next = sys_get_temp_dir().'/kit-adopt-next-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $next);
    file_put_contents($next.'/workspaces/stubs/Workspace.php.stub', file_get_contents($next.'/workspaces/stubs/Workspace.php.stub')."// catalog N+1\n");
    file_put_contents($next.'/blueprint.json', str_replace('"version": "0.1.0"', '"version": "0.1.1"', file_get_contents($next.'/blueprint.json')));
    $upgraded = new Lineages($lifecycle, repin($next));
    $install = function () use ($kit, $bindings, $pass): array {
        [$root, $paths] = hostApp();
        $preview = $kit->preview($root, 'host', ['workspaces', 'notes'], $bindings, $paths);
        $kit->add($root, 'host', ['workspaces', 'notes'], $bindings, $paths, $preview['plan'], null, bound($root, $pass));

        return [$root, $paths];
    };
    [$root, $paths] = $install();
    $others = [];
    foreach (glob($root.'/.mod-maintenance/installed/*.json') as $path) {
        if (basename($path) !== hash('sha256', 'tey/kit:workspaces').'.json') {
            $others[$path] = file_get_contents($path);
        }
    }
    $plan = $upgraded->updatePlan($root, 'host', 'workspaces', $bindings, $paths);
    check($plan->executable() && $plan->files['app/Workspaces/Workspace.php']['state'] === 'replace' && $plan->files['app/Workspaces/Concerns/BelongsToWorkspace.php']['state'] === 'unchanged', 'catalog N+1 plans a replace of the changed stub only, with no old catalog');
    $result = $upgraded->updateApply($plan, 'workspaces', $paths, 'upgrade-workspaces', bound($root, $pass));
    check($result['status'] === 'completed' && str_ends_with(file_get_contents($root.'/app/Workspaces/Workspace.php'), "// catalog N+1\n") && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.1', 'the upgrade applies and records the new version');
    check(array_map('file_get_contents', array_keys($others)) === array_values($others), 'other lineages are untouched by the upgrade');
    check($upgraded->updatePlan($root, 'host', 'notes', $bindings, $paths)->executable(), 'notes still upgrades on its own afterwards');
    [$root, $paths] = $install();
    file_put_contents($root.'/app/Workspaces/Workspace.php', '<?php // edited by the host');
    $plan = $upgraded->updatePlan($root, 'host', 'workspaces', $bindings, $paths);
    check(! $plan->executable() && $plan->files['app/Workspaces/Workspace.php']['state'] === 'conflict', 'an edited file in the upgraded feature is a conflict');
    refuses(fn () => $upgraded->updateApply($plan, 'workspaces', $paths, 'upgrade-edited', bound($root, $pass)), 'Conflicted', 'the conflicting upgrade refuses');
    check(file_get_contents($root.'/app/Workspaces/Workspace.php') === '<?php // edited by the host' && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.0', 'the refusal keeps the edit and the installed version');
    [$root, $paths] = $install();
    $passes = false;
    $host = bound($root, function () use (&$passes): bool {
        return $passes;
    });
    $plan = $upgraded->updatePlan($root, 'host', 'workspaces', $bindings, $paths);
    check($upgraded->updateApply($plan, 'workspaces', $paths, 'upgrade-retry', $host)['status'] === 'verification_failed', 'a failed check leaves the upgrade unfinished');
    $passes = true;
    check($upgraded->updateResume($root, 'host', 'workspaces', $bindings, $paths, 'upgrade-retry', $host)['status'] === 'completed' && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.1', 'resume finishes the upgrade from the installed record');
    check($upgraded->updateResume($root, 'host', 'workspaces', $bindings, $paths, 'upgrade-retry', $host)['no_op'], 'resuming a finished upgrade is a no-op');
    refuses(fn () => $upgraded->updatePlan($root, 'host', 'workspaces', $bindings, $paths, catalog()), 'exactly the installed artifact', 'an explicit original catalog must rebuild the installed artifact');

    // A file that moves to a new feature (session-ui's banner into shell, as the package's shell split does). Its `moved` entry makes
    // the update a release on session-ui, a claim on shell at the released bytes, shell's own update, then session-ui's finish.
    $split = sys_get_temp_dir().'/kit-adopt-split-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $split);
    mkdir($split.'/shell');
    rename($split.'/session-ui/session-banner.php.stub', $split.'/shell/session-banner.php.stub');
    // Shell's target differs from what session-ui installed, as the package's shell stubs do.
    file_put_contents($split.'/shell/session-banner.php.stub', file_get_contents($split.'/shell/session-banner.php.stub')."// shell's banner\n");
    file_put_contents($split.'/session-ui/session-page.php.stub', "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }} {}\n");
    $files = fn (string $path, string $stub): array => [$path => ['stub' => $stub, 'scaffold' => 'kit', 'ownership' => 'generated']];
    file_put_contents($split.'/shell/feature.json', json_encode(['format' => 1, 'id' => 'shell', 'owns' => ['app/Session/SessionBanner.php'], 'files' => $files('app/Session/SessionBanner.php', 'session-banner.php.stub'), 'requires' => ['features' => ['core'], 'packages' => (object) []], 'conflicts' => [], 'host_owned' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $manifest = json_decode(file_get_contents($split.'/session-ui/feature.json'), true);
    $splitManifest = ['owns' => ['app/SessionUi'], 'files' => $files('app/SessionUi/SessionPage.php', 'session-page.php.stub'), 'requires' => ['features' => ['workspaces', 'shell'], 'packages' => (object) []]] + $manifest;
    $splitIds = ['core', 'workspaces', 'access', 'settings', 'shell', 'session-ui', 'mcp', 'notes'];
    file_put_contents($split.'/session-ui/feature.json', json_encode($splitManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $bare = $split.'-unlisted';
    copyTree($split, $bare);
    $unlisted = new Lineages($lifecycle, repin($bare, $splitIds));
    file_put_contents($split.'/session-ui/feature.json', json_encode($splitManifest + ['moved' => [['path' => 'app/Session/SessionBanner.php', 'to' => 'shell']]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $splitKit = new Lineages($lifecycle, repin($split, $splitIds));
    $banner = 'app/Session/SessionBanner.php';
    $stamp = function (string $root) use ($banner): array {
        clearstatcache();
        $stat = stat($root.'/'.$banner);

        return [hash_file('sha256', $root.'/'.$banner), $stat['ino'], $stat['mtime'], $stat['ctime']];
    };
    $installSession = function () use ($kit, $bindings, $pass): array {
        [$root, $paths] = hostApp();
        $preview = $kit->preview($root, 'host', ['session-ui'], $bindings, $paths);
        $kit->add($root, 'host', ['session-ui'], $bindings, $paths, $preview['plan'], null, bound($root, $pass));

        return [$root, $paths];
    };
    $step = function (string $root, array $paths, string $id, string $intent) use ($splitKit, $bindings, $pass): array {
        $plan = $splitKit->updatePlan($root, 'host', $id, $bindings, $paths);
        check($plan->executable(), $id.' plans an executable step ('.$intent.')');

        return [$plan, $splitKit->updateApply($plan, $id, $paths, $intent, bound($root, $pass))];
    };

    [$root, $paths] = $installSession();
    $before = tree($root);
    refuses(fn () => $unlisted->updatePlan($root, 'host', 'session-ui', $bindings, $paths), "drops app/Session/SessionBanner.php with no `moved` or `removed` entry", 'an update that drops a file neither `moved` nor `removed` lists is refused');
    check(! $splitKit->preview($root, 'host', ['shell'], $bindings, $paths)['executable'], 'adding shell beside the installed session-ui is not executable: session-ui still owns the file');
    check(tree($root) === $before && adopted($root, 'host') === ['core', 'workspaces', 'session-ui'], 'the refusals write nothing and keep the installed lineages');

    $original = $stamp($root);
    [$plan, $result] = $step($root, $paths, 'session-ui', 'split-release');
    check($plan->removals === [$banner => 'release'] && $plan->files[$banner]['state'] === 'release' && $result['status'] === 'completed' && str_contains($result['next'], '--lineage=shell'), 'step 1: session-ui releases the banner and names the next run');
    check($stamp($root) === $original && ! isset(Checkout::installed($root, 'host')['tey/kit:session-ui']['files'][$banner]) && ! isset(Checkout::installed($root, 'host')['tey/kit:session-ui']['artifact']['requires']['tey/kit:shell']), 'the released banner is untouched, session-ui no longer owns it and does not yet require shell');
    [$plan, $result] = $step($root, $paths, 'shell', 'split-claim');
    check($plan->claim === [$banner] && $result['status'] === 'completed' && $result['written'] === [] && $stamp($root) === $original, 'step 2: shell claims the banner at the released bytes with no write');
    check(Checkout::installed($root, 'host')['tey/kit:shell']['version'] === Checkout::installed($root, 'host')['tey/kit:core']['version'], 'shell is installed at the version session-ui released from');
    [$plan, $result] = $step($root, $paths, 'shell', 'split-shell');
    check($result['status'] === 'completed' && $result['next'] === null && str_ends_with(file_get_contents($root.'/'.$banner), "// shell's banner\n"), 'shell then updates the unedited banner to its own target');
    [$plan, $result] = $step($root, $paths, 'session-ui', 'split-finish');
    check($result['status'] === 'completed' && $result['next'] === null, 'step 3: session-ui finishes, now that shell is installed');
    $lineages = array_keys(Checkout::installed($root, 'host'));
    sort($lineages);
    check($lineages === ['tey/kit:core', 'tey/kit:session-ui', 'tey/kit:shell', 'tey/kit:workspaces'], 'after the move the host has core, workspaces, shell and session-ui');
    foreach (['core', 'workspaces', 'shell', 'session-ui'] as $id) {
        check(Checkout::installed($root, 'host')['tey/kit:'.$id]['identity'] === $splitKit->artifact($id, $bindings)->identity(), $id.' is at its target after the move');
        $plan = $splitKit->updatePlan($root, 'host', $id, $bindings, $paths);
        check($plan->executable() && array_filter($plan->files, fn (array $file): bool => $file['state'] !== 'unchanged') === [], $id.' has nothing left to change');
    }
    check(Checkout::pending($root, 'host') === [], 'no update is left unfinished');

    [$root, $paths] = $installSession();
    file_put_contents($root.'/'.$banner, file_get_contents($root.'/'.$banner)."// the host's change\n");
    $before = tree($root);
    refuses(fn () => $splitKit->updatePlan($root, 'host', 'session-ui', $bindings, $paths), "+ // the host's change", 'an edited moved file stops the move, showing the diff from the installed file');
    refuses(fn () => $splitKit->updatePlan($root, 'host', 'session-ui', $bindings, $paths), 'restore the installed bytes', 'the refusal gives the manual step');
    check(tree($root) === $before && adopted($root, 'host') === ['core', 'workspaces', 'session-ui'], 'the edited move writes nothing and keeps the installed lineages');

    // A step whose checks fail stays unfinished and resumes with the same removals.
    [$root, $paths] = $installSession();
    $passes = false;
    $flaky = bound($root, function () use (&$passes): bool {
        return $passes;
    });
    $plan = $splitKit->updatePlan($root, 'host', 'session-ui', $bindings, $paths);
    check($splitKit->updateApply($plan, 'session-ui', $paths, 'split-retry', $flaky)['status'] === 'verification_failed' && is_file($root.'/'.$banner), 'a failed check leaves the release unfinished and the banner in place');
    $passes = true;
    $result = $splitKit->updateResume($root, 'host', 'session-ui', $bindings, $paths, 'split-retry', $flaky);
    check($result['status'] === 'completed' && str_contains($result['next'], '--lineage=shell') && ! isset(Checkout::installed($root, 'host')['tey/kit:session-ui']['files'][$banner]), 'resume finishes the release and names the next run');

    [$fresh, $freshPaths] = hostApp();
    $preview = $splitKit->preview($fresh, 'host', ['session-ui'], $bindings, $freshPaths);
    check($preview['executable'] && array_column($preview['features'], 'feature') === ['core', 'workspaces', 'shell', 'session-ui'], 'a fresh host adopts the split session-ui with shell before it');

    // The package catalog adopts onto the same host app; host-owned files stay the host's. Its User has the workspaces host step.
    [$root, $paths] = hostApp();
    $loader->addPsr4('App\\', $root.'/app', true);
    Application::configure($root)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $package = new Lineages($lifecycle);
    $before = tree($root);
    $preview = $package->preview($root, 'host', ['mcp'], [], $paths);
    check($preview['executable'] && array_column($preview['features'], 'feature') === ['core', 'workspaces', 'access', 'mcp'] && in_array('php artisan migrate', $preview['host_steps'], true), 'the package catalog plans mcp with its requirements and host steps');
    // A host config naming a different tenant model than concierge.models.workspace is a blocking workspace_model step.
    is_dir($root.'/config') || mkdir($root.'/config');
    file_put_contents($root.'/config/multitenancy.php', "<?php\n\nreturn ['tenant_model' => 'App\\\\Models\\\\OtherWorkspace'];\n");
    config()->set('multitenancy.tenant_model', 'App\\Models\\OtherWorkspace');
    $mismatched = $package->preview($root, 'host', ['mcp'], [], $paths);
    check(! $mismatched['executable'] && count(array_filter($mismatched['unmet'], fn (string $step): bool => str_contains($step, 'multitenancy.tenant_model (App\\Models\\OtherWorkspace) must name the same workspace model'))) === 1, 'a mismatched workspace binding is refused at plan time');
    unlink($root.'/config/multitenancy.php');
    config()->set('multitenancy.tenant_model', null);
    check($package->preview($root, 'host', ['mcp'], [], $paths)['plan'] === $preview['plan'], 'with the binding restored the plan is the reviewed one again');
    $result = $package->add($root, 'host', ['mcp'], [], $paths, $preview['plan'], null, bound($root, $pass));
    check($result['status'] === 'completed' && array_intersect_key(tree($root), $before) === $before, 'the package catalog adopts mcp and leaves host files untouched');

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
    [$claimRoot, $claimPaths] = hostApp();
    $claimApp = Application::configure($claimRoot)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $consoleHost = get_class($app->make(LifecycleHost::class));
    $claimApp->instance(LifecycleHost::class, new $consoleHost($lifecycle, $claimPaths, bound($claimRoot, $pass)));
    $claimKernel = $claimApp->make(Kernel::class);
    $claimKernel->bootstrap();
    $workspace = $kit->artifact('workspaces', $bindings)->files;
    mkdir($claimRoot.'/app/Workspaces/Concerns', 0700, true);
    foreach (['app/Workspaces/Workspace.php', 'app/Workspaces/Concerns/BelongsToWorkspace.php'] as $path) {
        file_put_contents($claimRoot.'/'.$path, $workspace[$path]);
    }
    check($claimKernel->call('kit:add', ['features' => ['workspaces']]) !== 0 && str_contains($claimKernel->output(), 'claimable app/Workspaces/Workspace.php'), 'kit:add previews identical existing files as claimable and exits nonzero');
    $comma = $claimKernel->call('kit:add', ['features' => ['workspaces'], '--json' => true, '--claim' => ['app/Workspaces/Workspace.php,app/Workspaces/Concerns/BelongsToWorkspace.php']]);
    $commaPlan = json_decode(trim($claimKernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'];
    $repeated = $claimKernel->call('kit:add', ['features' => ['workspaces'], '--json' => true, '--claim' => ['app/Workspaces/Concerns/BelongsToWorkspace.php', 'app/Workspaces/Workspace.php']]);
    check($comma === 0 && $repeated === 0 && json_decode(trim($claimKernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'] === $commaPlan, '--claim takes a comma list or repeats, to the same plan');
    $applied = $claimKernel->call('kit:add', ['features' => ['workspaces'], '--apply' => true, '--expected-plan' => $commaPlan, '--claim' => ['app/Workspaces/Workspace.php,app/Workspaces/Concerns/BelongsToWorkspace.php']]);
    $claimOut = $claimKernel->output();
    check($applied === 0 && str_contains($claimOut, 'done: core, workspaces'), 'kit:add --apply --claim adopts the reviewed claims');
    check($kernel->call('kit:add', ['features' => ['notes'], '--resume' => true]) !== 0 && str_contains($kernel->output(), 'needs the --intent'), 'kit:add --resume without --intent exits nonzero with a clear refusal');
    file_put_contents($root.'/app/Notes/Note.php', '<?php // edited');
    check($kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'notes']) !== 0 && $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']) === 0, 'kit:update --lineage plans per lineage');
    $refused = $kernel->call('kit:update', ['action' => 'plan']);
    $refusal = $kernel->output();
    check($refused !== 0 && str_contains($refusal, 'kit:update plan --lineage=core') && str_contains($refusal, '--lineage=notes'), 'kit:update without --lineage on an adopted app refuses the release path and names each lineage');
    check($kernel->call('kit:update', ['action' => 'plan', '--target' => 'E']) !== 0 && glob($root.'/app/Support/Starter/*') === [], 'kit:update --target on an adopted app refuses and writes nothing');

    // A failed kit:update apply rolls back: no delivered file and no journal stay behind, and a fresh apply then completes.
    [$root, $paths] = $install();
    $verdict = false;
    $rollbackApp = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $rollbackApp->instance(LifecycleHost::class, new class($lifecycle, $paths, bound($root, function () use (&$verdict): bool { return $verdict; }), repin($next)) implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths, private Closure $check, private Catalog $catalog) {}

        public function catalog(): Catalog
        {
            return $this->catalog;
        }

        public function lifecycle(): Lifecycle
        {
            return $this->lifecycle;
        }

        public function appId(): string
        {
            return 'host';
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
    $rollbackKernel = $rollbackApp->make(Kernel::class);
    $rollbackKernel->bootstrap();
    $before = tree($root);
    $rollbackKernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json_decode(trim($rollbackKernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'];
    $failed = $rollbackKernel->call('kit:update', ['action' => 'apply', '--lineage' => 'workspaces', '--intent' => 'rollback', '--exclusive-checkout' => true, '--expected-plan' => $plan]);
    $report = json_decode(trim($rollbackKernel->output()), true, flags: JSON_THROW_ON_ERROR);
    check($failed !== 0 && $report['status'] === 'verification_failed' && $report['rolled_back'] === ['app/Workspaces/Workspace.php'], 'a failed kit:update apply reports the files it rolled back');
    check(tree($root) === $before && ! is_file($root.'/.mod-maintenance/journal-'.hash('sha256', 'rollback').'.json') && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.0', 'the rollback leaves the checkout, journals and installed record as they were');
    $verdict = true;
    $rollbackKernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    check(json_decode(trim($rollbackKernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'] === $plan && $rollbackKernel->call('kit:update', ['action' => 'apply', '--lineage' => 'workspaces', '--intent' => 'rollback', '--exclusive-checkout' => true, '--expected-plan' => $plan]) === 0 && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.1', 'after a rollback the same plan applies fresh');
    echo "Adoption checks on a synthetic host app only; no package boot, migrations, HTTP or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
