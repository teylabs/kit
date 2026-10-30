<?php

// An adopting host on a fresh database. kit:add plans the adoption of the host's own workspaces and permission tables, records it in the
// workspaces lineage's installed record (which kit.workspaces.adopt / kit.access.adopt read), and publishes the kit's migrations with a stamp
// after the host's own, so `migrate` on an empty database runs the host's migrations first and ends with the same schema as adopting over an
// existing database. A non-adopting host keeps the catalog's names; an existing install keeps its names on kit:update and re-runs nothing.
// Synthetic catalog copy (the real workspaces and access migration stubs), synthetic host app and tables. SQLite memory by default;
// DB_CONNECTION=pgsql runs the migrate comparisons against the PostgreSQL the DB_* variables name, in the kit_fresh_*, kit_prod_* and kit_plain_*
// schemas, which it drops and recreates: point it only at a throwaway database.
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Adoptions;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const KIT = __DIR__.'/../resources/features';

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function refused(Closure $run): ?string
{
    try {
        $run();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
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
/**
 * The fixture catalog with the kit's real workspaces and access migrations (their catalog names), at $version; $extra adds workspaces
 * migrations (name => bytes), as a later release would.
 */
function catalog(string $version, array $extra = []): Catalog
{
    $root = sys_get_temp_dir().'/kit-adopt-fresh-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    foreach (['workspaces', 'access'] as $id) {
        $manifest = json_decode(file_get_contents($root.'/'.$id.'/feature.json'), true);
        $manifest['owns'][] = 'database/migrations/kit/'.$id;
        mkdir($root.'/'.$id.'/migrations');
        $stubs = [];
        foreach (glob(KIT.'/'.$id.'/migrations/*.stub') as $stub) {
            $stubs[basename($stub)] = file_get_contents($stub);
        }
        if ($id === 'workspaces') {
            foreach ($extra as $name => $bytes) {
                $stubs[$name.'.stub'] = $bytes;
            }
        }
        foreach ($stubs as $name => $bytes) {
            file_put_contents($root.'/'.$id.'/migrations/'.$name, $bytes);
            $manifest['files']['database/migrations/kit/'.$id.'/'.basename($name, '.stub')] = ['stub' => 'migrations/'.$name, 'scaffold' => 'kit', 'ownership' => 'generated'];
        }
        file_put_contents($root.'/'.$id.'/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    file_put_contents($root.'/blueprint.json', str_replace('"version": "0.1.0"', '"version": "'.$version.'"', file_get_contents($root.'/blueprint.json')));

    return repin($root);
}
/** A disposable host app, booted, with the host's own migrations (a 2024 workspaces table and spatie's permission tables) in database/migrations. */
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-adopt-fresh-'.bin2hex(random_bytes(8));
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
    // Core's audit_events (unconstrained workspace_id) sorts first, as the kit's core migrations do; the host's own tables follow, with real dates.
    file_put_contents($root.'/database/migrations/0001_01_01_000100_create_audit_events_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('audit_events', function (Blueprint $t): void {
                    $t->id();
                    $t->unsignedBigInteger('workspace_id')->nullable();
                    $t->string('event');
                });
            }
        };
        PHP);
    file_put_contents($root.'/database/migrations/2024_03_01_120000_create_workspaces_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('workspaces', function (Blueprint $t): void {
                    $t->id();
                    $t->string('name');
                    $t->boolean('accepts_members')->default(false);
                    $t->timestamps();
                });
            }
        };
        PHP);
    copy(dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub', $root.'/database/migrations/2024_03_01_120001_create_permission_tables.php');
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $config = $app->make('config');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array', 'serialize' => false]);
    $config->set('permission', array_replace_recursive(require dirname(__DIR__).'/vendor/spatie/laravel-permission/config/permission.php', require dirname(__DIR__).'/config/defaults/permission.php', ['cache' => ['store' => 'array']]));

    return [$root, $paths, $app];
}
/** A fresh, empty connection named $name: SQLite memory, or a newly created PostgreSQL schema. */
function freshConnection(Application $app, string $name): string
{
    $config = $app->make('config');
    if (getenv('DB_CONNECTION') === 'pgsql') {
        $pg = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'prefer'];
        $config->set('database.connections.pg_admin', $pg + ['search_path' => 'public']);
        DB::connection('pg_admin')->statement('drop schema if exists '.$name.' cascade');
        DB::connection('pg_admin')->statement('create schema '.$name);
        $config->set('database.connections.'.$name, $pg + ['search_path' => $name]);
    } else {
        $config->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }
    DB::purge($name);

    return $name;
}
/** Runs `migrate` over $paths on $connection (the default connection while it runs, as artisan migrate does); returns the names it ran. */
function migrate(Application $app, string $connection, array $paths): array
{
    $config = $app->make('config');
    $previous = $config->get('database.default');
    $config->set('database.default', $connection);
    DB::setDefaultConnection($connection);
    try {
        $migrator = $app->make('migrator');
        $migrator->setConnection($connection);
        $repository = $migrator->getRepository();
        $repository->setSource($connection);
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        return array_map(fn (string $file): string => basename($file, '.php'), $migrator->run($paths));
    } finally {
        $config->set('database.default', $previous);
        DB::setDefaultConnection($previous);
    }
}
/** The schema as Laravel introspects it: tables, columns, indexes and foreign keys, without the schema name. */
function schemaOf(string $connection): array
{
    $schema = Schema::connection($connection);
    $out = [];
    foreach ($schema->getTables() as $table) {
        if (getenv('DB_CONNECTION') === 'pgsql' && ($table['schema'] ?? $connection) !== $connection) {
            continue;
        }
        $name = $table['name'];
        $out[$name] = [
            'columns' => array_map(fn (array $c): array => [$c['name'], $c['type_name'], $c['type'], $c['nullable'], $c['default'], $c['auto_increment']], $schema->getColumns($name)),
            'indexes' => array_map(fn (array $i): array => [$i['name'], $i['columns'], $i['unique'], $i['primary']], $schema->getIndexes($name)),
            'foreign' => array_map(fn (array $f): array => [$f['name'], $f['columns'], $f['foreign_table'], $f['foreign_columns'], $f['on_update'], $f['on_delete']], $schema->getForeignKeys($name)),
        ];
        sort($out[$name]['indexes']);
        sort($out[$name]['foreign']);
    }
    ksort($out);

    return $out;
}
/** The kit migrations a lineage put under database/migrations/kit, by basename. */
function kitMigrations(string $root): array
{
    $names = array_map('basename', glob($root.'/database/migrations/kit/*/*.php'));
    sort($names);

    return $names;
}
function hostTables(): void
{
    Schema::create('workspaces', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->boolean('accepts_members')->default(false);
        $t->timestamps();
    });
    (require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
}

