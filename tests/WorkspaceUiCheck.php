<?php

// The workspace-ui split: session-ui keeps the sign-in, workspace-ui takes the workspace pages byte for byte, every selection with workspace-ui
// generates what session-ui did, and an app that installed session-ui before the split moves the pages over with kit:update, writing and
// deleting nothing. 0.6.0 moved the invitation page and the workspace-timezone step too: an app with both features re-pins once, writing and deleting nothing. Synthetic host app and a pre-split copy of the package catalog rebuilt in a temp directory; no network, no consumer acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Pennant\PennantServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Shell\ShellSource;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider;
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
/** A host's user on a workspace app (the 0.5.x session-ui required workspaces): a Concierge member. */
class KitHostUser extends User implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}
/** The providers and tables of a workspace app (Concierge, spatie permission, multitenancy, settings, Pennant), for the sign-in fixture. */
const WORKSPACE_PROVIDERS = [PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, PennantServiceProvider::class, ConciergeServiceProvider::class];
function workspaceTables(): Closure
{
    return function (): void {
        foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
            (require $file)->up();
        }
    };
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
 * its owns and files added, its `moved` entries gone), session-ui requiring workspaces and access again, and notes requiring session-ui again.
 */
// workspace-ui's pages added after the split (transfer ownership and the deletions): never session-ui's, so neither its moved list nor the pre-split copy has them.
const ADDED_SINCE_SPLIT = ['resources/js/pages/Workspace/Account/Delete/Page.vue', 'resources/js/pages/Workspace/Settings/Delete/Page.vue', 'resources/js/pages/Workspace/Settings/People/Transfer/Page.vue'];

// The invitation page and the workspace-timezone welcome step moved from session-ui to workspace-ui in 0.6.0, after the first split.
const MOVED_IN_060 = ['resources/js/pages/Auth/Invitation/Show/Page.vue', 'resources/js/pages/Welcome/WorkspaceTimezone/Page.vue'];

/**
 * The package catalog as 0.5.x shipped it, after the first split and before 0.6.0: the invitation page and the workspace-timezone step folded back into
 * session-ui (their stubs at the same paths, their owns and files restored, their `moved` entries gone), and session-ui requiring workspaces and access again.
 */
