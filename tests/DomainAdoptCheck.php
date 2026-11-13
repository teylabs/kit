<?php

// The domain tables become the app's (0.19): `kit:scaffold <feature> --adopt` writes the app's models, one migration and the kit.models entries; the migration
// renames the kit's tables with every row on an existing database and creates them on a fresh one, refuses when both names exist, and rolls back. An app that
// does not adopt keeps the kit's tables and classes. A configured class that is not a model with its contract stops the boot. Synthetic apps in temp directories,
// synthetic rows; no network. SQLite files by default; DB_CONNECTION=pgsql runs each app in its own kit_domain_* schema of the PostgreSQL the DB_* variables
// name, dropped and recreated: point it only at a throwaway database.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Tey\Concierge\Contracts\Member as MemberContract;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\DomainModels;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Notes\Models\Note;
use Tey\Kit\Features\SessionUi\Operations\DeleteAccount;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\KitServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const FEATURES = ['core', 'workspaces', 'access', 'shell', 'session-ui', 'workspace-ui', 'notes', 'finalize', 'deliver'];
const ADOPTABLE = ['deliver', 'finalize', 'notes', 'access', 'session-ui'];
// The kit's name => the app's.
const PAIRS = [
    'kit_deliveries' => 'deliveries', 'kit_delivery_recipients' => 'delivery_recipients', 'kit_delivery_events' => 'delivery_events',
    'kit_finalize_confirmations' => 'finalization_confirmations', 'kit_finalizations' => 'finalizations',
    'kit_notes' => 'notes', 'kit_note_intents' => 'note_intents', 'kit_security_events' => 'security_events',
    'entitlements' => 'entitlements', 'complimentary_grants' => 'complimentary_grants',
];
const BASELINES = ['create_kit_deliver_tables', 'add_finalization_ref_to_kit_deliveries', 'create_kit_finalize_tables', 'add_snapshot_to_kit_finalizations', 'create_kit_notes_table', 'create_kit_access_tables', 'create_kit_security_events_table', 'add_user_foreign_key_to_kit_security_events_table'];

/** A model that is not the kit's note: no contract, no trait. */
final class NotANote extends Model {}

/** A synthetic member of workspaces, for an account deletion. */
final class Member extends User implements MemberContract
{
    use HasWorkspaces;

    public $timestamps = false;

    protected $table = 'users';
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/**
 * config/kit.php as an app has it: the package's file with the features and user model set (`current`), or a pre-0.19 file with one-line models (`old`).
 * $models (key => a PHP literal) replace the package file's entries.
 *
 * @param  array<string, string>  $models
 */
function kitConfig(string $style, array $models = []): string
{
    $features = var_export(FEATURES, true);
    if ($style === 'old') {
        return "<?php\n\nreturn [\n    'features' => ".$features.",\n    'models' => ['user' => Illuminate\\Foundation\\Auth\\User::class],\n];\n";
    }
    $source = (string) file_get_contents(dirname(__DIR__).'/config/kit.php');
    $source = str_replace("    'features' => null,", "    'features' => ".$features.',', $source);
    $source = (string) preg_replace("/(    'models' => \\[\\n        'user' => )null,/", '${1}Illuminate\\Foundation\\Auth\\User::class,', $source, 1);
    foreach ($models as $key => $literal) {
        $source = (string) preg_replace("/^(        '".$key."' => )[^\\n]+,$/m", '${1}'.addcslashes($literal, '\\$').',', $source, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('No kit.models.'.$key.' line in config/kit.php');
        }
    }

    return $source;
}

/**
 * A synthetic app root with its own database and the given files (path => bytes); config/kit.php is $kit.
 *
 * @param  array<string, string>  $files
 */
function root(string $kit, array $files = [], string $env = 'testing'): string
{
    $root = sys_get_temp_dir().'/kit-domain-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config', 'database/migrations', 'app/Models'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $GLOBALS['loader']->addPsr4('App\\', $root.'/app/');
    file_put_contents($root.'/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
    touch($root.'/database/database.sqlite');
    $connection = ['driver' => 'sqlite', 'database' => $root.'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true];
    if (getenv('DB_CONNECTION') === 'pgsql') {
        $schema = 'kit_domain_'.(++$GLOBALS['schemas']);
        $connection = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => $schema, 'sslmode' => 'prefer'];
        $pdo = new PDO('pgsql:host='.$connection['host'].';port='.$connection['port'].';dbname='.$connection['database'], $connection['username'], $connection['password']);
        $pdo->exec('drop schema if exists '.$schema.' cascade; create schema '.$schema);
    }
    file_put_contents($root.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::create('users', fn (\$t) => \$t->id());\n    }\n\n    public function down(): void\n    {\n        Illuminate\\Support\\Facades\\Schema::dropIfExists('users');\n    }\n};\n");
    foreach ($files as $path => $bytes) {
        @mkdir(dirname($root.'/'.$path), 0700, true);
        file_put_contents($root.'/'.$path, $bytes);
    }
    file_put_contents($root.'/config/kit.php', $kit);
    file_put_contents($root.'/config/database.php', '<?php return '.var_export(['default' => 'kit', 'migrations' => ['table' => 'migrations'], 'connections' => ['kit' => $connection]], true).';');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\\Handler\\NullHandler::class]]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['env' => $env, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/auth.php', '<?php return '.var_export(['providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]]], true).';');
    file_put_contents($root.'/config/permission.php', '<?php return '.var_export(['teams' => false, 'table_names' => ['roles' => 'roles', 'permissions' => 'permissions', 'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions'], 'column_names' => ['role_pivot_key' => null, 'permission_pivot_key' => null, 'model_morph_key' => 'model_id', 'team_foreign_key' => 'team_id']], true).';');

    return $root;
}

