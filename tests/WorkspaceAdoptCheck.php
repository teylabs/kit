<?php

// A host that already has its own workspaces table, its own workspace model and spatie/laravel-permission's tables (with teams) adopts the kit's
// workspaces and access features in place: the plan lists adoptions, not collisions; the migrations add only the kit's missing columns (every existing
// row admitted, at settings version 1) and create no second table; the kit resolves the host's model everywhere; a mismatched binding is refused; and
// nothing under src/ names the kit's own model except the model, its resolver and the default config. SQLite memory, synthetic workspaces and people.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Tenant;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Policies\WorkspacePolicy;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace as KitWorkspace;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceTable;
use Tey\Kit\HostRequirements;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function adoptCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function adoptRefused(callable $run): ?string
{
    try {
        $run();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
}

class AdoptUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(AdoptUser::class, 'App\Models\User');

/** A host's own workspace model over its own table: timestamps, its own columns, spatie's Tenant, Concierge's contract and the kit's trait. */
class HostWorkspace extends Tenant implements Tey\Concierge\Contracts\Workspace
{
    use IsKitWorkspace;

    protected $table = 'workspaces';

    protected $guarded = [];

    protected $casts = ['accepts_members' => 'boolean', 'admitted' => 'boolean'];
}

/** Missing the kit's trait. */
class TraitlessWorkspace extends Tenant implements Tey\Concierge\Contracts\Workspace
{
    protected $table = 'workspaces';

    public function conciergeName(): string
    {
        return (string) $this->name;
    }

    public function conciergeAcceptsMembers(): bool
    {
        return true;
    }
}

/** Not a tenant. */
class PlainWorkspace extends Illuminate\Database\Eloquent\Model implements Tey\Concierge\Contracts\Workspace
{
    use IsKitWorkspace;

    protected $table = 'workspaces';
}

/** A host model the kit's BelongsToWorkspace scopes, to show the relation follows the bound model. */
class AdoptTicket extends Illuminate\Database\Eloquent\Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $table = 'adopt_tickets';

    protected $guarded = [];
}

/** References to the kit's own model under a directory, outside the files allowed to name it. */
function kitModelReferences(string $root, array $allowed): array
{
    $found = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
        if ($file->getExtension() === 'php' && ! in_array($path, $allowed, true) && namesKitModel((string) file_get_contents($file->getPathname()))) {
            $found[] = $path;
        }
    }
    sort($found);

    return $found;
}

function namesKitModel(string $source): bool
{
    return preg_match('/Models\\\\{1,2}Workspace\b/', $source) === 1;
}

