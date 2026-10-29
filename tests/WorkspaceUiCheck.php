<?php

// The workspace-ui split: session-ui keeps the sign-in, workspace-ui takes the workspace pages byte for byte, every selection with workspace-ui
// generates what session-ui did, and an app that installed session-ui before the split moves the pages over with kit:update, writing and
// deleting nothing. Synthetic host app and a pre-split copy of the package catalog rebuilt in a temp directory; no network, no consumer acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const PACKAGE = __DIR__.'/../resources/features';

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
        check(str_contains($error->getMessage(), $contains), $message);

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
/** Pins for a catalog copy, computed independently of Blueprint. */
function repin(string $root, array $ids): Catalog
{
    $pins = [];
    foreach ($ids as $id) {
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
/**
 * The package catalog as it was before the split: workspace-ui's manifest and stubs folded back into session-ui (its stubs at the same paths,
 * its owns and files added, its `moved` entries gone), and notes requiring session-ui again.
 */
function preSplit(): Catalog
{
    $root = sys_get_temp_dir().'/kit-pre-split-'.bin2hex(random_bytes(8));
    copyTree(PACKAGE, $root);
    $session = json_decode(file_get_contents($root.'/session-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    $workspace = json_decode(file_get_contents($root.'/workspace-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($workspace['files'] as $entry) {
        @mkdir(dirname($root.'/session-ui/'.$entry['stub']), 0700, true);
        rename($root.'/workspace-ui/'.$entry['stub'], $root.'/session-ui/'.$entry['stub']);
    }
    $session['owns'] = [...$session['owns'], ...$workspace['owns']];
    $session['files'] += $workspace['files'];
    $session['moved'] = array_values(array_filter($session['moved'], fn (array $entry): bool => $entry['to'] !== 'workspace-ui'));
    file_put_contents($root.'/session-ui/feature.json', json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $notes = json_decode(file_get_contents($root.'/notes/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    $notes['requires']['features'] = ['workspaces', 'access', 'session-ui'];
    file_put_contents($root.'/notes/feature.json', json_encode($notes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    exec('rm -rf '.escapeshellarg($root.'/workspace-ui'));

    return repin($root, ['core', 'workspaces', 'access', 'settings', 'shell', 'session-ui', 'mcp', 'notes']);
}
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-workspace-ui-'.bin2hex(random_bytes(8));
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
/** Every file outside Mod's metadata with its bytes' hash: a write or a deletion anywhere changes it. */
function tree(string $root): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (! str_starts_with($relative, '.mod-maintenance/')) {
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);

    return $files;
}

try {
    // The catalog: workspace-ui takes exactly the workspace pages, and session-ui lists each of them as moved there.
    $session = Blueprint::lineage('session-ui');
    $workspace = Blueprint::lineage('workspace-ui');
    $moved = array_filter(array_column($session['feature']['moved'], 'to', 'path'), fn (string $to): bool => $to === 'workspace-ui');
    $keys = function (array $map): array {
        $keys = array_keys($map);
        sort($keys);

        return $keys;
    };
    check(count($moved) === 22 && $keys($moved) === $keys($workspace['files']) && array_intersect_key($session['files'], $workspace['files']) === [], 'session-ui lists each of workspace-ui\'s 22 files as moved there, and generates none of them');
    $pages = fn (array $files, string $prefix): array => array_values(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, $prefix)));
    $authPages = array_map(fn (string $path): string => dirname(substr($path, strlen('resources/js/pages/'))), $pages($session['files'], 'resources/js/pages/Auth/'));
    sort($authPages);
    check($pages($session['files'], 'resources/js/pages/Workspace/') === [] && $pages($session['files'], 'resources/js/pages/Provision/') === [] && $authPages === ['Auth/ConfirmPassword', 'Auth/ForgotPassword', 'Auth/Invitation/Show', 'Auth/Login', 'Auth/ResetPassword', 'Auth/TwoFactor', 'Auth/VerifyEmail'] && count($pages($session['files'], 'resources/js/pages/Welcome/')) === 3, 'session-ui generates the seven Auth pages (ConfirmPassword, ForgotPassword, Invitation/Show, Login, ResetPassword, TwoFactor, VerifyEmail) and three welcome steps, and no Workspace or Provision page');
    check($workspace['feature']['requires']['features'] === ['workspaces', 'access', 'shell', 'session-ui'] && ! in_array('workspace-ui', $session['feature']['requires']['features'], true) && in_array('workspace-ui', Blueprint::lineage('notes')['feature']['requires']['features'], true), 'workspace-ui requires session-ui (not the other way round), and notes requires workspace-ui');
    check(Blueprint::features(['core', 'workspaces', 'access', 'shell', 'session-ui'])->selection() === ['core', 'workspaces', 'access', 'shell', 'session-ui'], 'an auth-only selection (session-ui without workspace-ui) validates');

    // Byte for byte: the whole catalog generates what it did before the split, and what the last release generated is still generated.
    $before = preSplit();
    $after = Blueprint::all()->manifest['generated'];
    $old = Blueprint::all($before)->manifest['generated'];
    ksort($after);
    ksort($old);
    check($after === $old, 'every selection with workspace-ui generates what session-ui did: the whole catalog\'s '.count($after).' files at the same paths and bytes');
    $all = [];
    foreach (Catalog::package()->featureIds() as $id) {
        $all += Blueprint::lineage($id)['files'];
    }
    $noFeature = [];
    foreach (['session-ui', 'notes'] as $id) {
        $noFeature += array_diff_key(Blueprint::lineage($id, $before)['files'], $all);
    }
    check($noFeature === [] && array_intersect_key(Blueprint::lineage('session-ui', $before)['files'], $workspace['files']) == $workspace['files'], 'nothing the pre-split session-ui generated is dropped: workspace-ui generates its pages at the same bytes');
    $history = json_decode(file_get_contents(PACKAGE.'/history.json'), true, flags: JSON_THROW_ON_ERROR)['releases'];
    // The last release before the split (the newest without workspace-ui): picking "the release before the current one" stopped meaning that once the split itself was released.
    $released = array_key_last(array_filter($history, fn (array $release): bool => ! isset($release['workspace-ui'])));
    // The split released workspace-ui at session-ui's bytes; workspace-ui's own releases may change its files after that (the dashboard's ActivityWidget did).
    $split = array_key_first(array_filter($history, fn (array $release): bool => isset($release['workspace-ui'])));
    $hashes = $history[$split]['workspace-ui'];
    check(count($hashes) === count($workspace['files']) && array_intersect_key($history[$released]['session-ui'], $hashes) == $hashes, 'workspace-ui\'s files were the bytes session-ui generated in '.$released.' when '.$split.' split them out');

    // An app that installed session-ui before the split.
    $lifecycle = new Lifecycle;
    $old = new Lineages($lifecycle, $before);
    $new = new Lineages($lifecycle);
    $pass = fn () => true;
    $install = function () use ($old, $pass, $loader): array {
        [$root, $paths] = hostApp();
        $loader->addPsr4('App\\', $root.'/app', true);
        Application::configure($root)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
        $preview = $old->preview($root, 'host', ['session-ui'], [], $paths);
        check($preview['executable'], 'the pre-split session-ui installs on the host app');
        $result = $old->add($root, 'host', ['session-ui'], [], $paths, $preview['plan'], null, bound($root, $pass));
        check($result['status'] === 'completed', 'installed: '.implode(', ', array_keys($result['features'])));

        return [$root, $paths];
    };
    $step = function (string $root, array $paths, string $id, string $intent) use ($new, $pass): array {
        $plan = $new->updatePlan($root, 'host', $id, [], $paths);
        check($plan->executable(), $id.' plans an executable step ('.$intent.')');

        return [$plan, $new->updateApply($plan, $id, $paths, $intent, bound($root, $pass))];
    };
    $carried = fn (string $root): bool => in_array('workspace-ui', FeaturesServiceProvider::selected(FeaturesServiceProvider::carried($root, 'host', FeaturesServiceProvider::adopted($root, 'host', null))), true);
    $listed = fn (string $root): bool => in_array('workspace-ui', FeaturesServiceProvider::selected(FeaturesServiceProvider::carried($root, 'host', ['core', 'workspaces', 'access', 'shell', 'session-ui'])), true);

    [$root, $paths] = $install();
    $files = tree($root);
    $installed = array_keys(Checkout::installed($root, 'host'));
    check($carried($root) && $listed($root), 'after a composer update, before any kit:update, the pre-split app keeps its workspace pages on: workspace-ui is carried for its lineages and for a kit.features list without it');
    check(! $new->preview($root, 'host', ['workspace-ui'], [], $paths)['executable'], 'adding workspace-ui beside the installed session-ui is not executable: session-ui still owns the pages');

    [$plan, $result] = $step($root, $paths, 'session-ui', 'split-release');
    check(array_values(array_unique($plan->removals)) === ['release'] && $keys($plan->removals) === $keys($moved) && $result['status'] === 'completed' && str_contains((string) $result['next'], '--lineage=workspace-ui'), 'step 1: kit:update --lineage=session-ui releases the 22 pages and names --lineage=workspace-ui next');
    check(tree($root) === $files && array_intersect_key(Checkout::installed($root, 'host')['tey/kit:session-ui']['files'], $moved) === [], 'the released pages stay on disk, unchanged; session-ui no longer owns them');
    check($carried($root) && $listed($root), 'between the steps the released pages stay on: workspace-ui is still carried');
    [$plan, $result] = $step($root, $paths, 'workspace-ui', 'split-claim');
    check($keys(array_flip($plan->claim)) === $keys($moved) && $result['status'] === 'completed' && $result['written'] === [] && tree($root) === $files, 'step 2: kit:update --lineage=workspace-ui claims the pages at the released bytes and writes nothing');
    [$plan, $result] = $step($root, $paths, 'workspace-ui', 'split-target');
    check($result['status'] === 'completed' && $result['next'] === null && tree($root) === $files, 'workspace-ui then updates to its target with nothing to write: its bytes are the released ones');
    [$plan, $result] = $step($root, $paths, 'session-ui', 'split-finish');
    check($result['status'] === 'completed' && $result['next'] === null && tree($root) === $files, 'step 3: kit:update --lineage=session-ui finishes');
    $lineages = array_keys(Checkout::installed($root, 'host'));
    check(array_values(array_diff($lineages, $installed)) === ['tey/kit:workspace-ui'] && array_diff($installed, $lineages) === [], 'the app now has workspace-ui beside the lineages it had');
    foreach (['session-ui', 'workspace-ui'] as $id) {
        check(Checkout::installed($root, 'host')['tey/kit:'.$id]['identity'] === $new->artifact($id, [])->identity(), $id.' is at its target');
    }
    check(tree($root) === $files && Checkout::pending($root, 'host') === [], 'nothing the app had was written or deleted, and no update is left unfinished');
    check($carried($root) && $listed($root), 'afterwards workspace-ui is on, by its own lineage (and for a kit.features list that does not name it yet)');

    // A moved page the app has edited stops the first step, with the diff.
    [$root, $paths] = $install();
    $dashboard = 'resources/js/pages/Workspace/Dashboard/Page.vue';
    file_put_contents($root.'/'.$dashboard, file_get_contents($root.'/'.$dashboard)."<!-- the host's change -->\n");
    $files = tree($root);
    refuses(fn () => $new->updatePlan($root, 'host', 'session-ui', [], $paths), "+ <!-- the host's change -->", 'an edited moved page stops session-ui\'s update and shows the diff from the installed file');
    refuses(fn () => $new->updatePlan($root, 'host', 'session-ui', [], $paths), $dashboard, 'the refusal names the edited file');
    check(tree($root) === $files && array_keys(Checkout::installed($root, 'host')) === $installed, 'the refusal writes nothing and keeps the installed lineages');

    // A fresh app: session-ui alone is the sign-in; workspace-ui brings session-ui with it.
    [$fresh, $freshPaths] = hostApp();
    $loader->addPsr4('App\\', $fresh.'/app', true);
    Application::configure($fresh)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $preview = $new->preview($fresh, 'host', ['session-ui'], [], $freshPaths);
    check($preview['executable'] && ! in_array('workspace-ui', array_column($preview['features'], 'feature'), true) && array_filter(array_merge(...array_map(fn (array $row): array => array_keys($row['files']), $preview['features'])), fn (string $path): bool => str_starts_with($path, 'resources/js/pages/Workspace/Dashboard')) === [], 'a fresh app adds session-ui alone, with no workspace page');
    $preview = $new->preview($fresh, 'host', ['workspace-ui'], [], $freshPaths);
    check($preview['executable'] && array_slice(array_column($preview['features'], 'feature'), -2) === ['session-ui', 'workspace-ui'], 'adding workspace-ui adds session-ui before it');
    $new->add($fresh, 'host', ['session-ui'], [], $freshPaths, $new->preview($fresh, 'host', ['session-ui'], [], $freshPaths)['plan'], null, bound($fresh, $pass));
    check(! $carried($fresh) && ! $listed($fresh), 'a session-ui installed after the split is auth-only: workspace-ui is not carried');
    echo "Workspace-ui split checks on a synthetic host app with the package catalog and a pre-split copy of it; no consumer acceptance or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