function boot(string $root): Application
{
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([KitServiceProvider::class, FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    return $app;
}

/** Runs an artisan command; returns [exit code, output]. */
function artisan(Application $app, string $command, array $arguments = []): array
{
    $kernel = $app->make(Kernel::class);
    $code = $kernel->call($command, $arguments);

    return [$code, $kernel->output()];
}

/** Runs `migrate`; returns the migrations it ran, in order. */
function migrate(Application $app): array
{
    $before = Schema::hasTable('migrations') ? $app['db']->table('migrations')->pluck('migration')->all() : [];
    [$code, $output] = artisan($app, 'migrate', ['--force' => true]);
    if ($code !== 0) {
        throw new RuntimeException('migrate exited '.$code.': '.$output);
    }

    return array_values(array_diff($app['db']->table('migrations')->orderBy('id')->pluck('migration')->all(), $before));
}

/** Runs $write with foreign keys unchecked: SQLite's pragma off; PostgreSQL skips their triggers for this session (the CI role is a superuser). */
function unchecked(Closure $write): mixed
{
    $connection = Schema::getConnection();
    $pgsql = $connection->getDriverName() === 'pgsql';
    $pgsql ? $connection->statement('set session_replication_role = replica') : $connection->statement('pragma foreign_keys = off');
    try {
        return $write();
    } finally {
        $pgsql ? $connection->statement('set session_replication_role = origin') : $connection->statement('pragma foreign_keys = on');
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
    return array_map(fn (object $row): array => (array) $row, Schema::getConnection()->table($table)->orderBy('id')->get()->all());
}

/** @return array<string, string> what the adopt migrations recorded in kit_meta, by table: renamed, created or left */
function records(): array
{
    $records = [];
    foreach (Schema::getConnection()->table('kit_meta')->where('key', 'like', 'adopt:%')->get() as $row) {
        $records[substr((string) $row->key, strrpos((string) $row->key, ':') + 1)] = (string) $row->value;
    }
    ksort($records);

    return $records;
}

/**
 * @param  list<string>  $values
 * @return list<string>
 */
function sorted(array $values): array
{
    sort($values);

    return $values;
}

/** @return list<string> */
function columns(string $table): array
{
    return sorted(Schema::getColumnListing($table));
}

/** @return list<string> the index and (PostgreSQL) foreign key names of a table */
function keyNames(string $table): array
{
    return [...array_column(Schema::getIndexes($table), 'name'), ...array_filter(array_column(Schema::getForeignKeys($table), 'name'))];
}

/** The table each key's columns are in: the kit's name, or the app's. */
function tableOf(string $key, bool $adopted): string
{
    foreach (DomainModels::FEATURES as $keys) {
        if (isset($keys[$key])) {
            return $keys[$key][$adopted ? 3 : 2];
        }
    }
    throw new RuntimeException($key);
}

function refusal(Closure $run): ?string
{
    try {
        $run();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
}

$schemas = 0;
// Run in a process of its own (by the check below), so no feature registered before it: an app without session-ui deletes an account.
if (($argv[2] ?? null) === 'without-session-ui') {
    $schemas = 100;
    $app = boot(root("<?php\n\nreturn ['features' => ['core', 'workspaces', 'access'], 'models' => ['user' => Member::class]];\n"));
    config(['concierge.models.user' => Member::class, 'permission' => array_replace_recursive(require dirname(__DIR__).'/vendor/spatie/laravel-permission/config/permission.php', (array) config('permission'))]);
    migrate($app);
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') ?: [] as $file) {
        (require $file)->up();
    }
    $user = new Member;
    $user->save();
    DeleteAccount::deleteRows($user, []);
    echo Member::query()->whereKey($user->getKey())->exists() ? 'kept' : 'deleted';
    exit(0);
}
try {
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    echo 'Database: '.($pgsql ? 'pgsql' : 'sqlite')."\n";

    // An app that does not adopt: the kit's classes on the kit's tables, exactly as before 0.19.
    $rootA = root(kitConfig('current'));
    $app = boot($rootA);
    migrate($app);
    $kitNames = array_keys(array_filter(PAIRS, fn (string $to, string $from): bool => $from !== $to, ARRAY_FILTER_USE_BOTH));
    check(array_filter($kitNames, fn (string $table): bool => ! Schema::hasTable($table)) === [] && array_filter(PAIRS, fn (string $to, string $from): bool => $from !== $to && Schema::hasTable($to), ARRAY_FILTER_USE_BOTH) === [], 'an app that does not adopt migrates the domain tables under the kit\'s names only');
    check(DomainModels::problems() === [] && Kit::model('note') === Note::class && Kit::table('delivery') === 'kit_deliveries' && Kit::table('note_intent') === 'kit_note_intents' && Kit::table('entitlement') === 'entitlements', 'and Kit::model() and Kit::table() name the kit\'s own classes and tables');
    check(Kit::model('media_upload') === Tey\Kit\Media\Models\MediaUpload::class && Kit::table('media_upload') === 'kit_media_uploads', 'Kit::model() and Kit::table() resolve media_upload too, a kit.models key that no feature registers');
    check(Relation::getMorphedModel('kit-note') === Note::class && Relation::getMorphedModel('kit-delivery') === Tey\Kit\Features\Deliver\Models\DeliveryRecord::class, 'the stable morph aliases (kit-note, kit-delivery) name the kit\'s classes');
    $drift = [];
    foreach (DomainModels::COLUMNS as $key => $expected) {
        if ($key !== 'media_upload' && columns(tableOf($key, false)) !== sorted($expected)) {
            $drift[] = $key;
        }
    }
    check($drift === [], 'DomainModels::COLUMNS is each kit table\'s columns exactly, as the kit\'s migrations leave them');

    // Synthetic rows in every domain table, and media rows that name a note by its alias and by the kit's class (stored before the alias existed).
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $before = [];
    foreach (array_keys(PAIRS) as $table) {
        seed($table);
        $before[$table] = rows($table);
    }
    $noteId = (int) $before['kit_notes'][0]['id'];
    foreach (['kit-note', Note::class] as $index => $type) {
        Schema::getConnection()->table('media')->insert(['model_type' => $type, 'model_id' => $noteId, 'uuid' => sprintf('00000000-0000-4000-8000-00000000000%d', $index), 'collection_name' => 'attachments', 'name' => 'synthetic', 'file_name' => 'synthetic.pdf', 'mime_type' => 'application/pdf', 'disk' => 'local', 'size' => 10, 'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]', 'order_column' => $index + 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
    }

    // kit:scaffold refuses what it cannot adopt, before writing anything.
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'workspaces', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'concierge.models.workspace'), 'kit:scaffold workspaces --adopt refuses: the workspaces table is already the app\'s through its own binding');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'core', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'no domain tables'), 'a feature with no domain tables refuses');
    [$code] = artisan($app, 'kit:scaffold', ['feature' => 'notes', '--adopt' => true, '--diff' => true]);
    check($code === 1 && glob($rootA.'/app/Models/*') === [], '--adopt with --diff refuses and writes nothing');

    // Adopt every feature on the existing database.
    $config = (string) file_get_contents($rootA.'/config/kit.php');
    foreach (ADOPTABLE as $feature) {
        [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => $feature, '--adopt' => true]);
        check($code === 0 && str_contains($output, 'updated config/kit.php'), 'kit:scaffold '.$feature.' --adopt writes its files and config entries');
    }
    $models = array_map('basename', glob($rootA.'/app/Models/*.php') ?: []);
    sort($models);
    $migrations = array_map(fn (string $file): string => preg_replace('/\A\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file)), glob($rootA.'/database/migrations/*_adopt_*.php') ?: []);
    sort($migrations);
    check($models === ['Delivery.php', 'DeliveryEvent.php', 'DeliveryRecipient.php', 'Finalization.php', 'FinalizationConfirmation.php', 'Note.php', 'SecurityEvent.php'] && $migrations === ['adopt_access_tables.php', 'adopt_deliver_tables.php', 'adopt_finalize_tables.php', 'adopt_notes_tables.php', 'adopt_session_ui_tables.php'], 'it wrote one model per domain model and one migration per feature');
    check(str_contains((string) file_get_contents($rootA.'/app/Models/Delivery.php'), "class Delivery extends Model implements DeliveryContract\n{\n    use IsDelivery;\n\n    protected \$table = 'deliveries';"), 'the app model uses the kit\'s trait, implements its contract and names the app\'s table');
    $written = (static fn (string $file): array => require $file)($rootA.'/config/kit.php');
    check($written['models']['delivery'] === 'App\\Models\\Delivery' && $written['models']['note'] === 'App\\Models\\Note' && $written['tables'] === ['note_intent' => 'note_intents', 'entitlement' => 'entitlements', 'complimentary_grant' => 'complimentary_grants'] && $written['models']['user'] === User::class && $written['features'] === FEATURES, 'config/kit.php names the app\'s models and tables, and nothing else in it changed meaning');
    $lines = fn (string $source): array => array_values(array_filter(explode("\n", $source), fn (string $line): bool => ! preg_match("/^\\s*'(delivery|delivery_recipient|delivery_event|finalization|finalization_confirmation|note|security_event)' =>|'tables' =>/", $line)));
    check($lines((string) file_get_contents($rootA.'/config/kit.php')) === $lines($config), 'every other line of config/kit.php is unchanged');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'adopted before') && count(glob($rootA.'/database/migrations/*_adopt_deliver_tables.php') ?: []) === 1, 'adopting a feature again refuses: its migration is there');

    // The app boots on its own models; migrate runs only the adopt migrations, and every row survives column for column.
    $app = boot($rootA);
    check(DomainModels::problems() === [] && Kit::model('delivery') === 'App\\Models\\Delivery' && Kit::table('delivery') === 'deliveries' && Kit::table('note_intent') === 'note_intents', 'the adopted app boots, and Kit::model() and Kit::table() name its own');
    $ran = migrate($app);
    check(count($ran) === 5 && count(preg_grep('/_adopt_[a-z_]+_tables\z/', $ran)) === 5, 'migrate runs the five adopt migrations and nothing else');
    $expected = array_map(fn (string $to): string => 'renamed', array_flip(array_filter(PAIRS, fn (string $to, string $from): bool => $from !== $to, ARRAY_FILTER_USE_BOTH))) + ['entitlements' => 'left', 'complimentary_grants' => 'left'];
    ksort($expected);
    $deliverKey = Schema::getConnection()->table('kit_meta')->where('key', 'like', 'adopt:%:deliveries')->value('key');
    check(records() === $expected && preg_match('/\Aadopt:\d{4}_\d{2}_\d{2}_\d{6}_adopt_deliver_tables:deliveries\z/', (string) $deliverKey) === 1, 'kit_meta records, per adopt migration and table, each renamed table as renamed and the access tables the kit created as left');
    $renamedRows = array_filter(PAIRS, fn (string $to, string $from): bool => rows($to) !== $before[$from] || ($from !== $to && Schema::hasTable($from)), ARRAY_FILTER_USE_BOTH);
    check($renamedRows === [], 'every row of every domain table is there under the app\'s name, column for column, and no kit name is left ('.count(PAIRS).' tables)');
    $stale = array_filter(PAIRS, fn (string $to, string $from): bool => $from !== $to && preg_grep('/\A'.$from.'_/', keyNames($to)) !== [], ARRAY_FILTER_USE_BOTH);
    check($stale === [] && preg_grep('/\Adeliveries_/', keyNames('deliveries')) !== [], 'the indexes and keys named after a kit table take the app\'s name');
    $parents = fn (string $table): array => array_column(Schema::getForeignKeys($table), 'foreign_table');
    check(in_array('deliveries', $parents('delivery_recipients'), true) && in_array('delivery_recipients', $parents('delivery_events'), true) && in_array('users', $parents('security_events'), true), 'foreign keys still resolve: recipients to deliveries, events to recipients, the security log to users');
    if ($pgsql) {
        check(str_ends_with((string) Schema::getConnection()->scalar("select pg_get_serial_sequence('deliveries', 'id')"), 'deliveries_id_seq'), 'PostgreSQL: the id sequence takes the app\'s name');
    }
    $next = unchecked(fn () => Schema::getConnection()->table('note_intents')->insertGetId(['workspace_id' => 1, 'actor_id' => 1, 'intent_key' => 'after-adopt', 'fingerprint' => 'synthetic', 'result' => '{}']));
    check(is_int($next) || ctype_digit((string) $next), 'a renamed table still numbers new rows');
    Schema::getConnection()->table('note_intents')->where('intent_key', 'after-adopt')->delete();
    check(Relation::getMorphedModel('kit-note') === 'App\\Models\\Note' && Relation::getMorphedModel(Note::class) === 'App\\Models\\Note' && Relation::getMorphedModel('kit-delivery-recipient') === 'App\\Models\\DeliveryRecipient', 'the morph aliases name the app\'s models, and the kit\'s class name resolves there too');
    $owners = Schema::getConnection()->table('media')->orderBy('id')->get()->map(fn (object $media): ?Model => (Relation::getMorphedModel($media->model_type) ?? $media->model_type)::query()->withoutGlobalScopes()->find($media->model_id))->all();
    check(count($owners) === 2 && array_filter($owners, fn (?Model $owner): bool => ! $owner instanceof App\Models\Note || $owner->getTable() !== 'notes' || (int) $owner->getKey() !== $noteId) === [], 'media rows stored under kit-note and under the kit\'s class both resolve to the app\'s note');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--check' => true]);
    check($code === 0 && str_contains($output, 'ok deliveries') && str_contains($output, 'Adopted'), 'kit:scaffold deliver --check finds every column the kit relies on');

    // Rolling back restores the kit's names, rows and keys; migrating again renames them again.
    [$code, $output] = artisan($app, 'migrate:rollback', ['--force' => true]);
    $restored = array_filter(PAIRS, fn (string $to, string $from): bool => ! Schema::hasTable($from) || ($from !== $to && Schema::hasTable($to)) || rows($from) !== $before[$from], ARRAY_FILTER_USE_BOTH);
    check($code === 0 && $restored === [] && preg_grep('/\Adeliveries_/', keyNames('kit_deliveries')) === [] && preg_grep('/\Akit_deliveries_/', keyNames('kit_deliveries')) !== [], 'rollback restores the kit\'s names, every row and the kit\'s key names'.($code === 0 ? '' : ': '.$output));
    check(records() === [] && Schema::hasTable('entitlements') && Schema::hasTable('complimentary_grants'), 'and leaves the tables it left (entitlements, complimentary_grants) and no record behind');
    $again = migrate($app);
    check(count($again) === 5 && array_filter(PAIRS, fn (string $to, string $from): bool => rows($to) !== $before[$from], ARRAY_FILTER_USE_BOTH) === [], 'migrating again renames them again, rows intact');
    $deliverPath = ['--force' => true, '--path' => glob($rootA.'/database/migrations/*_adopt_deliver_tables.php'), '--realpath' => true];
    Schema::create('kit_deliveries', fn ($t) => $t->id());
    $refused = refusal(fn () => artisan($app, 'migrate:rollback', $deliverPath));
    check($refused !== null && str_contains($refused, 'Both deliveries and kit_deliveries exist') && Schema::hasTable('delivery_events') && Schema::hasTable('delivery_recipients') && ! Schema::hasTable('kit_delivery_events') && records()['delivery_events'] === 'renamed', 'down() checks every pair before renaming back: with kit_deliveries in the way it refuses, and delivery_events, renamed back first otherwise, keeps its name and record');
    Schema::drop('kit_deliveries');

    // --check is read-only and names a missing column.
    Schema::table('deliveries', fn ($t) => $t->dropColumn('finalization_ref'));
    $columns = columns('deliveries');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--check' => true]);
    check($code === 1 && str_contains($output, 'missing deliveries.finalization_ref') && columns('deliveries') === $columns, 'kit:scaffold --check names a column the app\'s table lacks, exits 1 and changes nothing');

    // A fresh database with the adopted app's files: the tables are created under the app's names, and no kit migration of theirs runs.
    $files = ['config/kit.php' => (string) file_get_contents($rootA.'/config/kit.php')];
    foreach ([...glob($rootA.'/app/Models/*.php') ?: [], ...glob($rootA.'/database/migrations/*_adopt_*.php') ?: []] as $file) {
        $files[substr($file, strlen($rootA) + 1)] = (string) file_get_contents($file);
    }
    $rootB = root($files['config/kit.php'], $files);
    $fresh = boot($rootB);
    $ran = migrate($fresh);
    $baselines = array_filter($ran, fn (string $migration): bool => in_array(preg_replace('/\A\d{4}_\d{2}_\d{2}_(?:\d{6}_)+/', '', $migration), BASELINES, true));
    check($baselines === [] && count(preg_grep('/_adopt_[a-z_]+_tables\z/', $ran)) === 5, 'a fresh database runs the adopt migrations and none of the kit\'s migrations for those tables');
    check(array_filter(PAIRS, fn (string $to, string $from): bool => ! Schema::hasTable($to) || ($from !== $to && Schema::hasTable($from)), ARRAY_FILTER_USE_BOTH) === [], 'and has every domain table under the app\'s name only');
    check(array_values(array_unique(records())) === ['created'] && count(records()) === count(PAIRS), 'kit_meta records every table as created');
    $drift = [];
    foreach (DomainModels::COLUMNS as $key => $expected) {
        if ($key !== 'media_upload' && columns(tableOf($key, true)) !== sorted($expected)) {
            $drift[] = $key;
        }
    }
    check($drift === [] && in_array('deliveries', array_column(Schema::getForeignKeys('delivery_recipients'), 'foreign_table'), true), 'the created tables have the kit\'s columns exactly, and their foreign keys');
    [$code] = artisan($fresh, 'migrate:rollback', ['--force' => true, '--path' => glob($rootB.'/database/migrations/*_adopt_*.php'), '--realpath' => true]);
    check($code === 0 && array_filter(PAIRS, fn (string $to): bool => Schema::hasTable($to)) === [] && Schema::hasTable('workspaces'), 'rolling the adopt migrations back on the fresh database drops the tables they created, and nothing else');

    // A table the app already had under the app's name, which the kit never created here: up() records it as left, and down() leaves it, rows and all.
    // down() drops only what up() recorded as created; a table it has no record of stays as it is, never a guess.
    $adoptPaths = ['--force' => true, '--path' => glob($rootB.'/database/migrations/*_adopt_*.php'), '--realpath' => true];
    foreach (['notes', 'entitlements'] as $table) {
        Schema::create($table, fn ($t) => $t->id());
        Schema::getConnection()->table($table)->insert(['id' => 42]);
    }
    migrate($fresh);
    check(records()['notes'] === 'left' && records()['entitlements'] === 'left' && records()['note_intents'] === 'created' && records()['complimentary_grants'] === 'created', 'up() records an app table that was there before adoption as left, beside the tables it created');
    [$code] = artisan($fresh, 'migrate:rollback', $adoptPaths);
    $kept = fn (string $table): bool => Schema::hasTable($table) && rows($table) === [['id' => 42]];
    check($code === 0 && $kept('notes') && $kept('entitlements') && ! Schema::hasTable('note_intents') && ! Schema::hasTable('complimentary_grants') && ! Schema::hasTable('deliveries'), 'an app table that was there before adoption (notes, entitlements) survives the rollback with its rows; the tables up() created are dropped');
    migrate($fresh);
    Schema::getConnection()->table('kit_meta')->where('key', 'like', 'adopt:%_adopt_notes_tables:%')->delete();
    [$code] = artisan($fresh, 'migrate:rollback', $adoptPaths);
    check($code === 0 && $kept('notes') && Schema::hasTable('note_intents') && ! Schema::hasTable('deliveries') && ! Schema::hasTable('complimentary_grants') && records() === [], 'without a record, down() leaves the table as it is (notes, and the note_intents up() created); the tables with a record are undone');

    // Account deletion in an app without session-ui, which never registers the security log's model: the log is skipped, not resolved.
    $child = proc_open([PHP_BINARY, __FILE__, $argv[1], 'without-session-ui'], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    check(proc_close($child) === 0 && str_ends_with($out, 'deleted'), 'an app without session-ui deletes an account: the security log it never registered is skipped'.(str_ends_with($out, 'deleted') ? '' : ': '.substr($out, 0, 400)));

    // Both names present: kit:scaffold refuses and writes nothing; the migration refuses before renaming any table. A pre-0.19 config (one-line models).
    $rootC = root(kitConfig('old'));
    $app = boot($rootC);
    migrate($app);
    Schema::create('delivery_recipients', fn ($t) => $t->id());
    $config = (string) file_get_contents($rootC.'/config/kit.php');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'kit_delivery_recipients and delivery_recipients') && glob($rootC.'/app/Models/*') === [] && glob($rootC.'/database/migrations/*_adopt_*') === [] && file_get_contents($rootC.'/config/kit.php') === $config, 'with both kit_delivery_recipients and delivery_recipients present, kit:scaffold refuses, naming both, and writes nothing');
    Schema::drop('delivery_recipients');
    foreach (['deliver', 'access'] as $feature) {
        [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => $feature, '--adopt' => true]);
        check($code === 0, 'without the conflict, kit:scaffold '.$feature.' --adopt edits a pre-0.19 config/kit.php');
    }
    $written = (static fn (string $file): array => require $file)($rootC.'/config/kit.php');
    check($written['models'] === ['delivery' => 'App\\Models\\Delivery', 'delivery_recipient' => 'App\\Models\\DeliveryRecipient', 'delivery_event' => 'App\\Models\\DeliveryEvent', 'user' => User::class] && $written['tables'] === ['entitlement' => 'entitlements', 'complimentary_grant' => 'complimentary_grants'], 'which then names the app\'s models and tables');
    Schema::create('delivery_recipients', fn ($t) => $t->id());
    $app = boot($rootC);
    $refused = refusal(fn () => migrate($app));
    check($refused !== null && str_contains($refused, 'Both kit_delivery_recipients and delivery_recipients exist') && Schema::hasTable('kit_deliveries') && ! Schema::hasTable('deliveries') && Schema::hasTable('kit_delivery_events'), 'migrate refuses on the second pair before renaming the first: kit_deliveries keeps its name');
    Schema::drop('delivery_recipients');

    // An index name the rename would give is already on another table: migrate refuses before renaming any table or key, and records nothing.
    $deliverRecords = fn (): int => Schema::getConnection()->table('kit_meta')->where('key', 'like', 'adopt:%_adopt_deliver_tables:%')->count();
    $index = array_values(preg_grep('/\Akit_delivery_recipients_.+_unique\z/', keyNames('kit_delivery_recipients')) ?: [])[0] ?? throw new RuntimeException('no unique index on kit_delivery_recipients');
    $clash = 'delivery_recipients'.substr($index, strlen('kit_delivery_recipients'));
    Schema::create('unrelated_codes', fn ($t) => $t->string('code')->index($clash));
    $refused = refusal(fn () => migrate($app));
    check($refused !== null && str_contains($refused, $clash) && str_contains($refused, 'renames nothing') && Schema::hasTable('kit_deliveries') && Schema::hasTable('kit_delivery_recipients') && ! Schema::hasTable('deliveries') && ! Schema::hasTable('delivery_recipients') && in_array($index, keyNames('kit_delivery_recipients'), true) && $deliverRecords() === 0, 'with an index name the rename needs already on another table, migrate refuses naming it: no table or key renamed, no record written');
    Schema::drop('unrelated_codes');
    $deliverFile = (string) (glob($rootC.'/database/migrations/*_adopt_deliver_tables.php') ?: [''])[0];
    if ($pgsql) {
        // On PostgreSQL up() is one transaction with its kit_meta record, even outside the migrator: a record it cannot write undoes every rename.
        Schema::getConnection()->statement("alter table kit_meta add constraint adopt_blocked check (key not like 'adopt:%') not valid");
        $refused = refusal(fn () => (require $deliverFile)->up());
        Schema::getConnection()->statement('alter table kit_meta drop constraint adopt_blocked');
        check($refused !== null && Schema::hasTable('kit_deliveries') && Schema::hasTable('kit_delivery_events') && ! Schema::hasTable('deliveries') && $deliverRecords() === 0, 'PostgreSQL: when up() fails after renaming (its record refused), the transaction puts every table back and records nothing');
    }
    migrate($app);
    check(Schema::hasTable('deliveries') && Schema::hasTable('delivery_recipients') && ! Schema::hasTable('kit_deliveries') && $deliverRecords() === 3, 'with the clash gone the same migration renames the deliver tables and records each');

    // A config/kit.php whose models it cannot set exactly (a computed entry): it refuses, prints the entries to set, and writes nothing.
    $computed = "<?php\n\n\$models = ['user' => Illuminate\\Foundation\\Auth\\User::class];\n\nreturn ['features' => ".var_export(FEATURES, true).", 'models' => \$models];\n";
    $rootD = root($computed);
    $app = boot($rootD);
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'notes', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'Could not set kit.models.note') && str_contains($output, "'models' => ['note' => \\App\\Models\\Note::class]") && glob($rootD.'/app/Models/*') === [] && glob($rootD.'/database/migrations/*_adopt_*') === [] && file_get_contents($rootD.'/config/kit.php') === $computed, 'a config/kit.php it cannot edit exactly is refused with the entries to set, and nothing is written');

    // Links: --adopt writes nothing through a link, even with --force: not a linked model file, a linked folder below the app root, or a linked config/kit.php.
    $outside = sys_get_temp_dir().'/kit-domain-outside-'.bin2hex(random_bytes(8));
    mkdir($outside, 0700, true);
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($outside)));
    file_put_contents($outside.'/Note.php', 'outside');
    $rootE = root(kitConfig('current'));
    $app = boot($rootE);
    $untouched = fn (): bool => file_get_contents($outside.'/Note.php') === 'outside' && glob($rootE.'/database/migrations/*_adopt_*') === [] && ! str_contains((string) file_get_contents($rootE.'/config/kit.php'), 'App\\Models\\Note');
    symlink($outside.'/Note.php', $rootE.'/app/Models/Note.php');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'notes', '--adopt' => true, '--force' => ['app/Models/Note.php']]);
    check($code === 1 && str_contains($output, 'app/Models/Note.php') && $untouched(), 'a linked model file is refused even with --force, and nothing is written through it or anywhere else');
    unlink($rootE.'/app/Models/Note.php');
    rmdir($rootE.'/app/Models');
    symlink($outside, $rootE.'/app/Models');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'notes', '--adopt' => true, '--force' => ['app/Models/Note.php']]);
    check($code === 1 && str_contains($output, 'linked folder') && $untouched(), 'a linked folder below the app root is refused even with --force');
    unlink($rootE.'/app/Models');
    mkdir($rootE.'/app/Models');
    rename($rootE.'/config/kit.php', $outside.'/kit.php');
    symlink($outside.'/kit.php', $rootE.'/config/kit.php');
    $config = (string) file_get_contents($outside.'/kit.php');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'notes', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'config/kit.php') && file_get_contents($outside.'/kit.php') === $config && glob($rootE.'/app/Models/*') === [] && glob($rootE.'/database/migrations/*_adopt_*') === [], 'a linked config/kit.php is refused, and nothing is written');

    // The boot check: a configured class that is not a model with its contract, and a feature adopted in part, stop the boot in testing.
    $refused = refusal(fn () => boot(root(kitConfig('current', ['note' => 'NotANote::class']))));
    check($refused !== null && str_contains($refused, 'kit.models.note (NotANote)') && str_contains($refused, 'Tey\\Kit\\Features\\Notes\\Contracts\\Note'), 'a kit.models class that does not implement its contract stops the boot, naming the key and the contract');
    $refused = refusal(fn () => boot(root(kitConfig('current', ['delivery' => 'App\\Models\\Delivery::class']))));
    check($refused !== null && str_contains($refused, 'deliver feature is partly adopted'), 'a feature adopted in part (delivery without its recipients and events) stops the boot');
    check(refusal(fn () => boot(root(kitConfig('current', ['note' => 'Tey\\Kit\\Features\\Notes\\Models\\Note::class'])))) === null, 'naming the kit\'s own class is not adopting');
    check(refusal(fn () => boot(root(kitConfig('current', ['note' => 'NotANote::class']), env: 'production'))) === null, 'outside local and testing the app boots and the problem is logged');

    // A malformed entry is not "unset": only null means the kit's own. A model entry that is not a class name stops the app; a table entry that is not
    // a table name refuses where the kit reads it. Neither falls back to the kit's table.
    $refused = refusal(fn () => boot(root(kitConfig('current', ['note' => 'false']), env: 'production')));
    check($refused !== null && str_contains($refused, 'kit.models.note'), 'kit.models.note set to false refuses, naming the key, rather than run on the kit\'s note table');
    $app = boot(root(str_replace("'note_intent' => null", "'note_intent' => ''", kitConfig('current')), env: 'production'));
    check(refusal(fn () => Kit::table('note_intent')) !== null && str_contains((string) refusal(fn () => Kit::table('note_intent')), 'kit.tables.note_intent') && Kit::table('entitlement') === 'entitlements', 'kit.tables.note_intent set to an empty string refuses, naming the key; a null entry is still the kit\'s table');

    // A feature adopted in part is not adopted: outside local and testing the app boots (the problem logged), and the kit's create migrations still run,
    // so its tables exist.
    $part = root(kitConfig('current', ['delivery' => 'App\\Models\\Delivery::class']), ['app/Models/Delivery.php' => (string) file_get_contents(dirname(__DIR__).'/resources/adopt/models/Delivery.php.stub')], 'production');
    $app = boot($part);
    // Booted as production; migrate as testing, which skips the deploy's role catalog sync (no catalog here).
    $app['env'] = 'testing';
    $ran = migrate($app);
    check(! DomainModels::adopted('deliver') && DomainModels::adoptedKeys('deliver') === ['delivery'] && Schema::hasTable('kit_deliveries') && Schema::hasTable('kit_delivery_recipients') && preg_grep('/_create_kit_deliver_tables\z/', $ran) !== [], 'a feature adopted in part is not adopted: migrate still runs the kit\'s deliver migrations');
    [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--adopt' => true]);
    check($code === 1 && str_contains($output, 'delivery') && glob($part.'/database/migrations/*_adopt_*') === [], 'and kit:scaffold deliver --adopt refuses it, naming the key already set');

    // When config/kit.php cannot be written, the models and migration written before it are removed again: --adopt writes all of it or none.
    $rootF = root(kitConfig('current'));
    $app = boot($rootF);
    $config = (string) file_get_contents($rootF.'/config/kit.php');
    chmod($rootF.'/config/kit.php', 0444);
    if (is_writable($rootF.'/config/kit.php')) {
        echo "SKIP a read-only config/kit.php: this process writes it anyway (root)\n";
    } else {
        [$code, $output] = artisan($app, 'kit:scaffold', ['feature' => 'deliver', '--adopt' => true]);
        check($code === 1 && str_contains($output, 'Could not write config/kit.php') && glob($rootF.'/app/Models/*') === [] && glob($rootF.'/database/migrations/*_adopt_*') === [] && file_get_contents($rootF.'/config/kit.php') === $config, 'a read-only config/kit.php refuses, and the models and migration it wrote first are removed');
    }
    chmod($rootF.'/config/kit.php', 0644);

    echo 'Domain table adoption on '.($pgsql ? 'PostgreSQL (throwaway schemas)' : 'SQLite files').', synthetic apps and rows. MySQL, a consumer app and the kit\'s pages on adopted tables are not covered here.'."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
