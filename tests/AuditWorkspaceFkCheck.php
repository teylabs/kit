<?php

// audit_events.workspace_id under the workspaces feature: audit rows outlive their workspace. A host with orphaned rows (a workspace_id naming a deleted
// workspace) migrates, the orphans are set to null and counted (printed and logged); deleting a workspace keeps its rows with a null workspace_id and their
// workspace_name; a database with 000204's key (no ON DELETE) is converted to ON DELETE SET NULL; running the migrations again changes nothing; a fresh
// install ends nullable with the SET NULL key; core alone is unchanged. Synthetic workspaces and events only.
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Log\Events\MessageLogged;
use Monolog\Handler\NullHandler;
use Tey\Kit\Features\Core\Audit;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function fkCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    // SQLite memory (foreign keys enforced) by default. DB_CONNECTION=pgsql runs against the PostgreSQL the DB_* variables name (CI's service container):
    // its public schema is dropped and recreated before each scenario, so point it only at a throwaway database.
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $connection = $pgsql
        ? ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer']
        : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];

    $base = sys_get_temp_dir().'/kit-audit-fk-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->instance('env', 'testing');
    $app->instance('config', new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'env' => 'testing', 'debug' => false],
        'database' => ['default' => 'testing', 'connections' => ['testing' => $connection]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    $app->register(Illuminate\Database\DatabaseServiceProvider::class);
    $app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
    $app->boot();
    $logged = [];
    $app['events']->listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });
    echo 'Database: '.DB::connection()->getDriverName()."\n";

    $dir = dirname(__DIR__).'/resources/features';
    $core = $dir.'/core/migrations/0001_01_02_000100_create_kit_core_tables.php.stub';
    $workspaces = $dir.'/workspaces/migrations/0001_01_02_000200_create_kit_workspaces_table.php.stub';
    $constrain = $dir.'/workspaces/migrations/0001_01_02_000204_constrain_audit_events_workspace_id.php.stub';
    $fix = array_map(fn (string $name): string => $dir.'/workspaces/migrations/'.$name.'.php.stub', ['0001_01_02_000205_make_audit_events_workspace_id_nullable', '0001_01_02_000206_null_orphaned_audit_events_workspace_ids', '0001_01_02_000207_audit_events_workspace_id_null_on_delete', '0001_01_02_000208_add_workspace_name_to_audit_events']);

    $reset = function () use ($pgsql): void {
        if ($pgsql) {
            DB::statement('drop schema if exists public cascade');
            DB::statement('create schema public');
        } else {
            Schema::dropAllTables();
        }
    };
    // Runs migration files in order, returning what they printed.
    $migrate = function (array $files): string {
        ob_start();
        try {
            foreach ($files as $file) {
                (require $file)->up();
            }
        } finally {
            $printed = (string) ob_get_clean();
        }

        return $printed;
    };
    $keys = fn (): array => array_values(array_filter(Schema::getForeignKeys('audit_events'), fn (array $foreign): bool => $foreign['columns'] === ['workspace_id']));
    $nullable = fn (): bool => (bool) collect(Schema::getColumns('audit_events'))->firstWhere('name', 'workspace_id')['nullable'];
    $setNull = fn (): bool => count($keys()) === 1 && $keys()[0]['on_delete'] === 'set null' && $keys()[0]['foreign_table'] === 'workspaces';
    $event = fn (int $workspace, string $name = 'note.created'): array => ['actor_id' => 1, 'workspace_id' => $workspace, 'event' => $name, 'details' => '{}', 'created_at' => now()];
    $deletes = function (int $id): ?string {
        try {
            DB::table('workspaces')->where('id', $id)->delete();
        } catch (Throwable $error) {
            return $error->getMessage();
        }

        return null;
    };

    // Core alone: the column stays required, there is no key, and Audit writes no workspace_name.
    $reset();
    $migrate([$core]);
    (new Audit)->write(1, 7, 'note.created', []);
    fkCheck(! $nullable() && $keys() === [] && ! Schema::hasColumn('audit_events', 'workspace_name') && DB::table('audit_events')->count() === 1, 'core alone: workspace_id stays required and unconstrained, and Audit writes as before');

    // A fresh install (core, then workspaces): nullable, ON DELETE SET NULL, nothing printed.
    $reset();
    $printed = $migrate([$core, $workspaces, $constrain, ...$fix]);
    fkCheck($nullable() && $setNull() && Schema::hasColumn('audit_events', 'workspace_name') && $printed === '', 'a fresh install ends with a nullable workspace_id and one ON DELETE SET NULL key, printing nothing');
    $northwind = DB::table('workspaces')->insertGetId(['name' => 'Northwind Farms']);
    $acme = DB::table('workspaces')->insertGetId(['name' => 'Acme Travel']);
    (new Audit)->write(1, $northwind, 'note.created', ['note' => 1]);
    (new Audit)->write(1, $acme, 'note.created', ['note' => 2]);
    fkCheck(DB::table('audit_events')->orderBy('id')->pluck('workspace_name')->all() === ['Northwind Farms', 'Acme Travel'], 'Audit stores the workspace name on each row it writes');
    fkCheck($deletes($northwind) === null, 'deleting a workspace that has audit events succeeds');
    $rows = DB::table('audit_events')->orderBy('id')->get();
    fkCheck(count($rows) === 2 && $rows[0]->workspace_id === null && $rows[0]->workspace_name === 'Northwind Farms' && (int) $rows[1]->workspace_id === $acme, 'its audit rows survive with a null workspace_id and their workspace_name; other workspaces\' rows are untouched');
    $before = $keys();
    $printed = $migrate([$constrain, ...$fix]);
    fkCheck($printed === '' && $keys() === $before && $nullable() && DB::table('audit_events')->orderBy('id')->pluck('workspace_name')->all() === ['Northwind Farms', 'Acme Travel'], 'running the migrations again changes nothing');

    // A host that ran 000204 (its key has no ON DELETE): the key is converted, and only that key.
    $reset();
    $migrate([$core, $workspaces]);
    Schema::create('audit_notes', function (Illuminate\Database\Schema\Blueprint $t): void {
        $t->id();
    });
    Schema::table('audit_events', fn (Illuminate\Database\Schema\Blueprint $t) => $t->unsignedBigInteger('note_id')->nullable());
    Schema::table('audit_events', fn (Illuminate\Database\Schema\Blueprint $t) => $t->foreign('note_id')->references('id')->on('audit_notes'));
    $northwind = DB::table('workspaces')->insertGetId(['name' => 'Northwind Farms']);
    DB::table('audit_events')->insert($event($northwind));
    $migrate([$constrain]);
    fkCheck(count($keys()) === 1 && $keys()[0]['on_delete'] !== 'set null' && $deletes($northwind) !== null, 'with 000204\'s key (on delete '.($keys()[0]['on_delete'] ?? '?').'), deleting a workspace that has audit events fails');
    $printed = $migrate($fix);
    fkCheck($setNull() && $nullable() && $printed === '', 'the new migrations convert it to ON DELETE SET NULL');
    $other = array_values(array_filter(Schema::getForeignKeys('audit_events'), fn (array $foreign): bool => $foreign['columns'] === ['note_id']));
    fkCheck(count($other) === 1 && $other[0]['foreign_table'] === 'audit_notes', 'another foreign key on audit_events is kept');
    fkCheck(DB::table('audit_events')->value('workspace_name') === 'Northwind Farms', 'existing rows are backfilled with their workspace name');
    fkCheck($deletes($northwind) === null && DB::table('audit_events')->count() === 1 && DB::table('audit_events')->value('workspace_id') === null && DB::table('audit_events')->value('workspace_name') === 'Northwind Farms', 'and deleting the workspace now keeps its audit row');
    $before = $keys();
    fkCheck($migrate([$constrain, ...$fix]) === '' && $keys() === $before, 'running them again on the converted key does nothing');

    // A host with orphaned rows that has not run 000204: every migration succeeds, the orphans are nulled, and the count is printed and logged.
    $reset();
    $migrate([$core, $workspaces]);
    $acme = DB::table('workspaces')->insertGetId(['name' => 'Acme Travel']);
    DB::table('audit_events')->insert([$event($acme), $event($acme + 40), $event($acme + 41), $event($acme + 41)]);
    $printed = $migrate([$constrain, ...$fix]);
    fkCheck($setNull() && $nullable(), 'with orphaned audit rows, 000204 through 000208 succeed and end with the SET NULL key');
    fkCheck(str_contains($printed, 'set workspace_id to null on 3 row(s)'), 'the orphan count is printed during migrate: '.trim($printed));
    $counted = array_values(array_filter($logged, fn (MessageLogged $message): bool => ($message->context['orphaned_audit_events'] ?? null) === 3));
    fkCheck(count($counted) === 1 && $counted[0]->level === 'warning', 'and logged as a warning with the count');
    fkCheck(DB::table('audit_events')->count() === 4 && DB::table('audit_events')->whereNull('workspace_id')->count() === 3 && (int) DB::table('audit_events')->whereNotNull('workspace_id')->value('workspace_id') === $acme, 'the orphaned rows are kept with a null workspace_id; the valid row keeps its workspace');
    fkCheck(DB::table('audit_events')->whereNotNull('workspace_name')->pluck('workspace_name')->all() === ['Acme Travel'], 'only the row with a workspace gets a backfilled name');
    fkCheck($migrate([$constrain, ...$fix]) === '', 'running them again prints and changes nothing');

    // A full reset on an adopting host runs the host's create_workspaces down() before the kit's: the README's down() drops the kit's audit key first, so the
    // table drops and audit_events keeps its rows. (PostgreSQL refuses the plain drop while the key is there; SQLite lets it through, so that half runs in CI.)
    $reset();
    $migrate([$core, $workspaces, $constrain, ...$fix]);
    $acme = DB::table('workspaces')->insertGetId(['name' => 'Acme Travel']);
    (new Audit)->write(1, $acme, 'note.created', []);
    if ($pgsql) {
        $refused = null;
        try {
            DB::transaction(fn () => Schema::drop('workspaces'));
        } catch (Throwable $error) {
            $refused = $error->getMessage();
        }
        fkCheck($refused !== null && Schema::hasTable('workspaces'), 'without dropping the key first, the host\'s down() fails to drop workspaces');
    }
    $hostDown = function (): void {
        if (Schema::hasTable('audit_events') && Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (Schema::getForeignKeys('audit_events') as $key) {
                if ($key['columns'] === ['workspace_id'] && $key['foreign_table'] === 'workspaces') {
                    Schema::table('audit_events', fn (Illuminate\Database\Schema\Blueprint $table) => $table->dropForeign($key['name']));
                }
            }
        }
        Schema::dropIfExists('workspaces');
    };
    $hostDown();
    fkCheck(! Schema::hasTable('workspaces') && DB::table('audit_events')->count() === 1 && ($pgsql ? $keys() === [] : true), 'the README\'s create_workspaces down() drops the kit\'s audit key ('.($pgsql ? 'PostgreSQL' : 'SQLite: the table drops with it').'), then the table; audit_events keeps its rows');

    echo 'Audit workspace key on '.($pgsql ? 'PostgreSQL' : 'in-memory SQLite (foreign keys on)')." with migration stubs run directly; no MySQL run, no `php artisan migrate` end to end.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
