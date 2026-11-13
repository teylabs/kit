<?php

// The kit's migrations load from the package (database/migrations/<feature>) since 0.18, deferring to an app's own copies under
// database/migrations/kit/<feature>: the package baselines are the stubs kit:add copied, byte for byte and by name; an app that ran its copies runs none
// of them again (a stamped copy too), and an edited copy is the one that runs. The 0.18 renames move the infrastructure tables' rows intact, run on a
// fresh database too, and refuse when both names exist. The adopt flags from config keep the kit from creating a host's table. Synthetic apps in temp directories, synthetic rows; no network. SQLite files by default;
// DB_CONNECTION=pgsql runs each app in its own kit_migrations_* schema of the PostgreSQL the DB_* variables name, dropped and recreated: point it only
// at a throwaway database.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\FeaturesServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const PACKAGE = __DIR__.'/../database/migrations';
const FEATURES = ['core', 'workspaces', 'access', 'shell', 'session-ui', 'mcp'];
const RENAMES = [
    'kit_user_state' => 'user_state', 'kit_account_deletions' => 'account_deletions', 'kit_entitlements' => 'plan_entitlements', 'kit_permission_defaults' => 'permission_defaults',
    'kit_capability_overrides' => 'capability_overrides', 'kit_mcp_intents' => 'mcp_intents', 'kit_mcp_registrations' => 'mcp_registrations', 'kit_mcp_runs' => 'mcp_runs',
];
const RENAME_MIGRATIONS = ['2026_11_14_000304_rename_access_tables', '2026_11_14_000506_rename_session_ui_tables', '2026_11_14_000605_rename_mcp_tables'];
// Kept as they are in 0.18: the domain tables (app-owned in 0.19), kit_meta and kit_adoptions.
const UNTOUCHED = ['kit_meta', 'kit_adoptions', 'kit_security_events', 'entitlements', 'complimentary_grants', 'workspaces'];

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/**
 * A synthetic app with the kit's FEATURES, its own users table and its own database, and the given files (path => bytes) and kit config.
 *
 * @param  array<string, string>  $files
 * @param  array<string, mixed>  $kit
 */
