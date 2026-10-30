<?php

// The starter catalog in resources/features: manifests, pins, the requires graph, stubs, ownership, kit:list and migration loading.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Contracts\IsTenant;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** Kept in step with Catalog::FEATURES; a changed manifest or stub must be repinned in both places on purpose. */
const PINS = [
    'core' => 'sha256:5ba4c374c197181d7ef87a1bf42f5c7cbddc7eae02a2fd29eccaafa0a80d1008',
    'workspaces' => 'sha256:40fdd76c4172f1c63586677fd16db16ed8ac9913c802373b94a14d01109a81c4',
    'access' => 'sha256:a179066fa9e5ca2279c2752ff67644f592ec4c5ee37e8cf263c205ddb7526d98',
    'settings' => 'sha256:fb83eb1d9e21b5d579b4cd00cce30401e3c79d0c9f839ddfcf53a77daa815f1e',
    'shell' => 'sha256:0daa7d88a2a08ae3fe2832fc75b392564e05a3e6263cc8da29deaac1f836a5b5',
    'session-ui' => 'sha256:16054cbcc2bed8968a3154473eb0e95939cba077084e3d805abe980f27f0e084',
    'workspace-ui' => 'sha256:a6ece55802247c45fe579540ad73b6658a521ed5db3b55dffcafaff86496cfdd',
    'mcp' => 'sha256:e35e287934b5e02a9b3683d2f0c98fedddae7b276c510638eacec1fd2a3b7ae0',
    'notes' => 'sha256:1a0455e79979a94149cdd82090cb59364f764a18a66f168669c2f2f862fb551b',
];
const BASE = 'sha256:c513b68d2e13a237a0e9d1bed497ab54496098de3ca03628fc44f21b2921f079';
/** Generated files live only here: per-feature migrations, Vue starting points and the shell's type aliases. Config and routes ship in the library. */
const GENERATED = ['database/migrations/kit/', 'resources/js/pages/', 'resources/js/widgets/', 'resources/js/components/Kit/', 'resources/css/kit.css', 'resources/js/types/kit-shell.d.ts'];
/** Migration prefix block per feature, in requires order: core, workspaces, access, settings, session-ui, mcp. Shell and workspace-ui migrate nothing and have no block. */
const MIGRATION_BLOCK = ['core' => 1, 'workspaces' => 2, 'access' => 3, 'settings' => 4, 'session-ui' => 5, 'mcp' => 6, 'notes' => 7];
/** Tailwind-style utilities only: a class token needs a dash or a variant, or is one of the bare utilities. */
const BARE_UTILITIES = ['flex', 'grid', 'block', 'inline', 'hidden', 'contents', 'table', 'border', 'rounded', 'shadow', 'underline', 'italic', 'truncate', 'container', 'sr-only', 'relative', 'absolute', 'fixed', 'sticky', 'static', 'grow', 'shrink', 'uppercase', 'lowercase', 'capitalize', 'antialiased', 'peer', 'group'];

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
    starterCheck(preg_match_all('/export \{ (default as LockedForm|default as EditOnly|useLocked) \}/', $snapshot[$kit.'index.ts']) === 3, '@kit/ui exports LockedForm, EditOnly and useLocked');
    foreach (['Form/Input', 'Form/Textarea', 'Form/InputNumber', 'Form/InputCheckbox', 'Form/InputSelect', 'Form/Select', 'Form/FileInput', 'Form/Repeater', 'Form/FormCard', 'Form/FormFieldset', 'Action/ActionBar', 'Layout/FloatingToolbar'] as $name) {
        starterCheck(str_contains($snapshot[$kit.$name.'.vue'], 'useLocked'), $name.' reads the locked context');
    }
    foreach (['Form/Input' => ':readonly="locked', 'Form/Textarea' => ':readonly="locked', 'Form/InputNumber' => ':readonly="locked', 'Form/InputCheckbox' => ':aria-readonly="locked', 'Form/InputSelect' => ':aria-readonly="readOnly', 'Form/Select' => ':aria-readonly="locked'] as $name => $marker) {
        starterCheck(str_contains($snapshot[$kit.$name.'.vue'], $marker) && ! preg_match('/:disabled="[^"]*lock/', $snapshot[$kit.$name.'.vue']), $name.' locks with read-only, never disabled');
    }
    // FormFieldset (the FormFieldset spec): a native fieldset and legend with the title as a heading, the description outside the legend, never `<fieldset disabled>`, no locked prop.
    $fieldset = $snapshot[$kit.'Form/FormFieldset.vue'];
    starterCheck(str_contains($fieldset, '<fieldset ') && str_contains($fieldset, '<legend ') && str_contains($fieldset, '<component :is="`h${level}`"') && str_contains($fieldset, ':aria-describedby="describedBy"') && ! str_contains($fieldset, 'fieldset :disabled') && ! str_contains($fieldset, 'locked?: boolean') && ! str_contains($fieldset, 'disabled?: boolean'), 'FormFieldset is a native fieldset with a legend heading and a described-by description, and has no locked or disabled prop');
    echo "SKIP Vue compile: the kit has no vue-tsc or @vue/compiler-sfc; blocks and relative imports are checked instead.\n";

    // kit:list prints the starter catalog.
    foreach (['bootstrap/cache', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($temp.'/'.$dir, 0700, true);
    }
    file_put_contents($temp.'/composer.json', json_encode(['name' => 'example/consumer', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $app = Application::configure($temp)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    starterCheck($kernel->call('kit:list', ['--json' => true]) === 0 && json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR) === Blueprint::entries(), 'kit:list --json prints the starter catalog');
    $kernel->call('kit:list');
    $lines = explode("\n", trim($kernel->output()));
    starterCheck($lines[0] === 'core' && $lines[1] === '  requires: -' && in_array('mcp', $lines, true) && in_array('  requires: workspaces, access', $lines, true) && ! in_array('foundation', $lines, true), 'kit:list prints each starter feature with what it requires, and no v0.1 fixture');

    // Adopted features' migrations load from database/migrations/kit/<feature> with no app configuration.
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
    (new FeaturesServiceProvider($migrating))->boot();
    // The permission migration reads the permission config, which now comes from the library defaults.
    (new WorkspacesProvider($migrating))->register();
    $paths = $migrating['migrator']->paths();
    starterCheck(array_map(fn (string $path): string => substr($path, strlen($temp) + 1), $paths) === array_map(fn (string $id): string => 'database/migrations/kit/'.$id, $selected), 'each adopted feature registers its kit migration directory, in canonical order');
    starterCheck($kernel->call('migrate', ['--force' => true]) === 0, 'migrate runs the host and kit migrations on synthetic SQLite memory');
    $tables = ['users', 'workspaces', 'operators', 'audit_events', 'entitlements', 'complimentary_grants', 'kit_entitlements', 'settings', 'settings_intents', 'connections', 'kit_mcp_intents', 'roles', 'permissions'];
    starterCheck(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)) === [], 'every kit table exists after migrate');
    starterCheck(Schema::hasColumn('workspaces', 'slug'), 'workspaces carry a slug');
    $ran = $migrating['db']->table('migrations')->pluck('migration')->all();
    $kitRan = array_values(array_filter($ran, fn (string $id): bool => str_starts_with($id, '0001_01_02_')));
    $blocks = array_map(fn (string $id): int => (int) substr($id, 14, 2), $kitRan);
    $sorted = $blocks;
    sort($sorted);
    starterCheck(count($kitRan) === 23 && $blocks === $sorted && $ran[0] === '0001_01_01_000000_create_users_table', 'the host migrations run first, then the kit migrations in requires order');
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
    // With kit.features null, the adopted features are the installed lineages (test:adopt installs real ones).
    $record = sys_get_temp_dir().'/kit-installed-'.bin2hex(random_bytes(6));
    mkdir($record, 0700, true);
    $record = realpath($record);
    starterCheck(FeaturesServiceProvider::adopted($record, 'app', null) === [], 'no installed lineage adopts nothing');
    starterCheck(FeaturesServiceProvider::adopted($record, null, null) === null, 'without an app id no lineage is read');
    starterCheck(FeaturesServiceProvider::adopted($record, 'app', ['core']) === ['core'], 'kit.features overrides the installed lineages');
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

    // An app with an edited config/kit.php adopts and grows features with no conflict: no feature owns it.
    $adapter = new Lifecycle;
    $root = sys_get_temp_dir().'/kit-starter-update-'.bin2hex(random_bytes(6));
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
    // The UI primitives core imports (its host_ui): present, as in an app with shadcn-vue's.
    foreach (['button', 'checkbox', 'dropdown-menu', 'input', 'label', 'sheet', 'spinner'] as $primitive) {
        mkdir($root.'/resources/js/components/ui/'.$primitive, 0700, true);
        file_put_contents($root.'/resources/js/components/ui/'.$primitive.'/index.ts', "export {};\n");
    }
    file_put_contents($root.'/resources/js/components/ui/spinner/Spinner.vue', "<template><span /></template>\n");
    $root = realpath($root);
    $published = "<?php\n// The app's own edits.\nreturn ['features' => null, 'routes' => ['workspaces' => ['middleware' => ['verified']]]];\n";
    file_put_contents($root.'/config/kit.php', $published);
    $operation = fn () => true;
    $check = (require $root.'/host-operations.txt');
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $host = new class($adapter, $paths, $check) implements LifecycleHost
    {
        public function __construct(private Lifecycle $adapter, private array $paths, private Closure $check) {}

        public function lifecycle(): Lifecycle
        {
            return $this->adapter;
        }

        public function appId(): string
        {
            return 'starter-update';
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
    $app->instance(LifecycleHost::class, $host);
    $kernel->call('kit:update', ['action' => 'plan', '--features' => 'core,workspaces']);
    $plan = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    starterCheck($kernel->call('kit:update', ['action' => 'apply', '--features' => 'core,workspaces', '--intent' => 'adopt', '--exclusive-checkout' => true, '--expected-plan' => $plan['plan']]) === 0 && is_file($root.'/database/migrations/kit/workspaces/0001_01_02_000202_create_kit_operators_table.php'), 'core and workspaces adopt beside a published config/kit.php');
    file_put_contents($root.'/config/kit.php', $published."// edited again after adoption\n");
    starterCheck($kernel->call('kit:update', ['action' => 'plan', '--original-features' => 'core,workspaces', '--features' => 'core,workspaces,access,mcp']) === 0, 'growing the selection after editing config/kit.php plans without conflict');
    $grow = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    starterCheck($kernel->call('kit:update', ['action' => 'apply', '--original-features' => 'core,workspaces', '--features' => 'core,workspaces,access,mcp', '--intent' => 'grow', '--exclusive-checkout' => true, '--expected-plan' => $grow['plan']]) === 0 && is_file($root.'/database/migrations/kit/mcp/0001_01_02_000600_create_kit_mcp_tables.php'), 'the update applies and delivers the new features');
    starterCheck(file_get_contents($root.'/config/kit.php') === $published."// edited again after adoption\n", 'the app\'s config/kit.php is untouched by the update');
    echo "Catalog checks only; the edited-config update runs on one single-lineage kit:update, not per-feature lineages (test:adopt covers those), and no package provider is booted.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
