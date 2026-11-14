<?php

// The starter catalog in resources/features: manifests, pins, the requires graph, stubs, ownership, catalog entries and migration loading.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\PackageMigrator;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** Kept in step with Catalog::FEATURES; a changed manifest or stub must be repinned in both places on purpose. */
const PINS = [
    'core' => 'sha256:c89c790de3034096b2e5e82e0f593e33a0b63a487222ec14b7b255b03342fdfd',
    'workspaces' => 'sha256:bbf33a40f7a70cd24b99d270cbca71f088265243e03d679ba35b588eb50a99f4',
    'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
    'settings' => 'sha256:587275c4d1c57b142fa9ff73852b9e5f7c51193e250f982c97279dfc260e1b48',
    'shell' => 'sha256:c0588ed42eb719c1c220b2f5847baf66bbf01b1a2573994fd3f93f2d128f55a2',
    'session-ui' => 'sha256:0a7ba81253a7eed39e5965e02f6c784852c45b04a11fd35c49cba0ef6bb19e87',
    'workspace-ui' => 'sha256:4bbee02bd872ac895f4b97ecb6175456f00c92ddebf37ef1d0249476965838e7',
    'mcp' => 'sha256:f1dee960991cc0cc3cdd9dd51f65be5934f4c06d6d648cf0974131bd3c4eb002',
    'notes' => 'sha256:6905800e000b1ae0de336a0b1d54116b1dc7e84e54c8ff1c82b4e9b58290f7ef',
    'finalize' => 'sha256:c121af94b55eeb2b20cef7b60d28b7887c3916e7eda8b7d437c80e2ea8e445d6',
    'deliver' => 'sha256:261e32989010829837938a479b88fc0dcb29385b69450898b2b2d2fb9483b6ea',
];
const BASE = 'sha256:596e1701b4bb1fd524fe62978ca1b2d5122f6756cfb0b5146255938545b79e39';
/** Generated files live only here: per-feature migrations, Vue starting points and the shell's type aliases. Config and routes ship in the library. */
const GENERATED = ['database/migrations/kit/', 'resources/js/pages/', 'resources/js/widgets/', 'resources/js/components/Kit/', 'resources/css/kit.css', 'resources/js/types/kit-shell.d.ts'];
/** Migration prefix block per feature, in requires order: core, workspaces, access, settings, session-ui, mcp. Shell and workspace-ui migrate nothing and have no block. */
const MIGRATION_BLOCK = ['core' => 1, 'workspaces' => 2, 'access' => 3, 'settings' => 4, 'session-ui' => 5, 'mcp' => 6, 'notes' => 7, 'finalize' => 8, 'deliver' => 9];
/** Tailwind-style utilities only: a class token needs a dash or a variant, or is one of the bare utilities. */
const BARE_UTILITIES = ['flex', 'grid', 'block', 'inline', 'hidden', 'contents', 'table', 'border', 'rounded', 'shadow', 'underline', 'italic', 'truncate', 'container', 'sr-only', 'relative', 'absolute', 'fixed', 'sticky', 'static', 'grow', 'shrink', 'uppercase', 'lowercase', 'capitalize', 'antialiased', 'peer', 'group'];

/** Kit stub source read regardless of formatting: whitespace collapsed, padded attribute values trimmed, a trailing comma before a closer dropped. */
function kitFlat(string $source): string
{
    $source = preg_replace('/\s+/', ' ', $source);
    $source = preg_replace('/="\s*([^"]*?)\s*"/', '="$1"', $source);

    return preg_replace('/,\s*([}\])])/', ' $1', $source);
}

function starterCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
/** @return list<string> */
function closureOf(string $id, array $seen = []): array
{
    foreach (FeaturesServiceProvider::FEATURES[$id][1] as $required) {
        if (! in_array($required, $seen, true)) {
            $seen[] = $required;
            $seen = closureOf($required, $seen);
        }
    }

    return $seen;
}
function within(string $path, string $prefix): bool
{
    return $path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/');
}