try {
    $lifecycle = new Lifecycle;
    $bindings = ['frontend' => 'SyntheticLayout'];
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $suffix = bin2hex(random_bytes(3));
    $v1 = catalog('0.1.0');
    $kit = new Lineages($lifecycle, $v1);
    $plainNames = array_map(fn (string $stub): string => basename($stub, '.stub'), [...glob(KIT.'/workspaces/migrations/*.stub'), ...glob(KIT.'/access/migrations/*.stub')]);
    sort($plainNames);

    // 1. The adopting host: its workspaces and spatie's tables exist where kit:add plans (production), and its own migrations are in the codebase.
    [$root, $paths, $app] = hostApp();
    hostTables();
    $stamp = Lineages::stamp($root);
    check($stamp > '2024_03_01_120001' && $stamp >= gmdate('Y_m_d').'_000000', 'the stamp sorts after every host migration and is no earlier than today ('.$stamp.')');
    $preview = $kit->preview($root, 'host', ['workspaces', 'access'], $bindings, $paths);
    check($preview['executable'] && count($preview['adoptions']) === 2, 'kit:add plans the adoption of the host\'s workspaces and permission tables, executable');
    check(count(array_filter($preview['host_steps'], fn (string $step): bool => str_contains($step, 'in your create_workspaces migration\'s down(), drop any audit_events foreign key on workspace_id before dropping the table'))) === 1, 'and lists the rollback host step: drop the kit\'s audit key in the host\'s create_workspaces down() first');
    $planned = array_merge(...array_map(fn (array $row): array => array_keys($row['files'] ?? []), $preview['features']));
    check(in_array('database/migrations/kit/workspaces/'.$stamp.'_000200_create_kit_workspaces_table.php', $planned, true) && in_array('database/migrations/kit/access/'.$stamp.'_000300_create_kit_access_tables.php', $planned, true) && preg_grep('#/0001_01_02_#', $planned) === [], 'the plan publishes every workspaces and access migration with the stamp, keeping the kit\'s order after it');
    check($kit->preview($root, 'host', ['workspaces', 'access'], $bindings, $paths)['plan'] === $preview['plan'], 'the stamp comes from the checkout, so a second preview has the same plan identity');
    $result = $kit->add($root, 'host', ['workspaces', 'access'], $bindings, $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt');
    check($result['status'] === 'completed', 'the reviewed adopting plan applies');
    $adoptNames = kitMigrations($root);
    $expected = array_map(fn (string $name): string => str_replace('0001_01_02_', $stamp.'_', $name), $plainNames);
    sort($expected);
    check($adoptNames === $expected, 'the kit\'s migrations are on disk with the stamp ('.count($adoptNames).' files)');
    $record = Checkout::artifact($root, 'host', Lineages::name('workspaces'));
    check(($record?->metadata['adopt'] ?? null) === ['stamp' => $stamp, 'tables' => ['permission', 'workspaces']], 'the workspaces lineage\'s installed record holds the adoption: the stamp and the adopted tables');
    check((Checkout::artifact($root, 'host', Lineages::name('access'))?->metadata['adopt']['stamp'] ?? null) === $stamp, 'the access lineage\'s record holds its stamp, so its updates keep it');
    // The flags: null in config reads the record (FeaturesServiceProvider), and an app's own value wins.
    $config = $app->make('config');
    $config->set('kit.app_id', 'host');
    $config->set('kit.features', []);
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    $app->register(FeaturesServiceProvider::class, true);
    check($config->get('kit.workspaces.adopt') === true && $config->get('kit.access.adopt') === true, 'kit.workspaces.adopt and kit.access.adopt read true from the installed record');
    $config->set('kit.workspaces.adopt', false);
    $config->set('kit.access.adopt', null);
    $app->register(FeaturesServiceProvider::class, true);
    check($config->get('kit.workspaces.adopt') === false && $config->get('kit.access.adopt') === true, 'a value the app sets wins over the record');
    $config->set('kit.workspaces.adopt', true);

    // 2. Fresh database: the host's migrations and the kit's stamped ones, in one migrate.
    $fresh = freshConnection($app, 'kit_fresh_'.$suffix);
    $ran = migrate($app, $fresh, [$root.'/database/migrations', $root.'/database/migrations/kit/workspaces', $root.'/database/migrations/kit/access']);
    check(array_slice($ran, 0, 3) === ['0001_01_01_000100_create_audit_events_table', '2024_03_01_120000_create_workspaces_table', '2024_03_01_120001_create_permission_tables'] && array_slice($ran, 3) === array_map(fn (string $n): string => basename($n, '.php'), $adoptNames), 'migrate on a fresh database runs the host\'s migrations, then every kit migration ('.count($ran).' in all)');
    $auditKey = array_map(fn (string $n): string => $stamp.'_'.$n, ['000205_make_audit_events_workspace_id_nullable', '000206_null_orphaned_audit_events_workspace_ids', '000207_audit_events_workspace_id_null_on_delete', '000208_add_workspace_name_to_audit_events']);
    check(array_values(array_intersect($ran, $auditKey)) === $auditKey && Schema::connection($fresh)->hasColumn('audit_events', 'workspace_name'), 'the audit key migrations (000205-000208) are stamped and run on the fresh adopting database, leaving audit_events.workspace_name');
    DB::setDefaultConnection($fresh);
    check(Adoptions::adopted('workspaces') && Adoptions::added('workspaces') === ['provisioning_invitation_id', 'settings_version', 'admitted', 'slug', 'deleting_at', 'deleting_by'], 'the kit adopted the host\'s workspaces table on the fresh database, adding only its own columns');
    DB::setDefaultConnection('sqlite');

    // The production adoption, as before this fix: the host's tables first, then the kit's migrations under the catalog's names.
    $prod = freshConnection($app, 'kit_prod_'.$suffix);
    $plainDir = $root.'/../'.basename($root).'-plain';
    foreach (['workspaces', 'access'] as $id) {
        @mkdir($plainDir.'/'.$id, 0700, true);
        foreach (glob(KIT.'/'.$id.'/migrations/*.stub') as $stub) {
            copy($stub, $plainDir.'/'.$id.'/'.basename($stub, '.stub'));
        }
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($plainDir)));
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    migrate($app, $prod, [$root.'/database/migrations']);
    migrate($app, $prod, [$plainDir.'/workspaces', $plainDir.'/access']);
    $config->set('kit.workspaces.adopt', true);
    $config->set('kit.access.adopt', true);
    // migrate --pretend on the adopting host before its first kit migrate: the host's tables exist, the kit's migrations are pending.
    $pretend = freshConnection($app, 'kit_pretend_'.$suffix);
    migrate($app, $pretend, [$root.'/database/migrations']);
    $config->set('database.default', $pretend);
    DB::setDefaultConnection($pretend);
    $pretendOutput = new BufferedOutput;
    try {
        $migrator = $app->make('migrator');
        $migrator->setConnection($pretend);
        $migrator->getRepository()->setSource($pretend);
        $migrator->setOutput(new OutputStyle(new ArrayInput([]), $pretendOutput));
        $pretendError = refused(fn () => $migrator->run([$plainDir.'/workspaces', $plainDir.'/access'], ['pretend' => true]));
    } finally {
        $config->set('database.default', 'sqlite');
        DB::setDefaultConnection('sqlite');
    }
    $pretendSql = $pretendOutput->fetch();
    check($pretendError === null, 'migrate --pretend on an adopting host does not throw'.($pretendError === null ? '' : ' ('.$pretendError.')'));
    check(preg_match('/alter table "?workspaces"? add column "?provisioning_invitation_id"?/i', $pretendSql) === 1 && str_contains($pretendSql, 'kit_adoptions') && ! str_contains($pretendSql, 'create table "workspaces"') && ! str_contains($pretendSql, 'create table "roles"'), 'and it lists the adoption\'s statements (columns added to the host\'s workspaces, the adoption record) without creating the host\'s tables');
    check(! Schema::connection($pretend)->hasColumn('workspaces', 'provisioning_invitation_id') && ! Schema::connection($pretend)->hasTable('kit_adoptions'), 'and it changes nothing');
    $freshSchema = schemaOf($fresh);
    $prodSchema = schemaOf($prod);
    check($freshSchema === $prodSchema, 'the fresh adopting schema equals the production adoption: '.count($freshSchema).' tables, their columns, indexes and foreign keys');
    check(isset($freshSchema['entitlements'], $freshSchema['complimentary_grants'], $freshSchema['kit_entitlements'], $freshSchema['kit_capability_overrides'], $freshSchema['kit_adoptions']) && in_array(['workspace_id'], array_column($freshSchema['audit_events']['foreign'], 1), true), 'and it has the access tables, the adoption record and the audit_events foreign key to the host\'s workspaces');
    // The bug this fixes: the catalog's names on a fresh adopting database run before the host's own migration, which then fails.
    $broken = freshConnection($app, 'kit_plain_'.$suffix);
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    check(str_contains((string) refused(fn () => migrate($app, $broken, [$root.'/database/migrations', $plainDir.'/workspaces', $plainDir.'/access'])), 'already exists'), 'with the catalog\'s names, a fresh adopting migrate fails (the kit creates workspaces before the host does)');
    // With the flag on, the kit's workspaces migration never creates the table, and says why.
    $config->set('kit.workspaces.adopt', true);
    $config->set('kit.access.adopt', true);
    $empty = freshConnection($app, 'kit_plain_'.$suffix);
    DB::setDefaultConnection($empty);
    try {
        check(str_contains((string) refused(fn () => (require $plainDir.'/workspaces/0001_01_02_000200_create_kit_workspaces_table.php')->up()), 'kit.workspaces.adopt is on') && ! Schema::hasTable('workspaces'), 'with kit.workspaces.adopt on and no host table yet, the kit\'s migration refuses and creates nothing');
        check(str_contains((string) refused(fn () => (require $plainDir.'/workspaces/0001_01_02_000201_create_permission_tables.php')->up()), 'kit.access.adopt is on') && ! Schema::hasTable('roles'), 'with kit.access.adopt on and no host permission tables yet, the permission migration refuses and creates nothing');
    } finally {
        DB::setDefaultConnection('sqlite');
    }

    // 3. A later release adds a workspaces migration: the adopting install keeps every name, and the new one takes the install's stamp.
    $config->set('kit.features', null);
    $config->set('kit.app_id', null);
    $added = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('kit_fresh_markers', function (Blueprint \$t): void {\n            \$t->id();\n            \$t->foreignId('workspace_id')->constrained();\n        });\n    }\n};\n";
    $kit2 = new Lineages($lifecycle, catalog('0.2.0', ['0001_01_02_000299_create_kit_fresh_markers_table.php' => $added]));
    // Core first: a feature's requires must be at the release before it updates.
    $core = $kit2->updatePlan($root, 'host', 'core', $bindings, $paths);
    check($kit2->updateApply($core, 'core', $paths, 'adopt-fresh-core', require $root.'/verification/host-operations.txt')['status'] === 'completed', 'core updates to the release first');
    $plan = $kit2->updatePlan($root, 'host', 'workspaces', $bindings, $paths);
    $created = array_keys(array_filter($plan->files, fn (array $file): bool => $file['state'] === 'create'));
    check($created === ['database/migrations/kit/workspaces/'.$stamp.'_000299_create_kit_fresh_markers_table.php'], 'kit:update plans only the new migration, under the install\'s stamp');
    $update = $kit2->updateApply($plan, 'workspaces', $paths, 'adopt-fresh-update', require $root.'/verification/host-operations.txt');
    check($update['status'] === 'completed' && array_diff($adoptNames, kitMigrations($root)) === [], 'the update applies and renames nothing');
    check((Checkout::artifact($root, 'host', Lineages::name('workspaces'))?->metadata['adopt'] ?? null) === ['stamp' => $stamp, 'tables' => ['permission', 'workspaces']], 'the updated record keeps the adoption');
    check(migrate($app, $fresh, [$root.'/database/migrations', $root.'/database/migrations/kit/workspaces', $root.'/database/migrations/kit/access']) === [$stamp.'_000299_create_kit_fresh_markers_table'], 'migrate after the update runs only the new migration; nothing is re-run');

    // 4. A non-adopting host: no host tables where kit:add plans; the catalog's names, and the kit creates its tables.
    [$plainRoot, $plainPaths, $plainApp] = hostApp();
    foreach (glob($plainRoot.'/database/migrations/2024_*') as $file) {
        unlink($file);
    }
    $preview = $kit->preview($plainRoot, 'host', ['workspaces', 'access'], $bindings, $plainPaths);
    check($preview['executable'] && $preview['adoptions'] === [], 'a host without its own tables plans no adoption');
    check(array_filter($preview['host_steps'], fn (string $step): bool => str_contains($step, 'create_workspaces migration')) === [], 'and no rollback host step');
    check($kit->add($plainRoot, 'host', ['workspaces', 'access'], $bindings, $plainPaths, $preview['plan'], null, require $plainRoot.'/verification/host-operations.txt')['status'] === 'completed', 'its plan applies');
    $plainInstalled = kitMigrations($plainRoot);
    check($plainInstalled === $plainNames, 'its migrations keep the catalog\'s names (0001_01_02_*)');
    check(! array_key_exists('adopt', Checkout::artifact($plainRoot, 'host', Lineages::name('workspaces'))?->metadata ?? ['adopt' => true]), 'its installed record holds no adoption');
    $plainConfig = $plainApp->make('config');
    $plainConfig->set('kit.app_id', 'host');
    $plainConfig->set('kit.features', []);
    $plainApp->register(FeaturesServiceProvider::class, true);
    check($plainConfig->get('kit.workspaces.adopt') === null && $plainConfig->get('kit.access.adopt') === null, 'and the adopt flags stay null');
    $own = freshConnection($plainApp, 'kit_plain_'.$suffix);
    $ran = migrate($plainApp, $own, [$plainRoot.'/database/migrations', $plainRoot.'/database/migrations/kit/workspaces', $plainRoot.'/database/migrations/kit/access']);
    check(count($ran) === count($plainNames) + 1 && Schema::connection($own)->hasTable('workspaces') && Schema::connection($own)->hasTable('roles') && ! Schema::connection($own)->hasTable(Adoptions::TABLE), 'on a fresh database the kit creates the workspaces and permission tables, and records no adoption');

    // 5. An existing install keeps its names on kit:update, even with the tables in the database now, and re-runs nothing.
    $plainConfig->set('kit.features', null);
    $plainConfig->set('kit.app_id', null);
    DB::setDefaultConnection($own);
    try {
        $kit2->updateApply($kit2->updatePlan($plainRoot, 'host', 'core', $bindings, $plainPaths), 'core', $plainPaths, 'adopt-fresh-plain-core', require $plainRoot.'/verification/host-operations.txt');
        $plan = $kit2->updatePlan($plainRoot, 'host', 'workspaces', $bindings, $plainPaths);
    } finally {
        DB::setDefaultConnection('sqlite');
    }
    $created = array_keys(array_filter($plan->files, fn (array $file): bool => $file['state'] === 'create'));
    check($created === ['database/migrations/kit/workspaces/0001_01_02_000299_create_kit_fresh_markers_table.php'], 'kit:update on an existing install plans the new migration under the catalog\'s name');
    check($kit2->updateApply($plan, 'workspaces', $plainPaths, 'adopt-fresh-plain-update', require $plainRoot.'/verification/host-operations.txt')['status'] === 'completed' && array_diff($plainInstalled, kitMigrations($plainRoot)) === [], 'the update applies and renames no installed migration');
    check(migrate($plainApp, $own, [$plainRoot.'/database/migrations', $plainRoot.'/database/migrations/kit/workspaces', $plainRoot.'/database/migrations/kit/access']) === ['0001_01_02_000299_create_kit_fresh_markers_table'], 'migrate after the update runs only the new migration');

    if ($pgsql) {
        foreach (['kit_fresh_', 'kit_prod_', 'kit_plain_'] as $prefix) {
            DB::connection('pg_admin')->statement('drop schema if exists '.$prefix.$suffix.' cascade');
        }
    }
    echo 'Fresh-database adoption on '.($pgsql ? 'PostgreSQL' : 'SQLite memory').' with a synthetic catalog copy (the real workspaces and access migrations) and synthetic host migrations; kit:add and kit:update through Lineages, not the artisan commands; no MySQL run.'."\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
