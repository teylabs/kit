<?php

// The table-naming rule (docs/domain-tables.md, Table names): a fresh migrate with every catalog feature creates no new table named kit_* (the frozen
// KIT_DOMAIN tables, which an app's adopt migration renames, are the only ones left) and no generic bare noun, a one-word name, outside the frozen
// BARE list. A table takes a plain noun, a feature prefix when the noun is generic (mcp_connections, workspace_entitlements), and never a package
// prefix. Both lists only shrink. The check proves itself: an app migration adding a kit_widgets and a tokens table must fail it. Synthetic apps in
// temp directories; SQLite files by default, DB_CONNECTION=pgsql runs each in its own kit_table_names_* schema of a throwaway database. No network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

// Domain tables still created as kit_* (as in MigrationsCheck). Frozen: a new table is never named kit_*.
const KIT_DOMAIN = [
    'kit_finalize_confirmations', 'kit_finalizations', 'kit_notes', 'kit_note_intents', 'kit_deliveries', 'kit_delivery_recipients', 'kit_delivery_events',
    'kit_security_events', 'kit_capability_overrides', 'kit_entitlements', 'kit_permission_defaults', 'kit_account_deletions', 'kit_user_state',
];
// One-word tables a fresh install has, each specific enough or owned by Laravel or a package. Frozen: a new generic noun takes its feature's prefix.
const BARE = ['users', 'migrations', 'workspaces', 'operators', 'roles', 'permissions', 'settings', 'tags', 'taggables'];

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** @param array<string, string> $files */
function host(array $files = []): Application
{
    $root = sys_get_temp_dir().'/kit-table-names-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config', 'database/migrations'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    touch($root.'/database/database.sqlite');
    $connection = ['driver' => 'sqlite', 'database' => $root.'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => false];
    if (getenv('DB_CONNECTION') === 'pgsql') {
        $schema = 'kit_table_names_'.(++$GLOBALS['schemas']);
        $connection = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => $schema, 'sslmode' => 'prefer'];
        $pdo = new PDO('pgsql:host='.$connection['host'].';port='.$connection['port'].';dbname='.$connection['database'], $connection['username'], $connection['password']);
        $pdo->exec('drop schema if exists '.$schema.' cascade; create schema '.$schema);
    }
    $files['database/migrations/0001_01_01_000000_create_users_table.php'] = "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::create('users', fn (\$t) => \$t->id());\n    }\n};\n";
    foreach ($files as $path => $bytes) {
        file_put_contents($root.'/'.$path, $bytes);
    }
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => Catalog::package()->featureIds(), 'models' => ['user' => User::class], 'routes' => ['mcp' => ['enabled' => false]]], true).';');
    file_put_contents($root.'/config/database.php', '<?php return '.var_export(['default' => 'kit', 'migrations' => ['table' => 'migrations'], 'connections' => ['kit' => $connection]], true).';');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['env' => 'testing', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/permission.php', '<?php return '.var_export(['teams' => false, 'table_names' => ['roles' => 'roles', 'permissions' => 'permissions', 'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions'], 'column_names' => ['role_pivot_key' => null, 'permission_pivot_key' => null, 'model_morph_key' => 'model_id', 'team_foreign_key' => 'team_id']], true).';');
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    if ($app->make(Kernel::class)->call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('migrate failed: '.$app->make(Kernel::class)->output());
    }

    return $app;
}

/** @return list<string> each table that breaks the rule, with why */
function offenders(): array
{
    $bad = [];
    foreach (array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name') as $table) {
        if (str_starts_with($table, 'kit_') && ! in_array($table, KIT_DOMAIN, true)) {
            $bad[] = $table.' (a package prefix)';
        } elseif (! str_contains($table, '_') && ! in_array($table, BARE, true)) {
            $bad[] = $table.' (a generic bare noun: give it its feature\'s prefix)';
        }
    }
    sort($bad);

    return $bad;
}

$schemas = 0;
try {
    host();
    $bad = offenders();
    check($bad === [], 'a fresh migrate with every feature creates no kit_* table but the frozen domain ones, and no bare noun outside the frozen list (got: '.implode(', ', $bad).')');
    $tables = array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name');
    check(array_diff(BARE, $tables) === [], 'every allowed bare noun is still created (drop one from BARE once it is gone)');
    check(array_diff(array_diff(KIT_DOMAIN, ['kit_capability_overrides', 'kit_entitlements', 'kit_permission_defaults', 'kit_account_deletions', 'kit_user_state']), $tables) === [], 'the frozen kit_* domain tables a fresh install keeps are there');
    // The rule itself: an app migration adding a package-prefixed and a generic bare table fails it, naming both.
    host(['database/migrations/2026_11_27_000000_create_bad_tables.php' => "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::create('kit_widgets', fn (\$t) => \$t->id());\n        Illuminate\\Support\\Facades\\Schema::create('tokens', fn (\$t) => \$t->id());\n    }\n};\n"]);
    check(offenders() === ['kit_widgets (a package prefix)', 'tokens (a generic bare noun: give it its feature\'s prefix)'], 'a kit_widgets and a tokens table each fail the rule');
    echo 'Table names after a fresh migrate on '.(getenv('DB_CONNECTION') === 'pgsql' ? 'PostgreSQL (throwaway schemas)' : 'SQLite files').", synthetic apps. Concierge's own tables and a consumer app are not covered here.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n");
    exit(1);
}
