<?php

// The workspace-ui split: session-ui keeps the sign-in, workspace-ui takes the workspace pages byte for byte (0.6.0 moved the invitation page and the
// workspace-timezone step too), every selection with workspace-ui generates what session-ui did, and with both features the `shell` prop has one producer.
// Synthetic host app and a pre-split copy of the package catalog rebuilt in a temp directory; no network, no consumer acceptance.
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
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Shell\ShellSource;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider;

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
// workspace-ui's files added after the split (transfer ownership, the deletions and the dashboard's setup card): never session-ui's, so neither its moved list nor the pre-split copy has them.
const ADDED_SINCE_SPLIT = ['resources/js/pages/Workspace/Account/Delete/Page.vue', 'resources/js/pages/Workspace/Dashboard/Partials/SetupWidget.vue', 'resources/js/pages/Workspace/Settings/Delete/Page.vue', 'resources/js/pages/Workspace/Settings/People/Transfer/Page.vue'];

// The invitation page and the workspace-timezone welcome step moved from session-ui to workspace-ui in 0.6.0, after the first split.
const MOVED_IN_060 = ['resources/js/pages/Auth/Invitation/Show/Page.vue', 'resources/js/pages/Welcome/WorkspaceTimezone/Page.vue'];

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
    foreach (['bootstrap/cache', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }

    return [realpath($root)];
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
    // Lineages added after the split (finalize) generate only their own files, outside this comparison.
    $after = array_filter(Blueprint::all()->manifest['generated'], fn (string $path): bool => ! str_starts_with($path, 'database/migrations/kit/finalize/') && ! str_starts_with($path, 'database/migrations/kit/deliver/'), ARRAY_FILTER_USE_KEY);
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
    // 0.6.0's two pages: the bytes the last session-ui that had them generated (the newest release with them in session-ui), and workspace-ui's in the release
    // that took them over. Its later releases may change them (the field accessibility fix dropped a hard-coded aria-describedby), as with the split above.
    $last060 = array_key_last(array_filter($history, fn (array $release): bool => isset($release['session-ui'][MOVED_IN_060[0]])));
    $moved060 = array_key_first(array_filter($history, fn (array $release): bool => isset($release['workspace-ui'][MOVED_IN_060[0]])));
    check(array_intersect_key($history[$moved060]['workspace-ui'], array_flip(MOVED_IN_060)) == array_intersect_key($history[$last060]['session-ui'], array_flip(MOVED_IN_060)) && array_intersect_key($history['current']['session-ui'], array_flip(MOVED_IN_060)) === [], 'workspace-ui generates the invitation page and the workspace-timezone step in '.$moved060.' at the bytes session-ui generated in '.$last060.', and session-ui no longer generates them');

    // The one producer of the shared `shell` prop with both features: one ShellSource resolution per page, and it is session-ui's PageShell.
    [$both] = hostApp();
    $loader->addPsr4('App\\', $both.'/app', true);
    [$bothApp] = (require __DIR__.'/fixtures/session-sign-in.php')($both, ['core', 'workspaces', 'access', 'shell', 'session-ui', 'workspace-ui'], KitHostUser::class, WORKSPACE_PROVIDERS, workspaceTables(), 'check', 'both features: ');
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

    echo "Workspace-ui split checks on a synthetic host app with the package catalog and a pre-split copy of it; no consumer acceptance or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