function host(array $files = [], array $kit = []): Application
{
    $root = sys_get_temp_dir().'/kit-migrations-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config', 'database/migrations'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    touch($root.'/database/database.sqlite');
    $connection = ['driver' => 'sqlite', 'database' => $root.'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => false];
    if (getenv('DB_CONNECTION') === 'pgsql') {
        $schema = 'kit_migrations_'.(++$GLOBALS['schemas']);
        $connection = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => $schema, 'sslmode' => 'prefer'];
        $pdo = new PDO('pgsql:host='.$connection['host'].';port='.$connection['port'].';dbname='.$connection['database'], $connection['username'], $connection['password']);
        $pdo->exec('drop schema if exists '.$schema.' cascade; create schema '.$schema);
    }
    file_put_contents($root.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::create('users', fn (\$t) => \$t->id());\n    }\n};\n");
    foreach ($files as $path => $bytes) {
        @mkdir(dirname($root.'/'.$path), 0700, true);
        file_put_contents($root.'/'.$path, $bytes);
    }
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export($kit + ['features' => FEATURES, 'models' => ['user' => User::class], 'routes' => ['mcp' => ['enabled' => false]]], true).';');
    file_put_contents($root.'/config/database.php', '<?php return '.var_export(['default' => 'kit', 'migrations' => ['table' => 'migrations'], 'connections' => ['kit' => $connection]], true).';');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['env' => 'testing', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/permission.php', '<?php return '.var_export(['teams' => false, 'table_names' => ['roles' => 'roles', 'permissions' => 'permissions', 'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions'], 'column_names' => ['role_pivot_key' => null, 'permission_pivot_key' => null, 'model_morph_key' => 'model_id', 'team_foreign_key' => 'team_id']], true).';');
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

/** The app copies kit:add wrote for every feature: each package baseline at database/migrations/kit/<feature>/, under its name or a stamp. */
function copies(string $stamp = '0001_01_02'): array
{
    $files = [];
    foreach (FEATURES as $id) {
        foreach (glob(PACKAGE.'/'.$id.'/0001_01_02_*.php') ?: [] as $file) {
            $files['database/migrations/kit/'.$id.'/'.$stamp.substr(basename($file), 10)] = (string) file_get_contents($file);
        }
    }

    return $files;
}

/** Runs `migrate`; returns the migrations it ran, in order. */
function migrate(Application $app, array $options = []): array
{
    $before = Schema::hasTable('migrations') ? $app['db']->table('migrations')->pluck('migration')->all() : [];
    $code = $app->make(Kernel::class)->call('migrate', ['--force' => true] + $options);
    if ($code !== 0) {
        throw new RuntimeException('migrate exited '.$code.': '.$app->make(Kernel::class)->output());
    }

    return array_values(array_diff($app['db']->table('migrations')->orderBy('id')->pluck('migration')->all(), $before));
}

/** Runs only the host's own migrations and the app's copies, as `migrate` did before 0.18. */
function migrate017(Application $app): array
{
    $paths = [$app->databasePath('migrations'), ...array_map(fn (string $id): string => $app->databasePath('migrations/kit/'.$id), FEATURES)];

    return migrate($app, ['--path' => array_filter($paths, 'is_dir'), '--realpath' => true]);
}

/** Runs $write with foreign keys unchecked: SQLite runs with them off; PostgreSQL skips their triggers for this session (the CI role is a superuser). */
function unchecked(Closure $write): mixed
{
    $pgsql = Schema::getConnection()->getDriverName() === 'pgsql';
    if ($pgsql) {
        Schema::getConnection()->statement('set session_replication_role = replica');
    }
    try {
        return $write();
    } finally {
        if ($pgsql) {
            Schema::getConnection()->statement('set session_replication_role = origin');
        }
    }
}

/** Three synthetic rows for a table, every column filled with a value of its type that differs per row. Their keys point at no row. */
function seed(string $table): void
{
    unchecked(function () use ($table): void {
        foreach ([1, 2, 3] as $row) {
            $values = [];
            foreach (Schema::getColumns($table) as $column) {
                $type = strtolower((string) $column['type_name']);
                $values[$column['name']] = match (true) {
                    str_contains($type, 'int') => $row * 7 + strlen($column['name']),
                    str_contains($type, 'bool') => $row % 2 === 1,
                    str_contains($type, 'json') => json_encode(['row' => $row, 'column' => $column['name']]),
                    str_contains($type, 'date') || str_contains($type, 'time') => sprintf('2026-0%d-1%d 12:3%d:00', $row, $row, $row),
                    str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'numeric') || str_contains($type, 'decimal') => $row + 0.5,
                    $type === 'uuid' => sprintf('00000000-0000-4000-8000-%012d', $row * 100 + strlen($column['name'])),
                    default => substr(md5($column['name']), 0, 5).$row,
                };
            }
            Schema::getConnection()->table($table)->insert($values);
        }
    });
}

/** @return list<array<string, mixed>> */
function rows(string $table): array
{
    return array_map(fn (object $row): array => (array) $row, Schema::getConnection()->table($table)->orderBy(Schema::getColumns($table)[0]['name'])->get()->all());
}

