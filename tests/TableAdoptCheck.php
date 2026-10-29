<?php

// A host that already has the access feature's entitlements or complimentary_grants table, or spatie/laravel-settings' settings table, adopts a
// compatible one in place: the plan lists an adoption, not a collision; the migration creates and changes nothing on it (its rows and columns
// stay as they were), records it in kit_adoptions and creates only the tables the host lacks (settings_intents); rolling back keeps it. An
// incompatible table blocks the plan and the migration refuses it, naming the columns, before anything is created. A schema the kit cannot read
// is a refusal. kit:adopt --json with no feature lists each table's status. Synthetic tables and rows.
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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Console\AdoptCommand;
use Tey\Kit\Features\Workspaces\AdoptableTables;
use Tey\Kit\Features\Workspaces\Adoptions;
use Tey\Kit\HostRequirements;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;

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
    $names = array_column(Schema::getTables(), 'name');
    sort($names);

    return $names;
}

try {
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $scenarios = ['access_ok', 'access_bad', 'access_partial', 'settings_ok', 'settings_bad', 'listing'];
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
    $plan = function (string $feature, string $name) use ($stub): array {
        $path = 'database/migrations/kit/'.$feature.'/'.$name.'.php';
        $files = [$path => ['state' => 'create']];
        $bytes = [$path => (string) file_get_contents($stub($feature, $name))];

        return ['collisions' => HostRequirements::collisions($files, $bytes), 'adoptions' => HostRequirements::adoptions($files, $bytes), 'tables' => HostRequirements::adoptedTables($files, $bytes)];
    };
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
    $planned = $plan('access', $access);
    tableCheck($planned['collisions'] === [], 'compatible host access tables are not collisions');
    tableCheck(count($planned['adoptions']) === 2 && str_contains($planned['adoptions'][0], 'adopt the host\'s entitlements table in place') && str_contains($planned['adoptions'][1], 'adopt the host\'s complimentary_grants table in place') && str_contains($planned['adoptions'][1], 'nothing is created or changed'), 'the plan names each adoption');
    tableCheck($planned['tables'] === ['entitlements', 'complimentary_grants'], 'and its adopted tables');
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
    $planned = $plan('access', $access);
    $grants = implode("\n", array_filter($planned['collisions'], fn (string $line): bool => str_contains($line, 'complimentary_grants table')));
    $entitlements = implode("\n", array_filter($planned['collisions'], fn (string $line): bool => str_contains($line, 'entitlements table') && ! str_contains($line, 'complimentary')));
    tableCheck(count($planned['collisions']) === 2 && $planned['adoptions'] === [] && $planned['tables'] === [], 'incompatible host access tables block the plan, and none is adopted');
    tableCheck(str_contains($grants, 'missing columns workspace_id, intent_key, fingerprint, expires_at'), 'the conflict names the missing columns');
    tableCheck(str_contains($grants, 'not nullable, but the kit writes null: revoked_at'), 'a column the kit writes null into that is not nullable');
    tableCheck(str_contains($grants, 'host columns the kit\'s inserts would leave empty (not nullable, no default): user_id, plan_id, granted_at'), 'the host\'s required columns the kit would leave empty');
    tableCheck(str_contains($grants, 'no unique index on (granted_by, intent_key)'), 'and the missing unique index');
    tableCheck(str_contains($entitlements, 'missing columns kind') && str_contains($entitlements, 'revoked_at is varchar, not time'), 'an entitlements table conflict names its missing and mistyped columns');
    $refusal = (string) tableRefused(fn () => (require $stub('access', $access))->up());
    tableCheck(str_contains($refusal, 'cannot adopt the host\'s entitlements table (missing columns kind') && str_contains($refusal, 'complimentary_grants table (missing columns workspace_id, intent_key, fingerprint, expires_at'), 'the migration refuses, naming both tables\' columns');
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
    $planned = $plan('access', $access);
    tableCheck($planned['collisions'] === [] && $planned['tables'] === ['complimentary_grants'], 'with only complimentary_grants, the plan adopts it alone');
    (require $stub('access', $access))->up();
    tableCheck(Schema::hasTable('entitlements') && ! Adoptions::adopted('entitlements') && Adoptions::adopted('complimentary_grants'), 'the migration creates entitlements and adopts complimentary_grants');
    (require $stub('access', $access))->down();
    tableCheck(! Schema::hasTable('entitlements') && Schema::hasTable('complimentary_grants'), 'rolling back drops only the table the kit created');

    // (4) spatie's own settings table, with a host group in it: adopted, and only settings_intents is created.
    $use('settings_ok');
    (require dirname(__DIR__).'/vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    DB::table('settings')->insert(['group' => 'general', 'name' => 'site_name', 'locked' => false, 'payload' => json_encode('Northwind Farms'), 'created_at' => '2025-01-02 03:04:05', 'updated_at' => '2025-01-02 03:04:05']);
    $before = ['rows' => rowsOf('settings'), 'schema' => schemaOf(['settings']), 'tables' => tableNames()];
    $planned = $plan('settings', $settings);
    tableCheck($planned['collisions'] === [] && count($planned['adoptions']) === 1 && str_contains($planned['adoptions'][0], 'adopt the host\'s settings table in place') && $planned['tables'] === ['settings'], 'a compatible settings table is an adoption in the plan');
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
    $planned = $plan('settings', $settings);
    tableCheck(count($planned['collisions']) === 1 && str_contains($planned['collisions'][0], 'missing columns locked') && str_contains($planned['collisions'][0], 'payload is '.($pgsql ? 'int4' : 'integer').', not json') && str_contains($planned['collisions'][0], 'no unique index on (group, name)') && $planned['adoptions'] === [], 'an incompatible settings table blocks the plan, naming its columns and the missing unique');
    $refusal = (string) tableRefused(fn () => (require $stub('settings', $settings))->up());
    tableCheck(str_contains($refusal, 'settings table (missing columns locked') && rowsOf('settings') === $before['rows'] && tableNames() === $before['tables'], 'the migration refuses it, creating and changing nothing');

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
        $planned = $plan('access', $access);
        tableCheck(count($planned['collisions']) >= 1 && str_contains(implode("\n", $planned['collisions']), 'complimentary_grants table, which') && str_contains(implode("\n", $planned['collisions']), 'its schema could not be read'), 'an unreadable schema blocks the plan');
        tableCheck(str_contains((string) tableRefused(fn () => (require $stub('access', $access))->up()), 'its schema could not be read'), 'and the migration refuses it');
    } finally {
        // db.schema is resolved per default connection, never cached: forget the fake so the next scenario's connection is used.
        $app->forgetInstance('db.schema');
        Schema::clearResolvedInstance('db.schema');
    }
    tableCheck(! $real->hasTable('complimentary_grants') && tableNames() === $before['tables'], 'nothing was created');

    // (7) kit:adopt --json with no feature lists each table: adopted, adoptable or conflict, with the columns named.
    $use('listing');
    (require dirname(__DIR__).'/vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    Schema::create('complimentary_grants', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('user_id');
    });
    Schema::create('entitlements', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('workspace_id');
        $t->string('kind');
        $t->timestamp('revoked_at')->nullable();
    });
    Adoptions::record('settings');
    $app->instance(LifecycleHost::class, new class implements LifecycleHost
    {
        public function lifecycle(): Lifecycle
        {
            return new Lifecycle;
        }

        public function appId(): string
        {
            return 'host';
        }

        public function bindings(): array
        {
            return [];
        }

        public function inputPaths(): array
        {
            return [];
        }

        public function operationCheck(): Closure
        {
            return fn (): bool => true;
        }
    });
    $command = new AdoptCommand;
    $command->setLaravel($app);
    $exit = $command->run(new ArrayInput(['--json' => true]), $output = new BufferedOutput);
    $printed = trim($output->fetch());
    $listed = json_decode($printed, true, flags: JSON_THROW_ON_ERROR);
    $rows = array_column($listed['tables'] ?? [], null, 'table');
    tableCheck($exit === 0 && $listed['status'] === 'list', 'kit:adopt --json with no feature lists, exit 0');
    tableCheck(($rows['entitlements']['status'] ?? null) === 'adoptable' && $rows['entitlements']['feature'] === 'access', 'a compatible table not yet adopted is listed adoptable');
    tableCheck(($rows['settings']['status'] ?? null) === 'adopted' && $rows['settings']['feature'] === 'settings', 'a table kit_adoptions records is listed adopted');
    tableCheck(($rows['complimentary_grants']['status'] ?? null) === 'conflict' && str_contains(implode('; ', $rows['complimentary_grants']['conflicts']), 'missing columns workspace_id, granted_by, intent_key, fingerprint, revoked_by, revocation_reason, reason, expires_at, revoked_at'), 'an incompatible one is listed as a conflict naming its columns');
    tableCheck(count($rows) === 3 && AdoptableTables::settingsTable() === 'settings', 'and no table the host lacks');

    echo 'Table adopt-in-place on '.($pgsql ? 'PostgreSQL' : 'in-memory SQLite')." with synthetic host tables; no MySQL run, no kit:add end to end, no HTTP.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
