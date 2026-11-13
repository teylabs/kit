<?php

// A host that already has the access feature's entitlements or complimentary_grants table, or spatie/laravel-settings' settings table, adopts a
// compatible one in place: the migration creates and changes nothing on it (its rows and columns stay as they were), records it in kit_adoptions
// and creates only the tables the host lacks (settings_intents); rolling back keeps it. The migration refuses an incompatible table, naming the
// columns, before anything is created. A schema the kit cannot read is a refusal. Synthetic tables and rows.
// SQLite memory by default. DB_CONNECTION=pgsql runs against the PostgreSQL the DB_* variables name (CI's service container), in the
// kit_table_* schemas, which it drops and recreates: point it only at a throwaway database.
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\NullHandler;
use Tey\Kit\Features\Workspaces\Adoptions;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function tableCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function tableRefused(callable $run): ?string
{
    try {
        $run();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
}

/** Every row of $table, ordered by id, as arrays: what "unchanged" is compared on. */
function rowsOf(string $table): array
{
    return DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
}

/** Each table's columns (name, type, nullable): the schema "no change" is compared on. */
function schemaOf(array $tables): array
{
    $schema = [];
    foreach ($tables as $table) {
        $schema[$table] = array_map(fn (array $c): array => [$c['name'], $c['type_name'], $c['nullable']], Schema::getColumns($table));
    }

    return $schema;
}

function tableNames(): array
{
    // PostgreSQL lists every schema's tables, and the CI database holds other checks' schemas too: count only this scenario's own.
    $schema = DB::connection()->getConfig('search_path');
    $names = array_column(array_filter(Schema::getTables(), fn (array $table): bool => $schema === null || ($table['schema'] ?? null) === $schema), 'name');
    sort($names);

    return $names;
}

try {
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $scenarios = ['access_ok', 'access_bad', 'access_partial', 'settings_ok', 'settings_bad'];
    $connections = [];
    foreach ($scenarios as $name) {
        $connections[$name] = $pgsql
            ? ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'kit_table_'.$name, 'sslmode' => 'prefer']
            : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
    }

    $base = sys_get_temp_dir().'/kit-table-adopt-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $base = realpath($base);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'database' => ['default' => 'access_ok', 'connections' => $connections],
        'settings' => ['repositories' => ['database' => ['table' => null]]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class, DatabaseServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    $use = function (string $name) use ($config, $pgsql): void {
        $config->set('database.default', $name);
        DB::setDefaultConnection($name);
        if ($pgsql) {
            DB::statement('drop schema if exists kit_table_'.$name.' cascade');
            DB::statement('create schema kit_table_'.$name);
        }
        // The host's own workspaces table, which the kit's created tables point at.
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });
        DB::table('workspaces')->insert([['name' => 'Northwind Farms'], ['name' => 'Acme Travel']]);
    };
    echo 'Database: '.($pgsql ? 'pgsql' : 'sqlite')."\n";

    $kit = dirname(__DIR__).'/resources/features';
    $stub = fn (string $feature, string $name): string => $kit.'/'.$feature.'/migrations/'.$name.'.php.stub';
    $access = '0001_01_02_000300_create_kit_access_tables';
    $settings = '0001_01_02_000400_create_settings_table';
    $intents = '0001_01_02_000401_create_kit_settings_tables';

    // (1) Compatible access tables: the host's own entitlements and complimentary_grants, with an extra nullable column, their own index and rows.
    $use('access_ok');
    Schema::create('entitlements', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('workspace_id');
        $t->string('kind');
        $t->string('source')->default('billing');
        $t->timestamp('revoked_at')->nullable();
        $t->timestamps();
    });
    Schema::create('complimentary_grants', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('workspace_id');
        $t->unsignedBigInteger('granted_by');
        $t->string('intent_key');
        $t->string('fingerprint');
        $t->unsignedBigInteger('revoked_by')->nullable();
        $t->text('revocation_reason')->nullable();
        $t->text('reason');
        $t->timestamp('expires_at');
        $t->timestamp('revoked_at')->nullable();
        $t->string('note')->nullable();
        $t->unique(['intent_key', 'granted_by']);
        $t->index('workspace_id');
    });
    DB::table('entitlements')->insert(['workspace_id' => 1, 'kind' => 'paid', 'source' => 'billing', 'revoked_at' => null, 'created_at' => '2025-01-02 03:04:05', 'updated_at' => '2025-01-02 03:04:05']);
    DB::table('complimentary_grants')->insert(['workspace_id' => 2, 'granted_by' => 7, 'intent_key' => 'grant-1', 'fingerprint' => 'f1', 'reason' => 'Pilot', 'expires_at' => '2027-01-01 00:00:00', 'note' => 'host note']);
    $before = ['rows' => [rowsOf('entitlements'), rowsOf('complimentary_grants')], 'schema' => schemaOf(['entitlements', 'complimentary_grants']), 'tables' => tableNames()];
    (require $stub('access', $access))->up();
    tableCheck([rowsOf('entitlements'), rowsOf('complimentary_grants')] === $before['rows'], 'the adopted tables\' rows are unchanged');
    tableCheck(schemaOf(['entitlements', 'complimentary_grants']) === $before['schema'], 'and their columns, types and nullability are unchanged');
    tableCheck(array_values(array_diff(tableNames(), $before['tables'])) === [Adoptions::TABLE], 'nothing is created but the kit_adoptions record');
    tableCheck(Adoptions::adopted('entitlements') && Adoptions::adopted('complimentary_grants') && Adoptions::added('complimentary_grants') === [] && DB::table(Adoptions::TABLE)->count() === 2, 'a kit_adoptions row is written for each, with no columns added');
    (require $stub('access', $access))->up();
    tableCheck([rowsOf('entitlements'), rowsOf('complimentary_grants')] === $before['rows'] && DB::table(Adoptions::TABLE)->count() === 2, 'running the migration again changes nothing');
    (require $stub('access', $access))->down();
    tableCheck(Schema::hasTable('entitlements') && Schema::hasTable('complimentary_grants') && [rowsOf('entitlements'), rowsOf('complimentary_grants')] === $before['rows'] && ! Schema::hasTable(Adoptions::TABLE), 'rolling back keeps the host tables and rows, and drops only the record');

    // (2) An incompatible complimentary_grants (person-scoped, required host columns, no idempotency key) and an entitlements table without kind.
    $use('access_bad');
    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('email');
    });
    Schema::create('complimentary_grants', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('plan_id');
        $t->unsignedBigInteger('granted_by');
        $t->text('reason');
        $t->timestamp('granted_at');
        $t->unsignedBigInteger('revoked_by')->nullable();
        $t->text('revocation_reason')->nullable();
        $t->timestamp('revoked_at');
    });
    Schema::create('entitlements', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('workspace_id');
        $t->string('revoked_at');
    });
    DB::table('complimentary_grants')->insert(['user_id' => 1, 'plan_id' => 3, 'granted_by' => 1, 'reason' => 'Pilot', 'granted_at' => '2025-01-02 03:04:05', 'revoked_at' => '2025-02-02 03:04:05']);
    $before = ['rows' => rowsOf('complimentary_grants'), 'tables' => tableNames()];
    $refusal = (string) tableRefused(fn () => (require $stub('access', $access))->up());
    [$entitlements, $grants] = explode('; complimentary_grants table', $refusal) + [1 => ''];
    tableCheck(str_contains($refusal, 'cannot adopt the host\'s entitlements table (missing columns kind') && str_contains($refusal, 'complimentary_grants table (missing columns workspace_id, intent_key, fingerprint, expires_at'), 'the migration refuses, naming both tables\' columns');
    tableCheck(str_contains($grants, 'not nullable, but the kit writes null: revoked_at'), 'a column the kit writes null into that is not nullable');
    tableCheck(str_contains($grants, 'host columns the kit\'s inserts would leave empty (not nullable, no default): user_id, plan_id, granted_at'), 'the host\'s required columns the kit would leave empty');
    tableCheck(str_contains($grants, 'no unique index on (granted_by, intent_key)'), 'and the missing unique index');
    tableCheck(str_contains($entitlements, 'missing columns kind') && str_contains($entitlements, 'revoked_at is varchar, not time'), 'the entitlements table\'s refusal names its missing and mistyped columns');
    tableCheck(rowsOf('complimentary_grants') === $before['rows'] && tableNames() === $before['tables'], 'and creates nothing and changes no row');

    // (3) Only a compatible complimentary_grants: it is adopted, and entitlements is created.
    $use('access_partial');
    Schema::create('complimentary_grants', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('workspace_id');
        $t->unsignedBigInteger('granted_by');
        $t->string('intent_key');
        $t->string('fingerprint');
        $t->unsignedBigInteger('revoked_by')->nullable();
        $t->string('revocation_reason')->nullable();
        $t->string('reason');
        $t->timestamp('expires_at');
        $t->timestamp('revoked_at')->nullable();
        $t->unique(['granted_by', 'intent_key']);
    });
    (require $stub('access', $access))->up();
    tableCheck(Schema::hasTable('entitlements') && ! Adoptions::adopted('entitlements') && Adoptions::adopted('complimentary_grants'), 'the migration creates entitlements and adopts complimentary_grants');
    (require $stub('access', $access))->down();
    tableCheck(! Schema::hasTable('entitlements') && Schema::hasTable('complimentary_grants'), 'rolling back drops only the table the kit created');

    // (4) spatie's own settings table, with a host group in it: adopted, and only settings_intents is created.
    $use('settings_ok');
    (require dirname(__DIR__).'/vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    DB::table('settings')->insert(['group' => 'general', 'name' => 'site_name', 'locked' => false, 'payload' => json_encode('Northwind Farms'), 'created_at' => '2025-01-02 03:04:05', 'updated_at' => '2025-01-02 03:04:05']);
    $before = ['rows' => rowsOf('settings'), 'schema' => schemaOf(['settings']), 'tables' => tableNames()];
    (require $stub('settings', $settings))->up();
    (require $stub('settings', $intents))->up();
    tableCheck(rowsOf('settings') === $before['rows'] && schemaOf(['settings']) === $before['schema'], 'the host\'s settings rows and columns are unchanged');
    tableCheck(array_values(array_diff(tableNames(), $before['tables'])) === [Adoptions::TABLE, 'settings_intents'], 'only settings_intents (and the record) are created');
    tableCheck(Adoptions::adopted('settings') && Adoptions::added('settings') === [], 'a kit_adoptions row is written for settings');
    (require $stub('settings', $intents))->down();
    (require $stub('settings', $settings))->down();
    tableCheck(Schema::hasTable('settings') && rowsOf('settings') === $before['rows'] && ! Schema::hasTable(Adoptions::TABLE), 'rolling back keeps the host\'s settings table and rows');

    // (5) An older settings table: group indexed but no group+name unique, no locked column, payload a string column.
    $use('settings_bad');
    Schema::create('settings', function (Blueprint $t): void {
        $t->id();
        $t->string('group')->index();
        $t->string('name');
        $t->integer('payload');
        $t->timestamps();
    });
    DB::table('settings')->insert(['group' => 'general', 'name' => 'site_name', 'payload' => 1]);
    $before = ['rows' => rowsOf('settings'), 'tables' => tableNames()];
    $refusal = (string) tableRefused(fn () => (require $stub('settings', $settings))->up());
    tableCheck(str_contains($refusal, 'settings table (missing columns locked') && str_contains($refusal, 'payload is '.($pgsql ? 'int4' : 'integer').', not json') && str_contains($refusal, 'no unique index on (group, name)'), 'the migration refuses an incompatible settings table, naming its columns and the missing unique');
    tableCheck(rowsOf('settings') === $before['rows'] && tableNames() === $before['tables'], 'and creates and changes nothing');

    // (6) Fail closed: a table the database says exists but whose columns cannot be read is a refusal, not a create.
    $real = Schema::getFacadeRoot();
    Schema::swap(new class($real)
    {
        public function __construct(private object $real) {}

        public function hasTable(string $table): bool
        {
            return $table === 'complimentary_grants' || $this->real->hasTable($table);
        }

        public function getColumns(string $table): array
        {
            throw new RuntimeException('permission denied for table '.$table);
        }

        public function __call(string $method, array $arguments): mixed
        {
            return $this->real->{$method}(...$arguments);
        }
    });
    try {
        tableCheck(str_contains((string) tableRefused(fn () => (require $stub('access', $access))->up()), 'complimentary_grants table (its schema could not be read: permission denied'), 'the migration refuses an unreadable schema');
    } finally {
        // db.schema is resolved per default connection, never cached: forget the fake so the next scenario's connection is used.
        $app->forgetInstance('db.schema');
        Schema::clearResolvedInstance('db.schema');
    }
    tableCheck(! $real->hasTable('complimentary_grants') && tableNames() === $before['tables'], 'nothing was created');

    echo 'Table adopt-in-place on '.($pgsql ? 'PostgreSQL' : 'in-memory SQLite')." with synthetic host tables; no MySQL run, no HTTP.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
