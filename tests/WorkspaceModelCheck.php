<?php

// Concerns\BelongsToWorkspace and Concerns\GuardsStaleWrites on a synthetic model: reads are scoped to the current workspace and refused outside one; a new
// row takes the current workspace; another workspace's row cannot be saved, moved or deleted; a stale lock_version is refused and changes nothing; the
// stale_write code is registered. SQLite memory, real Concierge and multitenancy, two synthetic workspaces.
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Concerns\GuardsStaleWrites;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(HttpUser::class, 'App\Models\User');

final class Ticket extends Model
{
    use BelongsToWorkspace, GuardsStaleWrites;

    protected $guarded = [];

    protected $casts = ['workspace_id' => 'integer', 'lock_version' => 'integer'];
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function refused(callable $run): ?string
{
    try {
        $run();
    } catch (RuntimeException $error) {
        return $error->getMessage();
    }

    return null;
}

try {
    $base = sys_get_temp_dir().'/kit-model-'.bin2hex(random_bytes(4));
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
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'mcp' => ['ui' => true]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => Monolog\Handler\TestHandler::class]]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $open = function (string $name) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => strtolower($name)]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $workspace = $open('Acme');
    $other = $open('Globex');
    Schema::create('tickets', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
        $table->string('title');
        $table->unsignedInteger('lock_version')->default(1);
        $table->timestamps();
    });
    $in = fn (Workspace $workspace, callable $run) => app(TenantBoundary::class)->run((int) $workspace->id, $run);

    $mine = $in($workspace, fn () => Ticket::create(['title' => 'Mine']));
    $theirs = $in($other, fn () => Ticket::create(['title' => 'Theirs']));
    check($mine->workspace_id === (int) $workspace->id && $mine->lock_version === 1, 'a new row takes the current workspace and starts at version 1');
    check($in($workspace, fn () => Ticket::pluck('title')->all()) === ['Mine'] && $in($other, fn () => Ticket::pluck('title')->all()) === ['Theirs'], 'each workspace reads only its own rows');
    check($in($workspace, fn () => Ticket::find($theirs->id)) === null, 'another workspace\'s row is not found by id');
    check(refused(fn () => Ticket::count()) === 'workspace_required', 'outside any workspace a read fails closed with workspace_required, never every tenant\'s rows');
    check(refused(fn () => $in($workspace, fn () => Ticket::create(['title' => 'Smuggled', 'workspace_id' => $other->id]))) === 'workspace_mismatch' && DB::table('tickets')->where('title', 'Smuggled')->doesntExist(), 'a new row naming another workspace is refused');
    check(refused(fn () => $in($workspace, fn () => $theirs->fill(['title' => 'Hijacked'])->save())) === 'workspace_mismatch', 'another workspace\'s row cannot be saved');
    check(refused(fn () => $in($workspace, fn () => $theirs->delete())) === 'workspace_mismatch' && DB::table('tickets')->where('id', $theirs->id)->exists(), 'another workspace\'s row cannot be deleted');
    check(refused(fn () => $in($workspace, fn () => $mine->fill(['workspace_id' => $other->id])->save())) === 'workspace_mismatch', 'a row cannot be moved to another workspace');
    check(DB::table('tickets')->where('id', $theirs->id)->value('title') === 'Theirs', 'nothing another workspace tried changed the row');

    $updated = $in($workspace, fn () => Ticket::findOrFail($mine->id)->updateIfCurrent(1, ['title' => 'Second', 'lock_version' => 99]));
    check($updated->title === 'Second' && $updated->lock_version === 2, 'a current write lands and bumps the version (a lock_version in the attributes is ignored)');
    check(refused(fn () => $in($workspace, fn () => Ticket::findOrFail($mine->id)->updateIfCurrent(1, ['title' => 'Stale']))) === 'stale_write' && DB::table('tickets')->where('id', $mine->id)->value('title') === 'Second', 'a write from the old version is refused with stale_write and changes nothing');
    check(refused(fn () => $in($other, fn () => (new Ticket)->setRawAttributes(['id' => $mine->id, 'workspace_id' => $workspace->id], true)->updateIfCurrent(2, ['title' => 'Cross']))) === 'stale_write' && DB::table('tickets')->where('id', $mine->id)->value('title') === 'Second', 'a guarded write from another workspace matches no row and changes nothing');
    check(app(Registry::class)->status('stale_write') === 409 && in_array('stale_write', app(Registry::class)->registeredMcpRefusalCodes(), true), 'stale_write is a 409 and an MCP refusal code');

    echo "Workspace model concerns on SQLite memory with real Concierge and multitenancy. The database-level foreign key and any check constraints are the app's migration's; this checks the model layer.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