$schemas = 0;
try {
    // The package baselines are the catalog's migration stubs: the same names kit:add gave its copies, the same bytes. Only new migrations are package-only.
    $stubs = [];
    foreach (glob(__DIR__.'/../resources/features/*/migrations/*.php.stub') ?: [] as $stub) {
        $stubs[basename(dirname($stub, 2)).'/'.basename($stub, '.stub')] = hash_file('sha256', $stub);
    }
    $package = [];
    foreach (glob(PACKAGE.'/*/*.php') ?: [] as $file) {
        $package[basename(dirname($file)).'/'.basename($file)] = hash_file('sha256', $file);
    }
    $baselines = array_filter($package, fn (string $path): bool => str_contains($path, '/0001_01_02_'), ARRAY_FILTER_USE_KEY);
    echo 'Database: '.(getenv('DB_CONNECTION') === 'pgsql' ? 'pgsql' : 'sqlite')."\n";
    check($stubs !== [] && $baselines === $stubs, 'every package baseline is a catalog stub at the same feature, name and bytes ('.count($stubs).')');
    $others = array_map(fn (string $path): string => basename($path, '.php'), array_keys(array_diff_key($package, $baselines)));
    sort($others);
    check($others === RENAME_MIGRATIONS, 'the package\'s only other migrations are the 0.18 renames, one per feature');

    // A fresh app on 0.18 (no copies): the package's migrations run once each, and end at the new names.
    $fresh = host();
    $ran = migrate($fresh);
    check(count($ran) === count(array_unique($ran)) && array_diff(array_map(fn (string $path): string => basename($path, '.php'), array_keys(array_filter($package, fn ($hash, string $path): bool => in_array(dirname($path), FEATURES, true), ARRAY_FILTER_USE_BOTH))), $ran) === [], 'a fresh app runs each of its features\' package migrations once ('.count($ran).')');
    check(array_filter(RENAMES, fn (string $to, string $from): bool => Schema::hasTable($from) || ! Schema::hasTable($to), ARRAY_FILTER_USE_BOTH) === [] && array_filter(UNTOUCHED, fn (string $table): bool => $table !== 'kit_adoptions' && ! Schema::hasTable($table)) === [], 'a fresh database has each infrastructure table under its new name only, and the domain tables and kit_meta as they were');
    check(migrate($fresh) === [], 'a second migrate runs nothing');
    check(FeaturesServiceProvider::packageMigrations('shell', $fresh->databasePath('migrations/kit/shell')) === [], 'a feature with no migrations loads none');

    // An app upgraded from 0.17: its copies ran, with rows in every table. migrate runs no baseline again: only the renames, which keep every row.
    $upgraded = host(copies());
    $ran017 = migrate017($upgraded);
    check(count($ran017) > 20 && preg_grep('/rename/', $ran017) === [], 'the 0.17 app ran its copies ('.count($ran017).')');
    $seeded = [];
    foreach (array_keys(RENAMES) as $table) {
        seed($table);
        $seeded[$table] = rows($table);
    }
    check(count($seeded['kit_user_state']) === 3 && $seeded['kit_user_state'][0] !== $seeded['kit_user_state'][1], 'each old table holds three distinct synthetic rows');
    $ran = migrate($upgraded);
    check($ran === RENAME_MIGRATIONS, 'after the upgrade migrate runs only the three renames: no baseline runs twice ('.implode(', ', $ran).')');
    foreach (RENAMES as $from => $to) {
        check(! Schema::hasTable($from) && rows($to) === $seeded[$from], $from.' is now '.$to.', with the same '.count($seeded[$from]).' rows, column for column');
    }
    $id = unchecked(fn (): int => Schema::getConnection()->table('user_state')->insertGetId(['user_id' => $seeded['kit_user_state'][0]['user_id'], 'workspace_id' => 0, 'key' => 'after-rename', 'value' => 'v']));
    check($id > 0 && ! in_array($id, array_column($seeded['kit_user_state'], 'id'), true), 'a renamed table still numbers new rows itself (its id sequence came along)');
    $names = $upgraded['db']->table('migrations')->pluck('migration')->all();
    check(count($names) === count(array_unique($names)), 'the migrations table names each migration once');
    $upgraded->make(Kernel::class)->call('migrate:rollback', ['--force' => true, '--step' => 3]);
    check(array_filter(RENAMES, fn (string $to, string $from): bool => ! Schema::hasTable($from) || Schema::hasTable($to), ARRAY_FILTER_USE_BOTH) === [] && rows('kit_mcp_runs') === $seeded['kit_mcp_runs'], 'rolling the renames back restores the old names and rows');

    // An edited copy is the one that runs, on a fresh database too, and its package twin never does.
    $files = copies();
    $edited = 'database/migrations/kit/session-ui/0001_01_02_000502_create_kit_user_state_table.php';
    $files[$edited] = str_replace("\$t->text('value')->nullable();", "\$t->text('value')->nullable();\n            \$t->string('edited_by_the_app')->nullable();", $files[$edited]);
    check(str_contains($files[$edited], 'edited_by_the_app'), 'the fixture edits the app\'s copy of the user state migration');
    $app = host($files);
    $ran = migrate($app);
    check(Schema::hasColumn('user_state', 'edited_by_the_app') && count(array_keys($ran, '0001_01_02_000502_create_kit_user_state_table', true)) === 1 && count($ran) === count(array_unique($ran)), 'the app\'s edited copy runs, once, and the package\'s file of that name does not; the rename still follows');
    check(! in_array(PACKAGE.'/session-ui/0001_01_02_000502_create_kit_user_state_table.php', FeaturesServiceProvider::packageMigrations('session-ui', $app->databasePath('migrations/kit/session-ui')), true)
        && in_array(realpath(PACKAGE.'/session-ui/2026_11_14_000506_rename_session_ui_tables.php'), array_map('realpath', FeaturesServiceProvider::packageMigrations('session-ui', $app->databasePath('migrations/kit/session-ui'))), true), 'the package loads the rename beside the app\'s copies, and not their baselines');

    // A host that adopted its own tables got its copies stamped (`<stamp>_<order>_<name>.php`): the package's baseline of each is not loaded either.
    $stamped = host(copies('2026_03_04_050607'));
    $loaded = array_map('basename', FeaturesServiceProvider::packageMigrations('session-ui', $stamped->databasePath('migrations/kit/session-ui')));
    check($loaded === ['2026_11_14_000506_rename_session_ui_tables.php'], 'a stamped copy keeps its package baseline out; only the rename loads ('.implode(', ', $loaded).')');
    $ran = migrate($stamped);
    check(preg_grep('/\A0001_01_02_/', $ran) === [] && count(preg_grep('/\A2026_03_04_050607_/', $ran)) === count(copies()), 'migrate on the stamped app runs its copies and no package baseline');

    // A stamped app upgraded from 0.17: its stamped copies ran, with rows. migrate re-runs no create: only the renames, which keep the rows.
    $stampedUpgrade = host(copies('2026_03_04_050607'));
    $ran017 = migrate017($stampedUpgrade);
    check(count(preg_grep('/\A2026_03_04_050607_/', $ran017)) === count(copies()) && preg_grep('/\A0001_01_02_/', $ran017) === [], 'the stamped 0.17 app ran its stamped copies ('.count($ran017).')');
    seed('kit_user_state');
    $stampedRows = rows('kit_user_state');
    $ran = migrate($stampedUpgrade);
    check($ran === RENAME_MIGRATIONS, 'after the upgrade the stamped app runs only the three renames: no create runs again ('.implode(', ', $ran).')');
    check(! Schema::hasTable('kit_user_state') && rows('user_state') === $stampedRows, 'and kit_user_state\'s rows are in user_state, intact');
    check(migrate($stampedUpgrade) === [], 'a second migrate on the stamped app runs nothing');

    // An edited stamped copy is the one that runs, and its package twin never does.
    $files = copies('2026_03_04_050607');
    $edited = 'database/migrations/kit/session-ui/2026_03_04_050607_000502_create_kit_user_state_table.php';
    $files[$edited] = str_replace("\$t->text('value')->nullable();", "\$t->text('value')->nullable();\n            \$t->string('edited_by_the_app')->nullable();", $files[$edited]);
    check(str_contains($files[$edited], 'edited_by_the_app'), 'the fixture edits the app\'s stamped copy of the user state migration');
    $ran = migrate(host($files));
    check(Schema::hasColumn('user_state', 'edited_by_the_app') && in_array('2026_03_04_050607_000502_create_kit_user_state_table', $ran, true)
        && ! in_array('0001_01_02_000502_create_kit_user_state_table', $ran, true) && count($ran) === count(array_unique($ran)), 'the edited stamped copy runs, once, and the package\'s twin does not; the rename still follows');

    // Both names present: the rename refuses, naming both, and changes nothing.
    $both = host(copies());
    migrate017($both);
    seed('kit_user_state');
    $kept = rows('kit_user_state');
    Schema::create('user_state', fn ($t) => $t->id());
    $refusal = null;
    try {
        migrate($both);
    } catch (Throwable $error) {
        $refusal = $error->getMessage();
    }
    check($refusal !== null && str_contains($refusal, 'Both kit_user_state and user_state exist') && str_contains($refusal, 'never merges'), 'with both kit_user_state and user_state present, migrate refuses, naming both');
    check(rows('kit_user_state') === $kept && rows('user_state') === [], 'and neither table is changed');
    // Every pair is checked before any table is renamed, so a database without transactional DDL is never left half renamed. up() and down() are
    // called directly here, with no transaction to roll a partial rename back.
    $partial = host(copies());
    migrate017($partial);
    $rename = require PACKAGE.'/session-ui/2026_11_14_000506_rename_session_ui_tables.php';
    Schema::create('account_deletions', fn ($t) => $t->id());
    $refusal = null;
    try {
        $rename->up();
    } catch (Throwable $error) {
        $refusal = $error->getMessage();
    }
    check(str_contains((string) $refusal, 'Both kit_account_deletions and account_deletions exist') && Schema::hasTable('kit_user_state') && ! Schema::hasTable('user_state'), 'a conflict on the second table refuses before the first is renamed');
    Schema::drop('account_deletions');
    $rename->up();
    check(Schema::hasTable('user_state') && Schema::hasTable('account_deletions') && ! Schema::hasTable('kit_user_state') && ! Schema::hasTable('kit_account_deletions'), 'with the conflict gone, both tables are renamed');
    Schema::create('kit_user_state', fn ($t) => $t->id());
    $refusal = null;
    try {
        $rename->down();
    } catch (Throwable $error) {
        $refusal = $error->getMessage();
    }
    check(str_contains((string) $refusal, 'Both user_state and kit_user_state exist') && Schema::hasTable('account_deletions') && ! Schema::hasTable('kit_account_deletions'), 'down checks every pair first too: a conflict on its last table leaves the first unrenamed');
    // The adopt flags come from config alone. On, with the host's table not there yet, the kit's migration refuses to create it, and says why.
    foreach (['workspaces' => 'workspaces', 'access' => 'roles'] as $flag => $table) {
        $adopting = host([], [$flag => ['adopt' => true]]);
        $refusal = null;
        try {
            migrate($adopting);
        } catch (Throwable $error) {
            $refusal = $error->getMessage();
        }
        check(str_contains((string) $refusal, 'kit.'.$flag.'.adopt is on') && ! Schema::hasTable($table), 'with kit.'.$flag.'.adopt on and no host '.$table.' table, migrate refuses and creates none');
    }
    // An adopting host with its own workspaces table: `migrate --pretend` shows the adoption's SQL and changes nothing.
    $pretending = host(['database/migrations/0001_01_01_000001_create_workspaces_table.php' => "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::create('workspaces', fn (\$t) => \$t->id());\n    }\n};\n"], ['workspaces' => ['adopt' => true]]);
    migrate($pretending, ['--path' => [$pretending->databasePath('migrations')], '--realpath' => true]);
    $kernel = $pretending->make(Kernel::class);
    $code = $kernel->call('migrate', ['--force' => true, '--pretend' => true]);
    $sql = $kernel->output();
    check($code === 0 && preg_match('/alter table "?workspaces"? add column "?provisioning_invitation_id"?/i', $sql) === 1 && ! str_contains($sql, 'create table "workspaces"'), 'migrate --pretend on an adopting host shows the columns it adds to the host\'s workspaces table, and no create');
    check(! Schema::hasColumn('workspaces', 'provisioning_invitation_id') && ! Schema::hasTable('user_state'), 'and it changes nothing');
    echo 'Package migrations and the 0.18 renames on '.(getenv('DB_CONNECTION') === 'pgsql' ? 'PostgreSQL (throwaway schemas)' : 'SQLite files').", synthetic apps and rows. A consumer app is not covered here.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