try {
    // Validation and pins.
    $catalog = Catalog::package();
    starterCheck($catalog->pins === PINS && $catalog->basePin === BASE, 'the package pins are the reviewed starter pins');
    $root = dirname(__DIR__).'/resources/features';
    starterCheck(Catalog::at($root, PINS, BASE)->featureIds() === array_keys(FeaturesServiceProvider::FEATURES), 'the catalog lists the starter features in canonical order');
    $all = Blueprint::all();
    starterCheck($all->selection() === array_keys(PINS) && $all->manifest['id'] === 'tey/kit', 'the whole catalog validates and every pin and the base pin verify');
    foreach (array_keys(PINS) as $id) {
        $selection = [$id, ...closureOf($id)];
        starterCheck(Blueprint::features($selection)->selection() === array_values(array_intersect(array_keys(PINS), $selection)), $id.' validates with exactly its requirement closure');
    }
    foreach (array_keys(PINS) as $id) {
        $wrong = PINS;
        $wrong[$id] = 'sha256:'.str_repeat('0', 64);
        try {
            Blueprint::features([$id, ...closureOf($id)], Catalog::at($root, $wrong, BASE));
            throw new RuntimeException('A wrong pin was accepted: '.$id);
        } catch (RuntimeException $error) {
            starterCheck(str_contains($error->getMessage(), 'identity mismatch'), 'a wrong '.$id.' pin refuses');
        }
    }

    // The requires closure is the library graph, which LibraryCheck ties to the starter's arch test.
    foreach (Blueprint::entries() as $entry) {
        $declared = $entry['requires'];
        sort($declared);
        $graph = FeaturesServiceProvider::FEATURES[$entry['id']][1];
        sort($graph);
        starterCheck($declared === $graph, $entry['id'].' requires what the library graph requires');
    }

    // Ownership.
    $manifests = [];
    foreach (array_keys(PINS) as $id) {
        $manifests[$id] = json_decode(file_get_contents($root.'/'.$id.'/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    }
    $hostOwned = array_merge(...array_column($manifests, 'host_owned'));
    $overlaps = [];
    foreach ($manifests as $id => $manifest) {
        foreach ($manifest['owns'] as $owned) {
            foreach ($hostOwned as $host) {
                if (within($owned, $host) || within($host, $owned)) {
                    $overlaps[] = $id.' owns '.$owned.' against host-owned '.$host;
                }
            }
        }
    }
    starterCheck($overlaps === [], 'no owned path overlaps a host-owned path in any feature');
    starterCheck(in_array('app/Models/User.php', $manifests['core']['host_owned'], true) && in_array('bootstrap', $manifests['core']['host_owned'], true) && in_array('config', $manifests['core']['host_owned'], true) && in_array('resources/css/app.css', $manifests['core']['host_owned'], true) && in_array('composer.json', $manifests['core']['host_owned'], true) && in_array('resources/js/app.ts', $manifests['session-ui']['host_owned'], true), 'core declares the common host-owned set (config, app.css and composer.json included), and session-ui the app entry');
    $files = $all->manifest['generated'];
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => ! array_filter(GENERATED, fn (string $prefix): bool => str_starts_with($path, $prefix))) === [], 'every generated file is a kit migration, a Vue starting point, a dashboard widget body, a kit component, the kit stylesheet or the shell\'s type aliases');
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, 'config/')) === [] && array_filter(array_merge(...array_column($manifests, 'owns')), fn (string $path): bool => within($path, 'config')) === [], 'no generated or owned file is under config/');
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, 'routes/') || str_starts_with($path, 'app/')) === [], 'no route file or app class is generated');

    // Stubs.
    $snapshot = $all->snapshot()['files'];
    starterCheck(count($snapshot) === count($files), 'the whole catalog generates '.count($files).' files');
    starterCheck(array_filter(array_keys($snapshot), fn (string $path): bool => str_starts_with($path, 'resources/js/components/Kit/')) === [], 'no shared component is generated: they load from the package');
    // The shared components, at the path an app's `@/components/Kit` alias resolves to, are checked with the generated files.
    $components = dirname(__DIR__).'/resources/js/components/Kit';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($components, FilesystemIterator::SKIP_DOTS)) as $component) {
        $snapshot['resources/js/components/Kit/'.substr($component->getPathname(), strlen($components) + 1)] = (string) file_get_contents($component->getPathname());
    }
    $flat = array_map(fn ($source) => is_string($source) ? kitFlat($source) : $source, $snapshot);
    $temp = sys_get_temp_dir().'/kit-starter-catalog-'.bin2hex(random_bytes(6));
    foreach ($snapshot as $path => $bytes) {
        @mkdir(dirname($temp.'/'.$path), 0700, true);
        file_put_contents($temp.'/'.$path, $bytes);
    }
    foreach ($snapshot as $path => $bytes) {
        if (str_ends_with($path, '.php')) {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp.'/'.$path).' 2>&1', $output, $status);
            starterCheck($status === 0, $path.' compiles');
            starterCheck(! str_contains($bytes, 'App\\Starter\\') && ! str_contains($bytes, 'LegacyDomainMigration'), $path.' names no starter-app class');
        }
    }
    foreach ($snapshot as $path => $bytes) {
        if (! str_ends_with($path, '.vue')) {
            continue;
        }
        $component = str_starts_with($path, 'resources/js/components/Kit/');
        $file = basename($path);
        // A dashboard widget body (WidgetGrid resolves `component: 'notes'` to widgets/Notes.vue): one PascalCase file, no folders.
        $widget = str_starts_with($path, 'resources/js/widgets/');
        if ($widget) {
            starterCheck(preg_match('#\Aresources/js/widgets/[A-Z][A-Za-z]*\.vue\z#', $path) === 1, $path.' is one PascalCase widget body');
        }
        if (! $component && ! $widget) {
            $relative = substr($path, strlen('resources/js/pages/'));
            $parts = explode('/', $relative);
            $file = array_pop($parts);
            starterCheck(array_filter($parts, fn (string $folder): bool => ! preg_match('/\A[A-Z][A-Za-z]*\z/', $folder)) === [] && ($file === 'Page.vue' || $file === 'Layout.vue' || ($parts !== [] && end($parts) === 'Partials')), $path.' follows pages/<Resource>/<Action>/Page.vue with PascalCase folders');
        }
        // Kit pages and the shell render UI only through the `@kit/ui` alias, which an app can point at its own components.
        starterCheck($component || ! preg_match('/from\s*[\'"]@\/(components|lib|composables)\//', $bytes), $path.' imports UI only from @kit/ui');
        starterCheck(! preg_match('/<style\b/i', $bytes), $path.' has no style block');
        preg_match_all('/\sclass="([^"]*)"/', $bytes, $classes);
        $authored = array_values(array_filter(preg_split('/\s+/', implode(' ', $classes[1]), -1, PREG_SPLIT_NO_EMPTY), fn (string $token): bool => ! in_array(preg_replace('/\A(?:(?:[a-z0-9*-]+(?:\[[^\]]+\])?|\[[^\]]+\]):)+/', '', $token), BARE_UTILITIES, true) && ! preg_match('/\A(?:(?:[a-z0-9*-]+(?:\[[^\]]+\])?|\[[^\]]+\]):)*(?:-?[a-z]+(?:-[a-z0-9.\/\[\]#%()=,:_-]+)+|\[--[a-z0-9-]+:[^\]\s]+\])!?\z/', $token)));
        starterCheck($authored === [], $path.' uses no authored class names'.($authored === [] ? '' : ': '.implode(', ', $authored)));
        starterCheck(! preg_match('/import\s+\w*Layout\b|Layout\.vue[\'"]/', $bytes) || $file === 'Layout.vue', $path.' declares no layout');
        starterCheck(substr_count($bytes, '<template>') >= 1 && substr_count($bytes, '</template>') >= 1 && preg_match_all('/<script setup lang="ts"(?: generic="[^"]*")?>/', $bytes) === 1 && substr_count($bytes, '</script>') === 1, $path.' has one template and one script setup block');
        preg_match_all("/(?:import|from)\\s*['\"](\\.{1,2}\\/[^'\"]+)['\"]/", $bytes, $imports);
        foreach ($imports[1] as $import) {
            $target = false;
            foreach (['', '.ts', '.vue'] as $extension) {
                $target = $target ?: realpath(dirname($temp.'/'.$path).'/'.$import.$extension);
            }
            starterCheck($target !== false && str_starts_with($target, dirname(realpath($temp.'/'.$path)).'/'), $path.' import '.$import.' resolves inside its own page folder');
        }
    }
    // LockedForm (the LockedForm spec): the wrapper, EditOnly and useLocked are exported, every kit control reads the context and locks with `readonly` or `aria-readonly`, never `disabled`.
    $kit = 'resources/js/components/Kit/';
    starterCheck(preg_match_all('/export \{ (default as LockedForm|default as EditOnly|useLocked) \}/', $flat[$kit.'index.ts']) === 3, '@kit/ui exports LockedForm, EditOnly and useLocked');
    foreach (['Form/Input', 'Form/Textarea', 'Form/InputNumber', 'Form/InputCheckbox', 'Form/InputSwitch', 'Form/InputSelect', 'Form/Select', 'Form/FileInput', 'Form/Repeater', 'Form/FormCard', 'Form/FormFieldset', 'Action/ActionBar', 'Layout/FloatingToolbar'] as $name) {
        starterCheck(str_contains($flat[$kit.$name.'.vue'], 'useLocked'), $name.' reads the locked context');
    }
    foreach (['Form/Input' => ':readonly="locked', 'Form/Textarea' => ':readonly="locked', 'Form/InputNumber' => ':readonly="locked', 'Form/InputCheckbox' => ':aria-readonly="locked', 'Form/InputSwitch' => ':aria-readonly="locked', 'Form/InputSelect' => ':aria-readonly="readOnly', 'Form/Select' => ':aria-readonly="locked'] as $name => $marker) {
        starterCheck(str_contains($flat[$kit.$name.'.vue'], $marker) && ! preg_match('/:disabled="[^"]*lock/', $flat[$kit.$name.'.vue']), $name.' locks with read-only, never disabled');
    }
    // FormFieldset (the FormFieldset spec): a native fieldset and legend with the title as a heading, the description outside the legend, never `<fieldset disabled>`, no locked prop.
    $fieldset = $flat[$kit.'Form/FormFieldset.vue'];
    starterCheck(str_contains($fieldset, '<fieldset ') && str_contains($fieldset, '<legend ') && str_contains($fieldset, '<component :is="`h${level}`"') && str_contains($fieldset, ':aria-describedby="describedBy"') && ! str_contains($fieldset, 'fieldset :disabled') && ! str_contains($fieldset, 'locked?: boolean') && ! str_contains($fieldset, 'disabled?: boolean'), 'FormFieldset is a native fieldset with a legend heading and a described-by description, and has no locked or disabled prop');
    echo "SKIP Vue compile: the kit has no vue-tsc or @vue/compiler-sfc; blocks and relative imports are checked instead.\n";

    // The catalog entries: each starter feature with what it requires, and no v0.1 fixture.
    $entries = array_column(Blueprint::entries(), null, 'id');
    starterCheck(array_keys($entries) === array_keys(PINS) && $entries['core']['requires'] === [] && $entries['mcp']['requires'] === ['workspaces', 'access'] && ! isset($entries['foundation']), 'Blueprint::entries lists each starter feature in canonical order with what it requires, and no v0.1 fixture');
    foreach (['bootstrap/cache', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($temp.'/'.$dir, 0700, true);
    }
    file_put_contents($temp.'/composer.json', json_encode(['name' => 'example/consumer', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));

    // The features in kit.features load their migrations from the package (and any copies under database/migrations/kit/<feature>) with no other app configuration.
    @mkdir($temp.'/database/migrations', 0700, true);
    file_put_contents($temp.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { Schema::create('users', function (Blueprint \$t): void { \$t->id(); \$t->string('name'); \$t->string('email'); }); } };\n");
    $selected = ['core', 'workspaces', 'access', 'settings', 'mcp'];
    $migrating = Application::configure($temp)->withExceptions(fn ($e) => null)->create();
    $migrating->booting(function () use ($migrating, $selected): void {
        $migrating['config']->set('database.default', 'testing');
        $migrating['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $migrating['config']->set('kit.features', $selected);
    });
    $kernel = $migrating->make(Kernel::class);
    $kernel->bootstrap();
    // Only the migration loading is under test, so the provider boots without registering the features' providers.
    $migrating->extend('migrator', fn ($migrator, $app) => PackageMigrator::from($migrator, $app));
    (new FeaturesServiceProvider($migrating))->boot();
    // The permission migration reads the permission config, which now comes from the library defaults.
    (new WorkspacesProvider($migrating))->register();
    $paths = $migrating['migrator']->paths();
    $expected = [];
    foreach ($selected as $id) {
        $expected = [...$expected, $temp.'/database/migrations/kit/'.$id, PackageMigrator::directory($id)];
    }
    starterCheck($paths === $expected, 'each adopted feature registers the app\'s database/migrations/kit/<feature>, then the package\'s directory, in canonical order');
    // This app has the catalog's copies under database/migrations/kit, as kit:add wrote them: of the package's, only the 0.18 renames are left to load.
    $package = array_filter($migrating['migrator']->getMigrationFiles($paths), fn (string $file): bool => ! str_starts_with($file, $temp.'/'));
    starterCheck(count($package) === 2 && array_filter($package, fn (string $file): bool => ! str_contains($file, '_rename_')) === [], 'the migrator lists, of the package\'s migrations, only those the app has no copy of: the renames');
    starterCheck($kernel->call('migrate', ['--force' => true]) === 0, 'migrate runs the host and kit migrations on synthetic SQLite memory');
    $tables = ['users', 'workspaces', 'operators', 'audit_events', 'entitlements', 'complimentary_grants', 'plan_entitlements', 'settings', 'settings_intents', 'connections', 'mcp_intents', 'mcp_runs', 'roles', 'permissions'];
    starterCheck(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)) === [], 'every kit table exists after migrate');
    starterCheck(Schema::hasColumn('workspaces', 'slug'), 'workspaces carry a slug');
    $ran = $migrating['db']->table('migrations')->pluck('migration')->all();
    $kitRan = array_values(array_filter($ran, fn (string $id): bool => str_starts_with($id, '0001_01_02_')));
    $blocks = array_map(fn (string $id): int => (int) substr($id, 14, 2), $kitRan);
    $sorted = $blocks;
    sort($sorted);
    starterCheck(count($kitRan) === 26 && $blocks === $sorted && $ran[0] === '0001_01_01_000000_create_users_table', 'the host migrations run first, then the kit migrations in requires order');
    foreach (array_keys($files) as $path) {
        if (str_starts_with($path, 'database/migrations/kit/')) {
            [, , , $feature, $name] = explode('/', $path);
            starterCheck(preg_match('/\A0001_01_02_000(\d)\d\d_[a-z_]+\.php\z/', $name, $match) === 1 && (int) $match[1] === MIGRATION_BLOCK[$feature], $path.' carries its feature\'s ordered prefix');
        }
    }
    foreach (array_keys(MIGRATION_BLOCK) as $id) {
        foreach (array_intersect(closureOf($id), array_keys(MIGRATION_BLOCK)) as $required) {
            starterCheck(MIGRATION_BLOCK[$required] < MIGRATION_BLOCK[$id], $id.' migrations run after '.$required.'\'s');
        }
    }
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, 'database/migrations/kit/shell/') || str_starts_with($path, 'database/migrations/kit/workspace-ui/')) === [] && ! array_diff(array_keys(PINS), [...array_keys(MIGRATION_BLOCK), 'shell', 'workspace-ui']), 'shell and workspace-ui migrate nothing; every other feature has its migration block');
    // Config defaults resolve from the library with no published files.
    $configRoot = sys_get_temp_dir().'/kit-config-'.bin2hex(random_bytes(6));
    foreach (['bootstrap/cache', 'config', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($configRoot.'/'.$dir, 0700, true);
    }
    $configured = function (string $root): Application {
        $app = Application::configure($root)->withExceptions(fn ($e) => null)->create();
        $app->bootstrapWith([LoadConfiguration::class]);
        $app['config']->set('kit.features', array_keys(PINS));
        // Registering, not booting: the defaults apply at register time; the package providers are not booted here.
        $app->register(FeaturesServiceProvider::class);

        return $app;
    };
    $app = $configured($configRoot);
    $config = $app['config'];
    starterCheck($config->get('kit.mcp.connection_ability') === 'settings.read' && $config->get('kit.capabilities') === ['workspace.dashboard' => true, 'workspace.settings' => true] && $config->get('kit.features') === array_keys(PINS), 'kit config defaults merge from the library under the app\'s own keys');
    starterCheck($config->get('permission.teams') === true && $config->get('permission.column_names.team_foreign_key') === 'workspace_id' && $config->get('permission.column_names.model_morph_key') === 'model_id' && $config->get('permission.table_names.roles') === 'roles', 'permission takes the kit keys over the package defaults, keeping the rest');
    starterCheck($config->get('multitenancy.tenant_model') === Workspace::class && $app->make('config')->get('concierge.models.workspace') === Workspace::class && $app->bound(IsTenant::class), 'the workspace model is the library model for multitenancy and concierge');
    starterCheck($config->get('pennant.default') === 'array' && $config->get('settings.cache.store') === 'array' && $config->get('fortify.home') === '/workspaces' && $config->get('inertia.testing.page_paths') === [$app->resourcePath('js/pages')], 'pennant, settings, fortify and inertia take the kit defaults');
    starterCheck(! is_dir($configRoot.'/config') || glob($configRoot.'/config/*') === [], 'no config file was written into the app');

    // A published (host-owned) file is the app's whole truth for its key.
    file_put_contents($configRoot.'/config/kit.php', "<?php\nreturn ['features' => null, 'mcp' => ['connection_ability' => 'settings.write'], 'routes' => ['settings' => ['prefix' => 'app']]];\n");
    file_put_contents($configRoot.'/config/permission.php', "<?php\nreturn ['teams' => false];\n");
    $app = $configured($configRoot);
    starterCheck($app['config']->get('kit.mcp.connection_ability') === 'settings.write' && $app['config']->get('kit.routes.settings.prefix') === 'app', 'an edited published config/kit.php wins');
    starterCheck($app['config']->get('permission.teams') === false && $app['config']->get('permission.column_names') === null, 'a published package config replaces the kit defaults entirely');
    echo "Catalog checks only; no package provider is booted.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
