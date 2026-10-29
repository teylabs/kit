<?php

// kit:adopt on a pre-lifecycle host: synthetic catalogs and a clean-room host app, no network, journal or consumer acceptance.
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

const MIGRATION = 'database/migrations/kit/core/0001_01_02_000100_create_core_tables.php';
const HELPER = 'app/Support/Core/CoreHelper.php';
const MARKER = 'app/Support/Core/CoreMarker.php';
const WORKSPACE = 'app/Workspaces/Workspace.php';
const CONCERN = 'app/Workspaces/Concerns/BelongsToWorkspace.php';

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
/** Pins for a catalog copy, computed independently of Blueprint. */
function repin(string $root): Catalog
{
    $pins = [];
    foreach (['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes'] as $id) {
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
/** A catalog release: the fixture catalog, core with a migration and a helper, then each stub change; history.json lists earlier releases. */
function release(string $version, array $stubs, array $history = []): Catalog
{
    $root = sys_get_temp_dir().'/kit-adopt-in-place-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    $core = json_decode(file_get_contents($root.'/core/feature.json'), true);
    $core['owns'][] = 'database/migrations/kit/core';
    $core['files'][MIGRATION] = ['stub' => 'migration.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
    $core['files'][HELPER] = ['stub' => 'core-helper.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
    file_put_contents($root.'/core/feature.json', json_encode($core, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($root.'/core/migration.php.stub', "<?php\n\n// audit_events.workspace_id: foreign key\nreturn new class {};\n");
    file_put_contents($root.'/core/core-helper.php.stub', "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }} {}\n");
    foreach ($stubs as $stub => $bytes) {
        @mkdir(dirname($root.'/'.$stub), 0700, true);
        file_put_contents($root.'/'.$stub, $bytes);
    }
    file_put_contents($root.'/blueprint.json', str_replace('"version": "0.1.0"', '"version": "'.$version.'"', file_get_contents($root.'/blueprint.json')));
    if ($history !== []) {
        file_put_contents($root.'/history.json', json_encode(['format' => 1, 'releases' => $history], JSON_UNESCAPED_SLASHES));
    }

    return repin($root);
}
/** A release's file hashes per feature, as bin/catalog-history.php records them. */
function hashes(Catalog $catalog): array
{
    $lineages = new Lineages(new Lifecycle, $catalog);
    $files = [];
    foreach (['core', 'workspaces'] as $id) {
        $files[$id] = array_map(fn (string $bytes): string => hash('sha256', $bytes), $lineages->artifact($id, ['frontend' => 'SyntheticLayout'])->files);
    }

    return $files;
}
/** Every file in the checkout outside Mod's metadata, with its bytes' hash. */
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
function json(Kernel $kernel): array
{
    $lines = array_values(array_filter(explode("\n", trim($kernel->output()))));

    return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
}

try {
    $lifecycle = new Lifecycle;
    $bindings = ['frontend' => 'SyntheticLayout'];
    // v-old: what the app installed by hand, before lineages. v-new changes the migration and Workspace.php.
    $old = release('0.0.9', []);
    $new = release('0.1.0', ['core/migration.php.stub' => "<?php\n\n// audit_events.workspace_id: indexed unsignedBigInteger\nreturn new class {};\n", 'workspaces/stubs/Workspace.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Workspace.php.stub')."// v-new\n"], ['v-old' => hashes($old)]);

    // A clean-room pre-lifecycle host: v-old's files copied in, no journal or record; one file edited, one missing.
    $root = sys_get_temp_dir().'/kit-adopt-in-place-host-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/host-app', $root);
    $root = realpath($root);
    foreach (['bootstrap/cache', 'storage/logs', 'verification'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode($lifecycle->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () use (&$verdict) { return $verdict; };');
    foreach (['core', 'workspaces'] as $id) {
        foreach ((new Lineages($lifecycle, $old))->artifact($id, $bindings)->files as $path => $bytes) {
            @mkdir(dirname($root.'/'.$path), 0700, true);
            file_put_contents($root.'/'.$path, $bytes);
        }
    }
    unlink($root.'/'.HELPER);
    $edit = file_get_contents($root.'/'.CONCERN)."// the app's own edit\n";
    file_put_contents($root.'/'.CONCERN, $edit);
    check(! is_dir($root.'/.mod-maintenance'), 'the pre-lifecycle host has no journal or installed record');

    $verdict = true;
    $check = require $root.'/verification/host-operations.txt';
    $host = new class($lifecycle, $paths, $check, $new) implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths, private Closure $check, public Catalog $catalog) {}

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
    };
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $app->instance(LifecycleHost::class, $host);
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $before = tree($root);

    // kit:update cannot run here, and says what to run instead.
    check($kernel->call('kit:update', ['action' => 'plan']) !== 0 && str_contains($out = $kernel->output(), 'installed before lineages') && str_contains($out, 'php artisan kit:adopt core plan') && str_contains($out, 'php artisan kit:adopt workspaces plan'), 'kit:update on a pre-lifecycle app refuses and prints the kit:adopt commands in order');
    check($kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']) !== 0 && str_contains($kernel->output(), 'kit:adopt core plan'), 'kit:update --lineage on a feature not adopted but on disk prints kit:adopt');
    check($kernel->call('kit:adopt', ['feature' => 'workspaces', 'action' => 'plan']) !== 0 && str_contains($kernel->output(), 'requires feature core'), 'a feature adopts only after its requirements');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan', '--from' => 'v-unknown']) !== 0 && str_contains($kernel->output(), 'known: v-old, current'), 'an unknown --from release is refused, naming the known ones');
    check(tree($root) === $before && ! is_dir($root.'/.mod-maintenance/installed'), 'the refusals write nothing');

    // Adopt core: identical files are claimed, the missing helper created; a failed check rolls the creation back.
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) === 0, 'kit:adopt core plans as executable');
    $adoptOutput = $kernel->output();
    $adoptLines = array_values(array_filter(explode("\n", trim($adoptOutput))));
    $plan = json_decode(end($adoptLines), true, flags: JSON_THROW_ON_ERROR);
    check($plan['from'] === 'v-old' && $plan['files'] == [MARKER => 'identical', MIGRATION => 'behind', HELPER => 'missing'] && $plan['kept'] === [], 'core matches v-old: the unchanged marker is identical, the migration this release changed is behind, the helper is missing');
    check($plan['behind'] === [MIGRATION => 'v-old'] && str_contains($adoptOutput, 'UPDATE: '.MIGRATION.' matches release v-old') && str_contains($adoptOutput, 'kit:update plan --lineage=core'), 'the behind migration is reported as an update, naming its release and the kit:update that follows');
    check(tree($root) === $before, 'the adoption plan writes nothing');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'apply', '--intent' => 'adopt-core', '--exclusive-checkout' => true, '--expected-plan' => 'sha256:stale']) !== 0 && tree($root) === $before, 'a stale expected plan is refused, with no write');
    $verdict = false;
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'apply', '--intent' => 'adopt-core', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) !== 0 && json($kernel)['rolled_back'] === [HELPER], 'a failed check rolls the adoption back');
    check(tree($root) === $before && Checkout::installed($root, 'host') === [], 'the rollback leaves the app as it was, unadopted');
    // A crash mid-adopt (an apply outside the command's rollback) leaves its journal and created file behind.
    $stopped = (new Lineages($lifecycle, $new))->adoptApply($plan2 = (new Lineages($lifecycle, $new))->adoptPlan($root, 'host', 'core', $bindings, $paths)['plan'], 'core', $paths, 'crashed', $check);
    check($stopped['status'] !== 'completed' && is_file($root.'/'.HELPER) && is_file($root.'/.mod-maintenance/journal-'.hash('sha256', 'crashed').'.json'), 'a crashed adoption leaves its journal and created file');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) !== 0 && str_contains($out = $kernel->output(), 'pending:tey/kit:core') && str_contains($out, 'php artisan kit:adopt core rollback --intent=crashed --exclusive-checkout'), 'every plan refuses, and the hint names the kit:adopt rollback');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'rollback', '--intent' => 'other', '--exclusive-checkout' => true]) !== 0 && str_contains($kernel->output(), 'No unfinished adoption'), 'rollback refuses an intent with no adopt journal');
    file_put_contents($root.'/'.HELPER, '<?php // changed after the crash');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'rollback', '--intent' => 'crashed', '--exclusive-checkout' => true]) !== 0 && is_file($root.'/'.HELPER) && is_file($root.'/.mod-maintenance/journal-'.hash('sha256', 'crashed').'.json'), 'rollback fails closed when a created file changed since, removing nothing');
    file_put_contents($root.'/'.HELPER, $plan2->target->files[HELPER]);
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'rollback', '--intent' => 'crashed', '--exclusive-checkout' => true]) === 0 && json($kernel)['removed'] === [HELPER], 'rollback removes the created file');
    check(tree($root) === $before && glob($root.'/.mod-maintenance/journal-*.json') === [] && Checkout::installed($root, 'host') === [], 'after the rollback the app is as it was, with no journal or record');
    $verdict = true;
    // The app lists its features in config/kit.php (an explicit list overrides the lineages): adopting a feature must switch it on there, as kit:add does.
    @mkdir($root.'/config', 0700, true);
    file_put_contents($root.'/config/kit.php', "<?php\n\nreturn [\n    'features' => [\n    ],\n    'app_id' => null,\n];\n");
    config(['kit.features' => []]);
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) === 0 && json($kernel)['plan'] === $plan['plan'], 'the same adoption plans again');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'apply', '--intent' => 'adopt-core', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && json($kernel)['next'] === 'php artisan kit:update plan --lineage=core', 'kit:adopt core applies and names the next step');
    check((require $root.'/config/kit.php')['features'] === ['core'] && config('kit.features') === ['core'], 'kit:adopt core adds core to the app\'s kit.features list, so the feature is on');
    check(array_diff_key(tree($root), [HELPER => true, 'config/kit.php' => true]) === $before && is_file($root.'/'.HELPER), 'adopting core wrote only the missing helper, and the features list');

    // Adopt workspaces: the edited file is kept by the app.
    check($kernel->call('kit:adopt', ['feature' => 'workspaces', 'action' => 'plan']) === 0, 'kit:adopt workspaces plans as executable');
    $plan = json($kernel);
    check($plan['files'] == [WORKSPACE => 'behind', CONCERN => 'edited'] && $plan['behind'] === [WORKSPACE => 'v-old'] && $plan['kept'] === [CONCERN => hashes($old)['workspaces'][CONCERN]], 'Workspace.php, which this release changed, is behind v-old; the edited file is kept against the v-old bytes it was edited from');
    $tree = tree($root);
    check($kernel->call('kit:adopt', ['feature' => 'workspaces', 'action' => 'apply', '--intent' => 'adopt-workspaces', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && array_diff_key(tree($root), ['config/kit.php' => true]) === array_diff_key($tree, ['config/kit.php' => true]), 'kit:adopt workspaces applies with no write');
    check((require $root.'/config/kit.php')['features'] === ['core', 'workspaces'] && config('kit.features') === ['core', 'workspaces'] && str_contains(file_get_contents($root.'/config/kit.php'), "    'features' => [\n        'core',\n        'workspaces',\n    ],"), 'kit:adopt workspaces adds it too, in canonical order, keeping the list\'s multi-line layout');
    $installed = Checkout::installed($root, 'host');
    check(array_keys($installed) === ['tey/kit:core', 'tey/kit:workspaces'] && ! isset($installed['tey/kit:workspaces']['files'][CONCERN]), 'each feature is its own lineage, and the edited file is not owned');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) !== 0 && str_contains($kernel->output(), 'already adopted'), 're-adopting refuses and points at kit:update');

    // kit:update to v-new: the changed migration is flagged and applies only once reviewed.
    check($kernel->call('kit:update', ['action' => 'plan']) !== 0 && str_contains($kernel->output(), '--lineage=core'), 'kit:update now takes the per-lineage path');
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']);
    $plan = json($kernel);
    check($plan['executable'] && $plan['files'][MIGRATION]['state'] === 'replace' && $plan['changed_migrations'] === [MIGRATION] && str_contains($plan['host_steps'][0], 'add a new migration'), 'the core update flags the changed migration and recommends a new one');
    $tree = tree($root);
    check($kernel->call('kit:update', ['action' => 'apply', '--lineage' => 'core', '--intent' => 'update-core', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) !== 0 && str_contains($kernel->output(), '--accept-changed-migrations') && tree($root) === $tree, 'the changed migration is never rewritten without review');
    check($kernel->call('kit:update', ['action' => 'apply', '--lineage' => 'core', '--intent' => 'update-core', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan'], '--accept-changed-migrations' => true]) === 0 && str_contains(file_get_contents($root.'/'.MIGRATION), 'indexed unsignedBigInteger'), 'the reviewed core update syncs the changed migration');
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json($kernel);
    check($plan['executable'] && $plan['files'][WORKSPACE]['state'] === 'replace' && ! isset($plan['files'][CONCERN]) && ! isset($plan['changed_migrations']), 'the workspaces update replaces the changed file and leaves the kept one out');
    check($kernel->call('kit:update', ['action' => 'apply', '--lineage' => 'workspaces', '--intent' => 'update-workspaces', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && str_ends_with(file_get_contents($root.'/'.WORKSPACE), "// v-new\n") && file_get_contents($root.'/'.CONCERN) === $edit, 'the workspaces update applies without overwriting the edit');

    // A follow-up update that does not touch the edited file: still kept, still the app's.
    $host->catalog = release('0.1.1', ['core/migration.php.stub' => "<?php\n\n// audit_events.workspace_id: indexed unsignedBigInteger\nreturn new class {};\n", 'workspaces/stubs/Workspace.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Workspace.php.stub')."// v-new\n", 'core/core-marker.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/core/core-marker.php.stub')."// follow-up\n"], ['v-old' => hashes($old), 'v-new' => hashes($new)]);
    foreach (['core', 'workspaces'] as $id) {
        $kernel->call('kit:update', ['action' => 'plan', '--lineage' => $id]);
        $plan = json($kernel);
        check($plan['executable'] && ! isset($plan['changed_migrations']) && $kernel->call('kit:update', ['action' => 'apply', '--lineage' => $id, '--intent' => 'follow-up-'.$id, '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0, 'the follow-up '.$id.' update applies');
    }
    check(str_ends_with(file_get_contents($root.'/'.MARKER), "// follow-up\n") && file_get_contents($root.'/'.CONCERN) === $edit && Checkout::installed($root, 'host')['tey/kit:workspaces']['version'] === '0.1.1', 'the follow-up update keeps the edit');

    // A release that changes the edited file: a conflict, exactly as for an edited owned file; matching it reconciles.
    $changed = file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Concerns/BelongsToWorkspace.php.stub')."// upstream\n";
    $host->catalog = release('0.1.2', ['core/migration.php.stub' => "<?php\n\n// audit_events.workspace_id: indexed unsignedBigInteger\nreturn new class {};\n", 'workspaces/stubs/Workspace.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Workspace.php.stub')."// v-new\n", 'workspaces/stubs/Concerns/BelongsToWorkspace.php.stub' => $changed]);
    check($kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']) !== 0, 'an upstream change to the edited file does not plan as executable');
    check(json($kernel)['files'][CONCERN]['state'] === 'conflict' && file_get_contents($root.'/'.CONCERN) === $edit, 'it is a conflict, and the edit stays');
    // The way out without merging (an app's own Layout.vue kept since adoption): keep it. The plan names what it was; nothing is applied here.
    $tree = tree($root);
    check($kernel->call('kit:update', ['action' => 'keep', 'path' => CONCERN]) === 0 && ($keep = json($kernel))['was'] === 'kept' && $keep['feature'] === 'workspaces' && $keep['executable'] && tree($root) === $tree, 'kit:update keep plans a file kept since adoption as host-kept, writing nothing');
    // Kept, the upstream change that landed before the keep is reported on the very next plan. The lineage records are restored afterwards (test only), so
    // the merge below runs on the file kept since adoption, as before.
    $records = sys_get_temp_dir().'/kit-adopt-in-place-records-'.bin2hex(random_bytes(6));
    copyTree($root.'/.mod-maintenance', $records);
    check($kernel->call('kit:update', ['action' => 'keep', 'path' => CONCERN, '--lineage' => 'workspaces', '--expected-plan' => $keep['plan'], '--exclusive-checkout' => true, '--intent' => 'keep-concern']) === 0 && tree($root) === $tree, 'keep applies to the file kept since adoption');
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json($kernel);
    check($plan['executable'] && ! isset($plan['files'][CONCERN]) && count($plan['held']) === 1 && str_contains($plan['held'][0], 'you keep '.CONCERN.', and the kit changed it upstream'), 'the first plan after the keep reports the upstream change made before it, once, and is executable');
    exec('rm -rf '.escapeshellarg($root.'/.mod-maintenance'));
    copyTree($records, $root.'/.mod-maintenance');
    exec('rm -rf '.escapeshellarg($records));
    $merged = (new Lineages($lifecycle, $host->catalog))->artifact('workspaces', $bindings)->files[CONCERN];
    file_put_contents($root.'/'.CONCERN, $merged);
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json($kernel);
    check($plan['executable'] && $plan['files'][CONCERN]['state'] === 'claim', 'once the app takes the release bytes, the file is claimed');
    check($kernel->call('kit:update', ['action' => 'apply', '--lineage' => 'workspaces', '--intent' => 'reconcile', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && isset(Checkout::installed($root, 'host')['tey/kit:workspaces']['files'][CONCERN]), 'and owned by the lineage from then on');
    // kit:update keep: the app keeps its own edit of an owned file (as an app keeps its Layout.vue). A re-pin that changes it and adds a partial
    // writes the partial, leaves the kept file alone with an attention line, and plans as executable; claim hands the file back.
    $layout = file_get_contents($root.'/'.WORKSPACE)."// the app's own layout\n";
    file_put_contents($root.'/'.WORKSPACE, $layout);
    check($kernel->call('kit:update', ['action' => 'keep', 'path' => WORKSPACE, '--lineage' => 'workspaces']) === 0 && ($keep = json($kernel))['was'] === 'owned', 'keep plans an owned, edited file');
    $tree = tree($root);
    check($kernel->call('kit:update', ['action' => 'keep', 'path' => WORKSPACE, '--lineage' => 'workspaces', '--expected-plan' => $keep['plan'], '--exclusive-checkout' => true, '--intent' => 'keep-layout']) === 0 && tree($root) === $tree, 'keep applies, writing no file');
    $record = Checkout::installed($root, 'host')['tey/kit:workspaces'];
    check(! isset($record['files'][WORKSPACE]), 'the lineage no longer owns the kept file');
    check($kernel->call('kit:update', ['action' => 'keep', 'path' => WORKSPACE]) !== 0 && str_contains($kernel->output(), 'already kept'), 'keeping it twice is refused, pointing at claim');
    $partial = 'app/Workspaces/Partials/Search.php';
    $base = ['core/migration.php.stub' => "<?php\n\n// audit_events.workspace_id: indexed unsignedBigInteger\nreturn new class {};\n", 'workspaces/stubs/Concerns/BelongsToWorkspace.php.stub' => $changed];
    $host->catalog = release('0.1.3', $base + ['workspaces/stubs/Workspace.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Workspace.php.stub')."// v-new\n// mounts search\n", 'workspaces/stubs/Partials/Search.php.stub' => "<?php\n\n// search partial\n"]);
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json($kernel);
    check($plan['executable'] && isset($plan['files'][$partial]) && ! isset($plan['files'][WORKSPACE]), 'the re-pin plans as executable: the new partial is in it, the kept file is not');
    check(count($plan['held']) === 1 && str_contains($plan['held'][0], 'ATTENTION: you keep '.WORKSPACE.', and the kit changed it upstream') && str_contains($plan['held'][0], 'kit:update claim '.WORKSPACE), 'the plan reports the upstream change to the kept file as attention');
    check($kernel->call('kit:update', ['action' => 'apply', '--lineage' => 'workspaces', '--intent' => 'repin-search', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && is_file($root.'/'.$partial) && file_get_contents($root.'/'.WORKSPACE) === $layout, 'the apply writes the partial and leaves the kept file as the app has it');
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    check(json($kernel)['held'] === [], 'once applied, the change it reported is not reported again');

    // kit:update claim: the kept file's current bytes become the lineage's original, so the next upstream change replaces it.
    check($kernel->call('kit:update', ['action' => 'claim', 'path' => WORKSPACE]) === 0 && ($claim = json($kernel))['was'] === 'held', 'claim plans a host-kept file');
    $tree = tree($root);
    check($kernel->call('kit:update', ['action' => 'claim', 'path' => WORKSPACE, '--lineage' => 'workspaces', '--expected-plan' => $claim['plan'], '--exclusive-checkout' => true, '--intent' => 'claim-layout']) === 0 && tree($root) === $tree, 'claim applies, writing no file');
    check((Checkout::installed($root, 'host')['tey/kit:workspaces']['files'][WORKSPACE] ?? null) === hash('sha256', $layout), 'the lineage owns the file again, with the app\'s bytes as its original');
    $host->catalog = release('0.1.4', $base + ['workspaces/stubs/Workspace.php.stub' => file_get_contents(__DIR__.'/fixtures/catalog/workspaces/stubs/Workspace.php.stub')."// v-new\n// mounts search, then help\n", 'workspaces/stubs/Partials/Search.php.stub' => "<?php\n\n// search partial\n"]);
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $plan = json($kernel);
    check($plan['executable'] && $plan['files'][WORKSPACE]['state'] === 'replace' && $plan['held'] === [], 'after the claim, the next upstream change replaces the file like any owned one');
    check($kernel->call('kit:update', ['action' => 'claim', 'path' => 'app/Nope.php']) !== 0 && str_contains($kernel->output(), 'No adopted feature delivers'), 'claiming a path no feature delivers is refused');

    // history.json's `current` entry is the live package catalog, so a release cannot ship it stale.
    $saved = json_decode(file_get_contents(dirname(__DIR__).'/resources/features/history.json'), true, flags: JSON_THROW_ON_ERROR)['releases'];
    $live = [];
    foreach (Catalog::package()->featureIds() as $id) {
        $live[$id] = array_map(fn (string $bytes): string => hash('sha256', $bytes), Tey\Kit\Blueprint::lineage($id)['files']);
        ksort($live[$id]);
    }
    check(array_key_last($saved) === 'current' && $saved['current'] === $live, 'history.json\'s current entry matches the live catalog (else run php bin/catalog-history.php)');
    // The newest release is the last tag history.json lists before `current`, and it must be the highest by version: no literal to bump at each release.
    $tags = array_values(array_diff(array_keys($saved), ['current']));
    $newest = end($tags);
    $highest = $tags;
    usort($highest, 'version_compare');
    check($tags !== [] && $newest === end($highest), 'history.json lists its releases in version order, the newest last before current');
    $package = (new Lineages($lifecycle))->history('core');
    check(isset($package['v0.2.0']['database/migrations/kit/core/0001_01_02_000100_create_kit_core_tables.php']) && array_key_last($package) === $newest, 'the package catalog history knows each tagged release, the v0.2.0 core migration included');
    echo "Adopt-in-place checks on synthetic catalogs and a clean-room host only; no package catalog, migrations run, HTTP or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