function pre060(): Catalog
{
    $root = sys_get_temp_dir().'/kit-pre-060-'.bin2hex(random_bytes(8));
    copyTree(PACKAGE, $root);
    $session = json_decode(file_get_contents($root.'/session-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    $workspace = json_decode(file_get_contents($root.'/workspace-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (MOVED_IN_060 as $path) {
        $entry = $workspace['files'][$path];
        @mkdir(dirname($root.'/session-ui/'.$entry['stub']), 0700, true);
        rename($root.'/workspace-ui/'.$entry['stub'], $root.'/session-ui/'.$entry['stub']);
        unset($workspace['files'][$path]);
        $session['files'][$path] = $entry;
        $owned = dirname($path) === 'resources/js/pages/Auth/Invitation/Show' ? 'resources/js/pages/Auth/Invitation' : dirname($path);
        $workspace['owns'] = array_values(array_diff($workspace['owns'], [$owned]));
        $session['owns'][] = $owned;
    }
    $session['moved'] = array_values(array_filter($session['moved'], fn (array $entry): bool => ! in_array($entry['path'], MOVED_IN_060, true)));
    $session['requires']['features'] = ['workspaces', 'access', 'shell'];
    file_put_contents($root.'/session-ui/feature.json', json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents($root.'/workspace-ui/feature.json', json_encode($workspace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    return repin($root, Catalog::package()->featureIds());
}

function preSplit(): Catalog
{
    $root = sys_get_temp_dir().'/kit-pre-split-'.bin2hex(random_bytes(8));
    copyTree(PACKAGE, $root);
    $session = json_decode(file_get_contents($root.'/session-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    $workspace = json_decode(file_get_contents($root.'/workspace-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (ADDED_SINCE_SPLIT as $path) {
        unlink($root.'/workspace-ui/'.$workspace['files'][$path]['stub']);
        unset($workspace['files'][$path]);
    }
    foreach ($workspace['files'] as $entry) {
        @mkdir(dirname($root.'/session-ui/'.$entry['stub']), 0700, true);
        rename($root.'/workspace-ui/'.$entry['stub'], $root.'/session-ui/'.$entry['stub']);
    }
    $session['owns'] = [...$session['owns'], ...$workspace['owns']];
    $session['files'] += $workspace['files'];
    $session['moved'] = array_values(array_filter($session['moved'], fn (array $entry): bool => $entry['to'] !== 'workspace-ui'));
    $session['requires']['features'] = ['workspaces', 'access', 'shell'];
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
    $added = ADDED_SINCE_SPLIT;
    check(count($moved) === 24 && $keys($moved) === array_values(array_diff($keys($workspace['files']), $added)) && count($workspace['files']) === 24 + count($added) && array_intersect_key($session['files'], $workspace['files']) === [] && array_values(array_intersect($keys($moved), MOVED_IN_060)) === MOVED_IN_060, 'session-ui lists each of the 24 files it moved to workspace-ui (22 in the first split, the invitation page and the workspace-timezone step in 0.6.0), generates none of workspace-ui\'s files, and workspace-ui\'s only others are the pages added since');
    $pages = fn (array $files, string $prefix): array => array_values(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, $prefix)));
    $authPages = array_map(fn (string $path): string => dirname(substr($path, strlen('resources/js/pages/'))), $pages($session['files'], 'resources/js/pages/Auth/'));
    sort($authPages);
    $welcome = $pages($session['files'], 'resources/js/pages/Welcome/');
    sort($welcome);
    check($pages($session['files'], 'resources/js/pages/Workspace/') === [] && $pages($session['files'], 'resources/js/pages/Provision/') === [] && $authPages === ['Auth/ConfirmPassword', 'Auth/ForgotPassword', 'Auth/Login', 'Auth/Register', 'Auth/ResetPassword', 'Auth/TwoFactor', 'Auth/VerifyEmail'] && $welcome === ['resources/js/pages/Welcome/Photo/Page.vue', 'resources/js/pages/Welcome/Timezone/Page.vue'], 'session-ui generates the seven Auth pages (ConfirmPassword, ForgotPassword, Login, Register, ResetPassword, TwoFactor, VerifyEmail) and the Photo and Timezone welcome steps, and no Workspace, Provision or invitation page');
    $workspacePages = array_map(fn (string $path): string => dirname(substr($path, strlen('resources/js/pages/'))), [...$pages($workspace['files'], 'resources/js/pages/Auth/'), ...$pages($workspace['files'], 'resources/js/pages/Welcome/')]);
    sort($workspacePages);
    check($workspacePages === ['Auth/Invitation/Show', 'Welcome/WorkspaceTimezone'] && in_array('resources/js/pages/Auth/Invitation', $workspace['feature']['owns'], true) && in_array('resources/js/pages/Welcome/WorkspaceTimezone', $workspace['feature']['owns'], true), 'workspace-ui generates and owns the invitation page (Auth/Invitation/Show) and the workspace-timezone step (Welcome/WorkspaceTimezone)');
    check($workspace['feature']['requires']['features'] === ['workspaces', 'access', 'shell', 'session-ui'] && $session['feature']['requires']['features'] === ['core', 'shell'] && in_array('workspace-ui', Blueprint::lineage('notes')['feature']['requires']['features'], true), 'session-ui requires only core and shell; workspace-ui requires workspaces, access, shell and session-ui; notes requires workspace-ui');
    check(Blueprint::features(['core', 'workspaces', 'access', 'shell', 'session-ui'])->selection() === ['core', 'workspaces', 'access', 'shell', 'session-ui'] && Blueprint::features(['core', 'shell', 'session-ui'])->selection() === ['core', 'shell', 'session-ui'], 'an auth-only selection validates, with workspaces or with only core and shell');

    // Byte for byte: the whole catalog generates what it did before the split, and what the last release generated is still generated.
    $before = preSplit();
    $after = Blueprint::all()->manifest['generated'];
    $old = Blueprint::all($before)->manifest['generated'];
    ksort($after);
    ksort($old);
    check(array_diff_key($after, array_flip(ADDED_SINCE_SPLIT)) === $old && count($after) === count($old) + count(ADDED_SINCE_SPLIT), 'every selection with workspace-ui generates what session-ui did: the whole catalog\'s '.count($old).' files at the same paths and bytes, plus the pages added since');
    $all = [];
    foreach (Catalog::package()->featureIds() as $id) {
        $all += Blueprint::lineage($id)['files'];
    }
    $noFeature = [];
    foreach (['session-ui', 'notes'] as $id) {
        $noFeature += array_diff_key(Blueprint::lineage($id, $before)['files'], $all);
    }
    check($noFeature === [] && array_intersect_key(Blueprint::lineage('session-ui', $before)['files'], $workspace['files']) == array_diff_key($workspace['files'], array_flip(ADDED_SINCE_SPLIT)), 'nothing the pre-split session-ui generated is dropped: workspace-ui generates its pages at the same bytes');
    $history = json_decode(file_get_contents(PACKAGE.'/history.json'), true, flags: JSON_THROW_ON_ERROR)['releases'];
    // The last release before the split (the newest without workspace-ui): picking "the release before the current one" stopped meaning that once the split itself was released.
    $released = array_key_last(array_filter($history, fn (array $release): bool => ! isset($release['workspace-ui'])));
    // The split released workspace-ui at session-ui's bytes; workspace-ui's own releases may change its files after that (the dashboard's ActivityWidget did).
    $split = array_key_first(array_filter($history, fn (array $release): bool => isset($release['workspace-ui'])));
    $hashes = $history[$split]['workspace-ui'];
    check(count($hashes) === count($workspace['files']) - count($added) - count(MOVED_IN_060) && array_intersect_key($history[$released]['session-ui'], $hashes) == $hashes, 'workspace-ui\'s first-split files were the bytes session-ui generated in '.$released.' when '.$split.' split them out');
    // 0.6.0's two pages: the bytes the last session-ui that had them generated (the newest release with them in session-ui), and workspace-ui's now.
    $last060 = array_key_last(array_filter($history, fn (array $release): bool => isset($release['session-ui'][MOVED_IN_060[0]])));
    check(array_intersect_key($history['current']['workspace-ui'], array_flip(MOVED_IN_060)) == array_intersect_key($history[$last060]['session-ui'], array_flip(MOVED_IN_060)) && array_intersect_key($history['current']['session-ui'], array_flip(MOVED_IN_060)) === [], 'workspace-ui generates the invitation page and the workspace-timezone step at the bytes session-ui generated in '.$last060.', and session-ui no longer generates them');

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
    check(array_values(array_unique($plan->removals)) === ['release'] && $keys($plan->removals) === $keys($moved) && $result['status'] === 'completed' && str_contains((string) $result['next'], '--lineage=workspace-ui'), 'step 1: kit:update --lineage=session-ui releases the 24 pages and names --lineage=workspace-ui next');
    check(tree($root) === $files && array_intersect_key(Checkout::installed($root, 'host')['tey/kit:session-ui']['files'], $moved) === [], 'the released pages stay on disk, unchanged; session-ui no longer owns them');
    check($carried($root) && $listed($root), 'between the steps the released pages stay on: workspace-ui is still carried');
    [$plan, $result] = $step($root, $paths, 'workspace-ui', 'split-claim');
    check($keys(array_flip($plan->claim)) === $keys($moved) && $result['status'] === 'completed' && $result['written'] === [] && tree($root) === $files, 'step 2: kit:update --lineage=workspace-ui claims the pages at the released bytes and writes nothing');
    [$plan, $result] = $step($root, $paths, 'workspace-ui', 'split-target');
    // The target adds the pages added since the split. The pre-split copy is rebuilt from today's stubs, so the pages it moved are already at the target's bytes.
    $updated = tree($root);
    $differs = array_keys(array_diff_assoc(array_intersect_key($updated, $files), $files));
    check($result['status'] === 'completed' && $result['next'] === null && $keys(array_diff_key($updated, $files)) === ADDED_SINCE_SPLIT && array_diff_key($files, $updated) === [] && $differs === [], 'workspace-ui then updates to its target: it writes only the pages added since the split, and changes nothing the app had');
    $files = $updated;
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

    // 0.6.0: an app with session-ui and workspace-ui both installed from 0.5.x re-pins once. session-ui's update shows the move (it releases the
    // invitation page and the workspace-timezone step to workspace-ui), workspace-ui's update claims them, and nothing on disk is written or deleted.
    $catalog060 = pre060();
    $old060 = new Lineages($lifecycle, $catalog060);
    [$both, $bothPaths] = hostApp();
    $loader->addPsr4('App\\', $both.'/app', true);
    Application::configure($both)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $preview = $old060->preview($both, 'host', ['workspace-ui'], [], $bothPaths);
    check($preview['executable'], 'the 0.5.x session-ui and workspace-ui install on the host app');
    $result = $old060->add($both, 'host', ['workspace-ui'], [], $bothPaths, $preview['plan'], null, bound($both, $pass));
    $before060 = Checkout::installed($both, 'host');
    check($result['status'] === 'completed' && array_intersect(MOVED_IN_060, array_keys($before060['tey/kit:session-ui']['files'])) === MOVED_IN_060, 'installed with session-ui owning the invitation page and the workspace-timezone step: '.implode(', ', array_keys($result['features'])));
    $files = tree($both);
    $plan = $new->updatePlan($both, 'host', 'session-ui', [], $bothPaths);
    $removals = $plan->removals;
    ksort($removals);
    check($plan->executable() && $removals === array_fill_keys(MOVED_IN_060, 'release'), 'kit:update plan --lineage=session-ui shows the move: it releases the invitation page and the workspace-timezone step, and deletes nothing');
    $result = $new->updateApply($plan, 'session-ui', $bothPaths, 'move-060-release', bound($both, $pass));
    check($result['status'] === 'completed' && $result['next'] === null && tree($both) === $files && array_intersect_key(Checkout::installed($both, 'host')['tey/kit:session-ui']['files'], array_flip(MOVED_IN_060)) === [], 'session-ui updates in one run (workspace-ui is installed): the two files stay on disk, unchanged, and session-ui no longer owns them');
    [$plan, $result] = $step($both, $bothPaths, 'workspace-ui', 'move-060-claim');
    $claimed = $plan->claim;
    sort($claimed);
    check(array_values(array_intersect($claimed, MOVED_IN_060)) === MOVED_IN_060 && $plan->removals === [] && $result['status'] === 'completed' && $result['written'] === [] && tree($both) === $files, 'kit:update --lineage=workspace-ui claims the two files at their bytes and writes nothing');
    $after060 = Checkout::installed($both, 'host');
    check(array_intersect(MOVED_IN_060, array_keys($after060['tey/kit:workspace-ui']['files'])) === MOVED_IN_060 && array_keys($after060) === array_keys($before060), 'workspace-ui now owns them; the app has the same lineages as before');
    foreach (['session-ui', 'workspace-ui'] as $id) {
        check($after060['tey/kit:'.$id]['identity'] === $new->artifact($id, [])->identity(), $id.' is at its 0.6.0 target');
    }
    check(tree($both) === $files && Checkout::pending($both, 'host') === [], 'with both installed the re-pin deletes and writes nothing: the files changed owner, not disk');

    // The one producer of the shared `shell` prop with both features: one ShellSource resolution per page, and it is session-ui's PageShell.
    [$bothApp] = (require __DIR__.'/fixtures/session-sign-in.php')($both, KitHostUser::class, WORKSPACE_PROVIDERS, workspaceTables(), 'check', 'both features: ');
    $resolved = 0;
    $bothApp->resolving(ShellSource::class, function () use (&$resolved): void {
        $resolved++;
    });
    Auth::guard('web')->logout();
    $request = Request::create('https://kit.test/login', 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $request->setLaravelSession($bothApp['session']->driver());
    $bothApp->instance('request', $request);
    $page = json_decode((string) $bothApp['router']->dispatch($request)->getContent(), true);
    $perPage = $resolved;
    check($bothApp->getProvider(WorkspaceUiProvider::class) !== null && $perPage === 1 && $bothApp->make(ShellSource::class) instanceof PageShell && ($page['props']['shell']['kind'] ?? null) === 'guest', 'with shell, session-ui and workspace-ui the `shell` prop is produced once per page (one ShellSource resolution, '.$perPage.'), by session-ui\'s PageShell');

    // Claim without write only on an exact match: one byte of a moved file differs, and workspace-ui's plan shows a conflict and writes nothing.
    [$edited, $editedPaths] = hostApp();
    $loader->addPsr4('App\\', $edited.'/app', true);
    Application::configure($edited)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $preview = $old060->preview($edited, 'host', ['workspace-ui'], [], $editedPaths);
    $old060->add($edited, 'host', ['workspace-ui'], [], $editedPaths, $preview['plan'], null, bound($edited, $pass));
    $plan = $new->updatePlan($edited, 'host', 'session-ui', [], $editedPaths);
    $new->updateApply($plan, 'session-ui', $editedPaths, 'move-060-release', bound($edited, $pass));
    $invitation = $edited.'/'.MOVED_IN_060[0];
    $bytes = file_get_contents($invitation);
    file_put_contents($invitation, substr($bytes, 0, -1).($bytes[-1] === "\n" ? ' ' : "\n"));
    $files = tree($edited);
    $plan = $new->updatePlan($edited, 'host', 'workspace-ui', [], $editedPaths);
    check(! in_array(MOVED_IN_060[0], $plan->claim, true) && in_array(MOVED_IN_060[1], $plan->claim, true) && ! $plan->executable() && ($plan->files[MOVED_IN_060[0]]['state'] ?? null) === 'unowned', 'a moved file one byte off is not claimed: workspace-ui\'s plan shows it ('.($plan->files[MOVED_IN_060[0]]['state'] ?? 'absent').') and is not executable until reviewed; the unedited one is still claimed');
    refuses(fn () => $new->updateApply($plan, 'workspace-ui', $editedPaths, 'move-060-claim', bound($edited, $pass)), '', 'applying that plan is refused (any refusal)');
    check(tree($edited) === $files, 'and nothing is written');

    // A 0.5.x session-ui installed alone (with workspaces and access) is not carried into workspace-ui by the pages 0.6.0 moved: it stays the sign-in.
    [$alone, $alonePaths] = hostApp();
    $loader->addPsr4('App\\', $alone.'/app', true);
    Application::configure($alone)->withExceptions(fn ($e) => null)->create()->make(Kernel::class)->bootstrap();
    $preview = $old060->preview($alone, 'host', ['session-ui'], [], $alonePaths);
    $old060->add($alone, 'host', ['session-ui'], [], $alonePaths, $preview['plan'], null, bound($alone, $pass));
    check(isset(Checkout::installed($alone, 'host')['tey/kit:session-ui']['files'][MOVED_IN_060[0]]) && ! $carried($alone) && ! $listed($alone), 'a 0.5.x session-ui alone that owns the invitation page is not carried into workspace-ui');
    $files = tree($alone);
    $plan = $new->updatePlan($alone, 'host', 'session-ui', [], $alonePaths);
    $removals = $plan->removals;
    ksort($removals);
    $kept = $new->keptOnUpdate($alone, 'host', 'session-ui', []);
    check($plan->executable() && $removals === array_fill_keys(MOVED_IN_060, 'release') && array_column(array_intersect_key($plan->files, array_flip(MOVED_IN_060)), 'state') === ['release', 'release'] && $kept['files'] === array_fill_keys(MOVED_IN_060, 'kept as the host\'s'), 'session-ui alone: kit:update plan --lineage=session-ui is executable, and reports each moved file as kept as the host\'s (released, never deleted or rewritten)');
    check(str_contains((string) $kept['note'], 'no longer owned by session-ui; kept as the host\'s') && str_contains((string) $kept['note'], 'only if this app uses') && str_contains((string) $kept['note'], 'invitations') && ! str_contains((string) $kept['note'], '--lineage=workspace-ui') && ! str_contains((string) $kept['note'], 'kit:add'), 'its note says each file is no longer owned by session-ui and kept as the host\'s, that workspace-ui is needed only for invitations, and names no --lineage=workspace-ui or kit:add');
    $result = $new->updateApply($plan, 'session-ui', $alonePaths, 'move-060-alone', bound($alone, $pass));
    check($result['status'] === 'completed' && $result['next'] === $kept['note'] && ! str_contains(json_encode($result), '--lineage=workspace-ui'), 'the apply completes, and its result names no --lineage=workspace-ui step');
    check(tree($alone) === $files && array_intersect_key(Checkout::installed($alone, 'host')['tey/kit:session-ui']['files'], array_flip(MOVED_IN_060)) === [] && ! isset(Checkout::installed($alone, 'host')['tey/kit:workspace-ui']) && Checkout::pending($alone, 'host') === [], 'the two files are byte-identical to before, session-ui\'s installed record no longer lists them, and no workspace-ui is installed or pending');
    check(Checkout::installed($alone, 'host')['tey/kit:session-ui']['identity'] === $new->artifact('session-ui', [])->identity() && ! $carried($alone), 'session-ui is at its 0.6.0 target in one run, and workspace-ui is not carried');
    (require __DIR__.'/fixtures/session-sign-in.php')($alone, KitHostUser::class, WORKSPACE_PROVIDERS, workspaceTables(), 'check', 'session-ui alone, after the update: ');
    echo "Workspace-ui split checks on a synthetic host app with the package catalog, a pre-split copy and a 0.5.x copy of it; no consumer acceptance or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
