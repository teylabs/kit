<?php

// kit:detach, the one-off before 0.18: it writes into config/kit.php the features and adopt flags the app boots from its installed records today
// (the composition root's own answer, carry included), keeps the rest of the file byte for byte, refuses a differing kit.features list unless
// --force, writes nothing on --dry-run, reports each recorded file and each migration copy, prints the deletion checklist and the kit:status,
// kit:update and kit:cache references, and fails closed on an unreadable record. Synthetic catalog copy (the real workspaces and access migration
// stubs, and a session-ui that still generates a workspace page) and synthetic host apps on SQLite memory; no consumer acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Catalog;
use Tey\Kit\Features\DiscoveryCache;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const KIT = __DIR__.'/../resources/features';
// A page the pre-split session-ui generated and listed as moved to workspace-ui: an installed session-ui that owns it carries workspace-ui.
const WORKSPACE_PAGE = 'resources/js/pages/Workspace/Dashboard/Page.vue';

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
/** The fixture catalog with the kit's real workspaces and access migrations; $carry gives session-ui the workspace page, as before the split. */
function catalog(bool $carry): Catalog
{
    $root = sys_get_temp_dir().'/kit-detach-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    foreach (['workspaces', 'access'] as $id) {
        $manifest = json_decode(file_get_contents($root.'/'.$id.'/feature.json'), true);
        $manifest['owns'][] = 'database/migrations/kit/'.$id;
        mkdir($root.'/'.$id.'/migrations');
        foreach (glob(KIT.'/'.$id.'/migrations/*.stub') as $stub) {
            copy($stub, $root.'/'.$id.'/migrations/'.basename($stub));
            $manifest['files']['database/migrations/kit/'.$id.'/'.basename($stub, '.stub')] = ['stub' => 'migrations/'.basename($stub), 'scaffold' => 'kit', 'ownership' => 'generated'];
        }
        file_put_contents($root.'/'.$id.'/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    if ($carry) {
        $manifest = json_decode(file_get_contents($root.'/session-ui/feature.json'), true);
        $manifest['owns'][] = 'resources/js/pages/Workspace';
        $manifest['files'][WORKSPACE_PAGE] = ['stub' => 'dashboard-page.vue.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
        file_put_contents($root.'/session-ui/dashboard-page.vue.stub', "<template><main>Dashboard</main></template>\n");
        file_put_contents($root.'/session-ui/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    $pins = [];
    foreach (['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes'] as $id) {
        $stubs = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$id, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($root.'/'.$id) + 1);
            if ($relative !== 'feature.json' && ! str_contains('/'.$relative, '/.')) {
                $stubs[$relative] = hash('sha256', file_get_contents($file->getPathname()));
            }
        }
        ksort($stubs);
        $pins[$id] = 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $root.'/'.$id.'/feature.json'), $stubs]));
    }

    return Catalog::at($root, $pins, 'sha256:'.hash_file('sha256', $root.'/blueprint.json'));
}
/** A disposable host app, booted, with its own migrations in database/migrations; $tables creates its workspaces and permission tables. */
function hostApp(bool $tables): array
{
    $root = sys_get_temp_dir().'/kit-detach-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/host-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/cache', 'verification', 'database/migrations'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () { return true; };');
    file_put_contents($root.'/database/migrations/2024_03_01_120000_create_workspaces_table.php', "<?php\n\n// The host's own workspaces table.\n");
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    $app->make('config')->set('cache.default', 'array');
    $app->make('config')->set('kit.app_id', 'host');
    $app->make('config')->set('permission', array_replace_recursive(require dirname(__DIR__).'/vendor/spatie/laravel-permission/config/permission.php', require dirname(__DIR__).'/config/defaults/permission.php', ['cache' => ['store' => 'array']]));
    if ($tables) {
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        (require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    }

    return [$root, $paths, $app];
}
function add(Lineages $kit, string $root, array $paths, array $ids): void
{
    $preview = $kit->preview($root, 'host', $ids, ['frontend' => 'SyntheticLayout'], $paths);
    check($preview['executable'], 'kit:add '.implode(', ', $ids).' plans as executable');
    check($kit->add($root, 'host', $ids, ['frontend' => 'SyntheticLayout'], $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt')['status'] === 'completed', 'and applies');
}
/** kit:update keep or claim of one path, planned and applied as the command does. */
function hold(Lineages $kit, string $root, array $paths, string $action, string $path): void
{
    $planned = ($action === 'keep' ? $kit->keepPlan(...) : $kit->claimPlan(...))($root, 'host', $path, null, ['frontend' => 'SyntheticLayout'], $paths);
    $result = $kit->holdApply($planned['plan'], $action, $path, $planned['feature'], $paths, 'detach-'.$action.'-'.bin2hex(random_bytes(4)), require $root.'/verification/host-operations.txt');
    check($result['status'] === 'completed', 'kit:update '.$action.' '.$path.' applies');
}
/** What the composition root boots today, read live and through kit:cache's file, and the adopt flags its register() sets. */
function live(Application $app): array
{
    $config = $app->make('config');
    $features = fn (): array => (fn (): array => $this->features())->call(new FeaturesServiceProvider($app));
    // Registered with no features, register() sets only the flags (and boot() registers kit:cache).
    DiscoveryCache::clear($app);
    $config->set('kit.features', []);
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    $app->register(FeaturesServiceProvider::class, true);
    $flags = array_keys(array_filter(['workspaces' => $config->get('kit.workspaces.adopt'), 'access' => $config->get('kit.access.adopt')], fn ($value): bool => $value === true));
    $config->set('kit.features', null);
    $live = $features();
    check($app->make(Kernel::class)->call('kit:cache') === 0, 'kit:cache caches the record');
    DiscoveryCache::forget();
    $cached = $features();
    DiscoveryCache::clear($app);
    check($cached === $live, 'the cached read boots what the live read does ('.implode(', ', $live).')');

    return [$live, $flags];
}
function detach(Application $app, array $options = []): array
{
    $kernel = $app->make(Kernel::class);
    $exit = $kernel->call('kit:detach', $options);
    // output() empties the buffer it reads: read it once.
    $output = $kernel->output();

    return [$exit, $output, isset($options['--json']) && $exit === 0 ? json_decode($output, true, flags: JSON_THROW_ON_ERROR) : null];
}

try {
    // 1. An adopting host whose session-ui still owns a workspace page: records for core, workspaces, access and session-ui.
    [$root, $paths, $app] = hostApp(true);
    $kit = new Lineages(new Lifecycle, catalog(true));
    add($kit, $root, $paths, ['workspaces', 'access', 'session-ui']);
    $copies = glob($root.'/database/migrations/kit/*/*.php');
    $edited = array_values(preg_grep('#/workspaces/\d{4}_\d{2}_\d{2}_\d{6}_000200_create_kit_workspaces_table\.php$#', $copies))[0] ?? null;
    check($edited !== null && preg_grep('#/0001_01_02_#', $copies) === [], 'kit:add published the migrations stamped, after the host\'s own ('.count($copies).' copies)');
    // The app's own changes: an edited migration, a missing file, a kept file, and a file kept and then claimed back.
    $edited = substr($edited, strlen($root) + 1);
    file_put_contents($root.'/'.$edited, file_get_contents($root.'/'.$edited)."// the host's change\n");
    hold($kit, $root, $paths, 'keep', 'app/Session/SessionBanner.php');
    file_put_contents($root.'/app/Support/Core/CoreMarker.php', file_get_contents($root.'/app/Support/Core/CoreMarker.php')."// the host's change\n");
    hold($kit, $root, $paths, 'keep', 'app/Support/Core/CoreMarker.php');
    hold($kit, $root, $paths, 'claim', 'app/Support/Core/CoreMarker.php');
    unlink($root.'/app/Access/AccessRules.php');
    [$live, $flags] = live($app);
    check(FeaturesServiceProvider::discover($root, 'host')['carry'] === true && $live === ['core', 'workspaces', 'access', 'shell', 'session-ui', 'workspace-ui'] && $flags === ['workspaces', 'access'], 'the app boots with workspace-ui carried and shell implied, and with both adopt flags on from the record');

    // What the deletion checklist and the reference scan look for, written after boot (Laravel would load bootstrap/providers.php).
    foreach (['app/Kit/KitHost.php', 'app/Kit/operations.php', 'config/kit-lifecycle.php', 'config/kit-executor.json', 'app/Providers/KitHostServiceProvider.php', 'Envoy.blade.php', '.github/workflows/deploy.yml', 'README.md'] as $file) {
        @mkdir(dirname($root.'/'.$file), 0700, true);
        file_put_contents($root.'/'.$file, "synthetic\n");
    }
    file_put_contents($root.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\KitHostServiceProvider::class,\n];\n");
    file_put_contents($root.'/.gitignore', "/vendor\n/.mod-maintenance/journal-*.json\n/.mod-maintenance/lock\n/.mod-maintenance/owner.json\n/.mod-maintenance-stage-*\n/node_modules\n");
    file_put_contents($root.'/.github/workflows/deploy.yml', "steps:\n  - run: php artisan kit:statuses\n  - run: php artisan kit:cache\n");
    file_put_contents($root.'/Envoy.blade.php', "@task('deploy')\n    php artisan migrate --force\n    php artisan kit:status --check\n@endtask\n");
    file_put_contents($root.'/README.md', "Run php artisan kit:update plan.\n");
    $composer = json_decode(file_get_contents($root.'/composer.json'), true);
    file_put_contents($root.'/composer.json', json_encode($composer + ['scripts' => ['post-update-cmd' => ['@php artisan kit:update plan', '@php artisan optimize'], 'test' => 'phpunit']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // --dry-run with no config/kit.php: the full answer, nothing written or published.
    [$exit, , $report] = detach($app, ['--dry-run' => true, '--json' => true]);
    check($exit === 0 && $report['features'] === $live && $report['adopt'] === $flags && $report['config']['status'] === 'would publish and write' && ! file_exists($root.'/config/kit.php'), '--dry-run reports the composition root\'s features and flags, and publishes and writes nothing');

    // The write: the kit-config stub is published, and only its features entry and the two adopt defaults change.
    [$exit, , $report] = detach($app, ['--json' => true]);
    $expected = str_replace("    'features' => null,", "    'features' => [\n        'core',\n        'workspaces',\n        'access',\n        'shell',\n        'session-ui',\n        'workspace-ui',\n    ],", file_get_contents(dirname(__DIR__).'/config/kit.php'), $one);
    $expected = str_replace(["'adopt' => env('KIT_WORKSPACES_ADOPT')", "'adopt' => env('KIT_ACCESS_ADOPT')"], ["'adopt' => env('KIT_WORKSPACES_ADOPT', true)", "'adopt' => env('KIT_ACCESS_ADOPT', true)"], $expected, $two);
    check($exit === 0 && $report['config']['status'] === 'published and written' && $one === 1 && $two === 2 && file_get_contents($root.'/config/kit.php') === $expected, 'kit:detach publishes config/kit.php and changes exactly its features entry and the two adopt defaults; every other byte is the stub\'s');
    $written = require $root.'/config/kit.php';
    check($written['features'] === $live && $written['workspaces']['adopt'] === true && $written['access']['adopt'] === true && $written['workspaces']['provisioning'] === 'invite_only', 'the written file boots the same features and flags (an unset KIT_*_ADOPT reads true), and keeps the rest of those arrays');
    putenv('KIT_ACCESS_ADOPT=false');
    check((require $root.'/config/kit.php')['access']['adopt'] === false, 'and a KIT_ACCESS_ADOPT the environment sets still wins, as it did over the record');
    putenv('KIT_ACCESS_ADOPT');

    // The statuses, by lineage.
    $files = array_merge(...array_column($report['lineages'], 'files'));
    $lineages = array_column($report['lineages'], 'lineage');
    check($lineages === ['tey/kit:access', 'tey/kit:core', 'tey/kit:session-ui', 'tey/kit:workspaces'], 'every installed lineage is reported');
    check($files['app/Session/SessionBanner.php'] === 'kept' && $files['app/Access/AccessRules.php'] === 'missing' && $files[$edited] === 'edited' && $files[WORKSPACE_PAGE] === 'unchanged', 'a kept file is kept, a deleted one missing, an edited one edited and an untouched one unchanged');
    check($files['app/Support/Core/CoreMarker.php'] === 'unchanged', 'a file claimed back after an edit is owned at its claimed bytes, so it reads unchanged (kit:update claim leaves no other mark)');
    check(count(array_filter($files, fn (string $state): bool => $state === 'edited')) === 1, 'and no other file reads edited');

    // The migration copies: each against the package stub of its catalog name.
    $migrations = array_column($report['migrations'], 'status', 'path');
    $identical = [];
    foreach ($copies as $copy) {
        $relative = substr($copy, strlen($root) + 1);
        $catalogName = preg_replace('/\A\d{4}_\d{2}_\d{2}_\d{6}_/', '0001_01_02_', basename($copy));
        $same = file_get_contents($copy) === file_get_contents(KIT.'/'.basename(dirname($copy)).'/migrations/'.$catalogName.'.stub');
        $same && $identical[] = $relative;
        check(($migrations[$relative] ?? null) === ($relative === $edited ? 'edited' : 'identical') && $same === ($relative !== $edited), $relative.' is '.$migrations[$relative]);
    }
    check(count($migrations) === count($copies) && array_unique(array_column($report['migrations'], 'stamped')) === [true], 'every copy is reported, each as stamped');

    // The checklist and the references.
    check($report['delete'] === ['.mod-maintenance/', 'app/Kit/KitHost.php', 'app/Kit/operations.php', 'config/kit-lifecycle.php', 'config/kit-executor.json', 'app/Providers/KitHostServiceProvider.php', 'bootstrap/providers.php:5: App\Providers\KitHostServiceProvider::class,', '.gitignore:2: /.mod-maintenance/journal-*.json', '.gitignore:3: /.mod-maintenance/lock', '.gitignore:4: /.mod-maintenance/owner.json', '.gitignore:5: /.mod-maintenance-stage-*', ...$identical], 'the checklist: .mod-maintenance, the kit:install files, the provider line, the .mod-maintenance .gitignore lines and the identical copies, not the edited one');
    check($report['references'] === ['composer.json scripts.post-update-cmd: @php artisan kit:update plan', '.github/workflows/deploy.yml:3: - run: php artisan kit:cache', 'Envoy.blade.php:3: php artisan kit:status --check'], 'the references: composer scripts, workflows and deploy files, not kit:statuses or a README');
    check(file_exists($root.'/.mod-maintenance') && is_file($root.'/app/Kit/KitHost.php') && count(glob($root.'/database/migrations/kit/*/*.php')) === count($copies), 'kit:detach deleted nothing');

    // Again: the file already says so.
    [$exit, $output] = detach($app);
    check($exit === 0 && str_contains($output, 'config/kit.php already says so; unchanged') && file_get_contents($root.'/config/kit.php') === $expected, 'a second run changes nothing');

    // An unreadable record: exit 1 with the reason, nothing written.
    $record = $root.'/'.Lineages::recordPath('core');
    $bytes = file_get_contents($record);
    file_put_contents($record, str_replace('"version"', '"versio"', $bytes));
    file_put_contents($root.'/config/kit.php', "<?php\n\nreturn ['features' => null];\n");
    [$exit, $output] = detach($app);
    check($exit === 1 && str_contains($output, 'The installed records could not be read: ') && str_contains($output, 'Nothing written') && file_get_contents($root.'/config/kit.php') === "<?php\n\nreturn ['features' => null];\n", 'a record that does not verify exits 1 with the reason, and writes nothing');
    file_put_contents($record, '{');
    [$exit, $output] = detach($app, ['--json' => true]);
    check($exit === 1 && str_contains($output, 'could not be read'), 'nor does a record that is not JSON');
    file_put_contents($record, $bytes);
    $app->make('config')->set('kit.app_id', null);
    [$exit, $output] = detach($app);
    check($exit === 1 && str_contains($output, 'no kit app id'), 'with no app id there is no record to read: exit 1');

    // 2. A host that adopted no tables, with a session-ui installed after the split: no carry, no flags, the catalog's migration names.
    [$root, $paths, $app] = hostApp(false);
    $kit = new Lineages(new Lifecycle, catalog(false));
    add($kit, $root, $paths, ['session-ui']);
    [$live, $flags] = live($app);
    [$exit, , $report] = detach($app, ['--dry-run' => true, '--json' => true]);
    check($live === ['core', 'workspaces', 'shell', 'session-ui'] && $flags === [] && $report['features'] === $live && $report['adopt'] === [], 'without the workspace page nothing is carried, and no flag is on: kit:detach says the same');
    check(array_unique(array_column($report['migrations'], 'status')) === ['identical'] && array_unique(array_column($report['migrations'], 'stamped')) === [false] && count($report['migrations']) === count(glob(KIT.'/workspaces/migrations/*.stub')), 'its copies keep the catalog names and are identical to the package\'s stubs');

    // An app's own config/kit.php with a kit.features list that differs: refused, unless --force.
    $own = "<?php\n\n// The app's own kit config: its comments and spacing stay.\nreturn [\n    'currency'   => 'CAD',\n    \"features\" => [\"core\", \"workspaces\"],   // trimmed by hand\n    'workspaces' => ['provisioning' => 'open'],\n];\n";
    file_put_contents($root.'/config/kit.php', $own);
    [$exit, $output] = detach($app);
    check($exit === 1 && str_contains($output, 'config/kit.php: [core, workspaces]') && str_contains($output, 'the records:    [core, workspaces, shell, session-ui]') && file_get_contents($root.'/config/kit.php') === $own, 'a kit.features list that boots other features is refused: both lists are printed, exit 1, and the file is unchanged');
    [$exit, $output] = detach($app, ['--force' => true, '--dry-run' => true]);
    check($exit === 0 && str_contains($output, 'would be written (--dry-run)') && file_get_contents($root.'/config/kit.php') === $own, '--force --dry-run says it would write, and writes nothing');
    [$exit] = detach($app, ['--force' => true]);
    check($exit === 0 && file_get_contents($root.'/config/kit.php') === str_replace('["core", "workspaces"]', "['core', 'workspaces', 'shell', 'session-ui']", $own), '--force replaces only the list, in its one-line layout; the comments, spacing and other keys stay');

    // A list that boots the same features (shell implied by session-ui) is made explicit without --force, in its multi-line layout.
    $own = "<?php\n\nreturn [\n    'features' => [\n        'core',\n        'workspaces',\n        'session-ui',\n    ],\n];\n";
    file_put_contents($root.'/config/kit.php', $own);
    [$exit] = detach($app);
    check($exit === 0 && file_get_contents($root.'/config/kit.php') === "<?php\n\nreturn [\n    'features' => [\n        'core',\n        'workspaces',\n        'shell',\n        'session-ui',\n    ],\n];\n", 'a list that boots the same features is rewritten to the full list without --force, keeping its layout');
    // A features entry that is not a literal list or null can't be anchored: exit 1, nothing written.
    $own = "<?php\n\nreturn ['features' => explode(',', 'core,workspaces,shell,session-ui,mcp')];\n";
    file_put_contents($root.'/config/kit.php', $own);
    [$exit, $output] = detach($app, ['--force' => true]);
    check($exit === 1 && str_contains($output, 'is not a literal list or null') && file_get_contents($root.'/config/kit.php') === $own, 'a computed features entry is never rewritten, even with --force: the edit to make is printed, exit 1');
    echo "kit:detach checks on synthetic host apps with a synthetic catalog copy (the real workspaces and access migration stubs); SQLite memory; no consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