try {
    // (c) The architecture guard: kit code reaches the workspace through Workspaces, never the kit's model class.
    adoptCheck(namesKitModel('use Tey\Kit\Features\Workspaces\Models\Workspace;') && namesKitModel("'Tey\\\\Kit\\\\Features\\\\Workspaces\\\\Models\\\\Workspace'") && ! namesKitModel('use Tey\Kit\Features\Workspaces\Workspaces;'), 'the guard recognises an import or string naming the kit model, and not the resolver');
    $allowed = ['src/Features/Workspaces/Models/Workspace.php', 'src/Features/Workspaces/Workspaces.php', 'config/defaults/concierge.php', 'config/defaults/multitenancy.php'];
    adoptCheck(($stray = kitModelReferences(dirname(__DIR__).'/src', $allowed)) === [], 'nothing under src/ names Models\Workspace except the model and the resolver'.($stray === [] ? '' : ': '.implode(', ', $stray)));
    adoptCheck(kitModelReferences(dirname(__DIR__).'/config', $allowed) === [], 'under config/, only the default concierge and multitenancy config name the kit model');

    $base = sys_get_temp_dir().'/kit-workspace-adopt-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => AdoptUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access'], 'models' => ['user' => AdoptUser::class]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();

    // The default binding is the kit's own model, and it passes the binding check.
    adoptCheck(Workspaces::model() === KitWorkspace::class && Workspaces::refusal() === null, 'with no host binding the kit resolves, and accepts, its own workspace model');

    // The host's schema before the kit: users, its own workspaces table (timestamps, accepts_members, rows) and spatie's tables with workspace teams.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    Schema::create('workspaces', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('accepts_members')->default(false);
        $table->timestamps();
    });
    DB::table('workspaces')->insert([
        ['name' => 'Northwind Farms', 'accepts_members' => true, 'created_at' => '2025-01-02 03:04:05', 'updated_at' => '2025-01-02 03:04:05'],
        ['name' => 'Acme Travel', 'accepts_members' => false, 'created_at' => '2025-02-03 04:05:06', 'updated_at' => '2025-02-03 04:05:06'],
    ]);
    (require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    DB::table($config->get('permission.table_names.roles'))->insert(['workspace_id' => 1, 'name' => 'host-role', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    $hostRows = DB::table('workspaces')->orderBy('id')->get(['id', 'name', 'accepts_members', 'created_at', 'updated_at'])->map(fn ($row) => (array) $row)->all();
    $tablesBefore = array_column(Schema::getTables(), 'name');

    // (a) The plan: the workspaces and permission migrations adopt the host's tables; another migration creating a host table still collides.
    $dir = dirname(__DIR__).'/resources/features/workspaces/migrations';
    $paths = ['database/migrations/kit/workspaces/0001_01_02_000200_create_kit_workspaces_table.php' => $dir.'/0001_01_02_000200_create_kit_workspaces_table.php.stub', 'database/migrations/kit/workspaces/0001_01_02_000201_create_permission_tables.php' => $dir.'/0001_01_02_000201_create_permission_tables.php.stub'];
    $files = array_map(fn () => ['state' => 'create'], $paths);
    $bytes = array_map(fn (string $stub): string => (string) file_get_contents($stub), $paths);
    $adoptions = HostRequirements::adoptions($files, $bytes);
    adoptCheck(HostRequirements::collisions($files, $bytes) === [], 'the host\'s workspaces and permission tables are not collisions');
    adoptCheck(count($adoptions) === 2 && str_contains($adoptions[0], 'adopt the host\'s workspaces table in place') && str_contains($adoptions[0], 'add admitted, settings_version, provisioning_invitation_id') && str_contains($adoptions[1], 'adopt the host\'s permission tables in place'), 'the plan lists both as adoptions, naming the columns the kit adds');
    $other = ['database/migrations/2026_01_01_000000_create_workspaces_again.php' => ['state' => 'create']];
    adoptCheck(count(HostRequirements::collisions($other, ['database/migrations/2026_01_01_000000_create_workspaces_again.php' => "Schema::create('workspaces', fn (\$t) => \$t->id());"])) === 1, 'any other migration creating the host\'s workspaces table still collides');
    // A host's own config/permission.php is the whole truth for the plan (the kit's defaults step aside), naming another teams column.
    mkdir($base.'/config');
    file_put_contents($base.'/config/permission.php', "<?php\n\nreturn [];\n");
    $config->set('permission.column_names.team_foreign_key', 'team_id');
    adoptCheck(count($blocking = HostRequirements::collisions($files, $bytes)) === 1 && str_contains($blocking[0], 'no team_id teams column'), 'host permission tables without the configured teams column block the plan');
    adoptCheck(str_contains((string) adoptRefused(fn () => (require $paths[array_key_last($paths)])->up()), 'have no team_id column'), 'and the permission migration refuses them clearly');
    $config->set('permission.column_names.team_foreign_key', 'workspace_id');
    unlink($base.'/config/permission.php');

    // (a) Migrate: Concierge's migrations, then every kit stub in order, over the host's schema.
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $rows = DB::table('workspaces')->orderBy('id')->get();
    adoptCheck(DB::table('workspaces')->orderBy('id')->get(['id', 'name', 'accepts_members', 'created_at', 'updated_at'])->map(fn ($row) => (array) $row)->all() === $hostRows, 'every host column and row is kept as it was');
    adoptCheck(count($rows) === 2 && $rows->every(fn ($row) => (int) $row->admitted === 1 && (int) $row->settings_version === 1 && $row->provisioning_invitation_id === null), 'every existing workspace is admitted, at settings version 1');
    adoptCheck($rows->pluck('slug')->all() === ['northwind-farms', 'acme-travel'], 'each existing workspace gets a slug from its name');
    $added = array_values(array_diff(array_column(Schema::getTables(), 'name'), $tablesBefore));
    adoptCheck($added !== [] && preg_grep('/workspaces$/', $added) === [], 'the other kit tables are created, and no second workspaces table ('.implode(', ', $added).')');
    adoptCheck(DB::table($config->get('permission.table_names.roles'))->where('name', 'host-role')->count() === 1, 'the host\'s permission tables and rows are kept');
    adoptCheck(WorkspaceTable::adopted(), 'the workspaces table reads as the host\'s, so the kit\'s down() leaves it');
    (require $stubs['0001_01_02_000200_create_kit_workspaces_table.php.stub'])->up();
    (require $stubs['0001_01_02_000203_add_slug_to_kit_workspaces_table.php.stub'])->up();
    (require $stubs['0001_01_02_000201_create_permission_tables.php.stub'])->up();
    adoptCheck(DB::table('workspaces')->orderBy('id')->pluck('slug')->all() === ['northwind-farms', 'acme-travel'], 'the adopting migrations run again change nothing');
    (require $stubs['0001_01_02_000203_add_slug_to_kit_workspaces_table.php.stub'])->down();
    (require $stubs['0001_01_02_000201_create_permission_tables.php.stub'])->down();
    (require $stubs['0001_01_02_000200_create_kit_workspaces_table.php.stub'])->down();
    adoptCheck(Schema::hasTable('workspaces') && Schema::hasColumn('workspaces', 'slug') && Schema::hasTable($config->get('permission.table_names.roles')) && DB::table('workspaces')->count() === 2, 'rolling the adopting migrations back drops no host table or column');

    // (b) The host binds its own model, and the kit resolves it.
    $config->set('concierge.models.workspace', HostWorkspace::class);
    $config->set('multitenancy.tenant_model', HostWorkspace::class);
    $app->bind(IsTenant::class, HostWorkspace::class);
    adoptCheck(Workspaces::model() === HostWorkspace::class && Workspaces::refusal() === null, 'the host binding passes the check and is the model the kit resolves');
    $northwind = Workspaces::query()->where('slug', 'northwind-farms')->firstOrFail();
    adoptCheck($northwind instanceof HostWorkspace && $northwind->accepts_members === true && $northwind->conciergeAcceptsMembers(), 'Workspaces::query() returns the host\'s model with its own columns');
    $created = Workspaces::model()::create(['name' => 'Contoso Studio', 'admitted' => true]);
    adoptCheck($created instanceof HostWorkspace && $created->slug === 'contoso-studio' && $created->created_at !== null && (int) DB::table('workspaces')->where('id', $created->id)->value('settings_version') === 1, 'a workspace the kit creates is the host\'s model, with a slug and the host\'s timestamps');
    adoptCheck(adoptRefused(fn () => Workspaces::model()::create(['name' => 'Other', 'slug' => 'contoso-studio'])) !== null, 'the kit trait still refuses a taken slug on the host model');
    adoptCheck(Workspaces::current() === null && adoptRefused(fn () => Workspaces::required()) === 'workspace_required', 'outside a workspace there is no current one and required() fails closed');
    $northwind->makeCurrent();
    try {
        adoptCheck(Workspaces::required() instanceof HostWorkspace && Workspaces::required()->is($northwind), 'inside the host workspace, Workspaces::required() is that host model');
        Schema::create('adopt_tickets', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
        });
        $ticket = AdoptTicket::create([]);
        adoptCheck((int) $ticket->workspace_id === (int) $northwind->id && $ticket->workspace instanceof HostWorkspace, 'BelongsToWorkspace stamps the current host workspace and relates to the host model');
    } finally {
        HostWorkspace::forgetCurrent();
    }
    $ana = AdoptUser::create(['name' => 'Ana', 'email' => 'ana@example.test', 'email_verified_at' => now()]);
    ConciergeModels::membership()::query()->create(['workspace_id' => $northwind->id, 'user_id' => $ana->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    $mine = MemberWorkspaces::workspaces($ana);
    adoptCheck(count($mine) === 1 && $mine[0] instanceof HostWorkspace && MemberWorkspaces::workspace((int) $northwind->id, $ana) instanceof HostWorkspace, 'MemberWorkspaces returns the host model for the person\'s workspaces');
    adoptCheck(Gate::getPolicyFor($northwind) instanceof WorkspacePolicy, 'the access WorkspacePolicy covers the host model');

    // (d) A mismatched or incomplete binding is refused clearly.
    adoptCheck(str_contains((string) Workspaces::refusal(HostWorkspace::class, KitWorkspace::class), 'must name the same workspace model'), 'concierge.models.workspace and multitenancy.tenant_model naming different models is refused');
    adoptCheck(str_contains((string) Workspaces::refusal(HostWorkspace::class, null), 'multitenancy.tenant_model (unset)'), 'an unset tenant model is refused');
    adoptCheck(str_contains((string) Workspaces::refusal(TraitlessWorkspace::class, TraitlessWorkspace::class), 'must use '.IsKitWorkspace::class), 'a bound model without the kit trait is refused');
    adoptCheck(str_contains((string) Workspaces::refusal(PlainWorkspace::class, PlainWorkspace::class), 'must implement '.IsTenant::class), 'a bound model that is not a tenant is refused');
    adoptCheck(str_contains((string) Workspaces::refusal('App\Models\NoSuchWorkspace', 'App\Models\NoSuchWorkspace'), 'does not exist'), 'a bound class that does not exist is refused');
    $config->set('multitenancy.tenant_model', KitWorkspace::class);
    adoptCheck(Workspaces::refusal() !== null, 'the live config check refuses the mismatch too');
    $config->set('multitenancy.tenant_model', HostWorkspace::class);

    echo "Adopt-in-place on in-memory SQLite with a synthetic host schema; no PostgreSQL or MySQL run, no kit:add command run end to end, no HTTP.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
