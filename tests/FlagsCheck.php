<?php

// Flags and tags (Concerns\HasFlags, Flags, Tags) on a synthetic workspace model: a flag is raised with its reason and link, raised again in place, removed
// with when: false or unflag(); flagged()/notFlagged() query it; a dismissed flag is not raised again unless forced, and only a dismissible one may be
// dismissed; Tags::for() orders flags by severity then definition, before people's tags, in one query for many rows; a person's tag stays in its
// workspace while flags are one vocabulary; the Flags filter narrows a table; workspace deletion takes the workspace's tag rows. SQLite memory, real
// Concierge, multitenancy and spatie/laravel-tags, two synthetic workspaces.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\TestHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Tags\TagsServiceProvider;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Tables\TableQuery;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Concerns\HasFlags;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Flags\Flags;
use Tey\Kit\Features\Workspaces\Flags\Tag;
use Tey\Kit\Features\Workspaces\Flags\Tags;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Mod\ModServiceProvider;

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
    use BelongsToWorkspace, HasFlags;

    protected $guarded = [];

    protected $casts = ['workspace_id' => 'integer'];
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $base = sys_get_temp_dir().'/kit-flags-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        // SQLite memory by default; DB_CONNECTION=pgsql runs on the PostgreSQL the DB_* variables name (CI's service container; its public schema is recreated).
        'database' => ['default' => 'testing', 'connections' => ['testing' => getenv('DB_CONNECTION') === 'pgsql'
            ? ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer']
            : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'mcp' => ['ui' => true]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => TestHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class, TagsServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

    if (DB::connection()->getDriverName() === 'pgsql') {
        DB::statement('drop schema if exists public cascade');
        DB::statement('create schema public');
    }
    echo 'Database: '.DB::connection()->getDriverName()."\n";
    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    // In the order a host's dated migrations run, so foreign keys find their tables (PostgreSQL checks them; SQLite here does not): Passport and media, the
    // kit's own (which create workspaces), then Concierge's, whose tables reference workspaces.
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
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
    Schema::create('tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id')->constrained();
        $table->string('title');
        $table->timestamps();
    });
    $in = fn (Workspace $workspace, callable $run) => Workspaces::run((int) $workspace->id, Closure::fromCallable($run));
    Flags::define('possible-duplicate', 'Possible duplicate', tone: 'warning', dismissible: true);
    Flags::define('unpaid', 'Unpaid', tone: 'info');
    Flags::define('stalled', 'Stalled', tone: 'danger');

    $in($workspace, function () use ($workspace) {
        $a = Ticket::create(['title' => 'A']);
        $b = Ticket::create(['title' => 'B']);
        check($a->flag('possible-duplicate', reason: 'Looks like A-1042', link: '/cases/1042') && $a->flagged('possible-duplicate') && ! $b->flagged('possible-duplicate'), 'flag() raises a flag on that record only');
        $pivot = DB::table('kit_taggables')->where('taggable_id', $a->id)->first();
        check((int) $pivot->workspace_id === (int) $workspace->id && json_decode($pivot->meta, true) === ['reason' => 'Looks like A-1042', 'link' => '/cases/1042'], 'the flag carries the record\'s workspace and its reason and link');
        $a->flag('possible-duplicate', reason: 'Looks like A-1043');
        check(DB::table('kit_taggables')->where('taggable_id', $a->id)->count() === 1 && json_decode(DB::table('kit_taggables')->where('taggable_id', $a->id)->value('meta'), true) === ['reason' => 'Looks like A-1043'], 'raising it again updates it in place, never a second row');
        check(DB::table('kit_tags')->where('type', 'flag')->count() === 1 && DB::table('kit_tags')->where('type', 'flag')->value('workspace_id') === null, 'a flag is one vocabulary entry with no workspace');
        check($b->flag('unpaid', when: true) && ! $b->flag('unpaid', when: false) && ! $b->fresh()->flagged('unpaid'), 'flag(when: false) removes it');
        $b->flag('stalled');
        $b->unflag('stalled');
        check(! $b->fresh()->flagged('stalled'), 'unflag() removes it');
        $b->flag('unpaid');
        check(Ticket::query()->flagged('possible-duplicate')->pluck('title')->all() === ['A'] && Ticket::query()->notFlagged('possible-duplicate')->pluck('title')->all() === ['B'] && Ticket::query()->flagged('unpaid', 'possible-duplicate')->count() === 2, 'flagged() and notFlagged() query by any of the keys');
        check(Ticket::query()->flagged('never-raised')->count() === 0, 'a flag never raised matches nothing');

        $a->dismissFlag('possible-duplicate', by: 7);
        check(! $a->fresh()->flagged('possible-duplicate') && DB::table('kit_flag_dismissals')->where(['flaggable_id' => $a->id, 'flag' => 'possible-duplicate', 'dismissed_by' => 7])->exists(), 'dismissFlag() removes it and records who');
        check(! $a->fresh()->flag('possible-duplicate', reason: 'Looks like A-1042') && ! $a->fresh()->flagged('possible-duplicate'), 'a dismissed flag is not raised again');
        check($a->fresh()->flag('possible-duplicate', reason: 'Looks like A-2001', force: true) && $a->fresh()->flagged('possible-duplicate') && ! DB::table('kit_flag_dismissals')->where('flaggable_id', $a->id)->exists(), 'force: true raises it again and clears the dismissal');
        $refused = null;
        try {
            $b->dismissFlag('unpaid');
        } catch (RuntimeException $error) {
            $refused = $error->getMessage();
        }
        check($refused === 'flag_not_dismissible: unpaid' && $b->fresh()->flagged('unpaid'), 'a flag not defined dismissible cannot be dismissed');

        $a->flag('stalled');
        $a->flag('ad-hoc-thing');
        $a->attachTag('VIP');
        $c = Ticket::create(['title' => 'C']);
        DB::enableQueryLog();
        $tags = Tags::for(Ticket::query()->orderBy('id')->get());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $first = array_map(fn ($tag) => [$tag->key, $tag->kind, $tag->tone], $tags[$a->id]);
        check($first === [['stalled', 'flag', 'danger'], ['possible-duplicate', 'flag', 'warning'], ['ad-hoc-thing', 'flag', 'neutral'], ['tag:vip', 'tag', 'neutral']], 'Tags::for() lists flags by severity, an undefined flag last of them, then people\'s tags');
        check($tags[$a->id][1]->label === 'Possible duplicate' && $tags[$a->id][1]->tooltip === 'Looks like A-2001' && $tags[$a->id][2]->label === 'Ad Hoc Thing' && $tags[$c->id] === [], 'a defined flag shows its label and reason; an undefined one is labelled from its key; a record without any has none');
        check($queries <= 2, 'one query loads the tags of every row ('.$queries.' queries for three rows)');

        $request = Request::create('/tickets', 'GET', ['filter' => ['flags' => 'unpaid']]);
        app()->instance('request', $request);
        $table = Flags::filter(TableQuery::for(Ticket::query(), $request)->column('title', 'Title'));
        $data = $table->build(fn (Ticket $ticket): array => ['id' => $ticket->id, 'title' => $ticket->title]);
        check(array_column($data->rows, 'title') === ['B'] && in_array('flags', array_map(fn ($filter) => $filter->key, $data->choices), true), 'the Flags filter narrows a table to the ticked flags and is offered as a choice');
    });
    $in($other, function () {
        $theirs = Ticket::create(['title' => 'Theirs']);
        $theirs->attachTag('VIP');
        check(DB::table('kit_tags')->where('type', null)->count() === 2 && Tag::query()->whereNull('type')->count() === 1, 'a person\'s tag belongs to its workspace: another workspace makes and sees its own');
        check(Ticket::query()->flagged('unpaid')->count() === 0, 'flags are read in the current workspace only');
    });

    // Fail closed (#1190): a tag that is not a flag, made with no current workspace (a job, a command, a seeder), is refused, never saved as shared.
    $refused = null;
    try {
        Tag::findOrCreate('Orphan');
    } catch (RuntimeException $error) {
        $refused = $error->getMessage();
    }
    check(str_contains((string) $refused, 'tag_workspace_required') && DB::table('kit_tags')->where('slug', 'like', '%orphan%')->count() === 0, 'a non-flag tag with no current workspace is refused, not saved with a null workspace');
    $nullable = collect(Schema::getColumns('kit_taggables'))->firstWhere('name', 'workspace_id')['nullable'] ?? true;
    check($nullable === false && (collect(Schema::getColumns('kit_flag_dismissals'))->firstWhere('name', 'workspace_id')['nullable'] ?? true) === false, 'kit_taggables and kit_flag_dismissals require a workspace_id');

    // Upgrading: 000212 fills a missing workspace_id from the record's own, and refuses, naming the rows, when the record gives none (it never guesses).
    $protect = require dirname(__DIR__).'/database/migrations/workspaces/0001_01_02_000212_protect_kit_tags_tables.php';
    $protect->down();
    $ticket = (int) DB::table('tickets')->insertGetId(['workspace_id' => $other->id, 'title' => 'legacy']);
    $flagTag = (int) DB::table('kit_tags')->where('type', 'flag')->value('id');
    DB::table('kit_taggables')->insert([['tag_id' => $flagTag, 'taggable_type' => Ticket::class, 'taggable_id' => $ticket, 'workspace_id' => null], ['tag_id' => $flagTag, 'taggable_type' => Ticket::class, 'taggable_id' => 999999, 'workspace_id' => null]]);
    $refused = null;
    try {
        $protect->up();
    } catch (RuntimeException $error) {
        $refused = $error->getMessage();
    }
    check(str_contains((string) $refused, 'kit_taggables') && str_contains((string) $refused, Ticket::class.' #999999') && ! str_contains((string) $refused, '#'.$ticket.','), 'the upgrade refuses, naming the row whose record has no workspace');
    DB::table('kit_taggables')->where('taggable_id', 999999)->delete();
    $protect->up();
    check((int) DB::table('kit_taggables')->where('taggable_id', $ticket)->value('workspace_id') === (int) $other->id && (collect(Schema::getColumns('kit_taggables'))->firstWhere('name', 'workspace_id')['nullable'] ?? true) === false, 'then fills the rest from the record\'s workspace and makes the column NOT NULL');
    DB::table('kit_taggables')->where('taggable_id', $ticket)->delete();
    DB::table('tickets')->where('id', $ticket)->delete();

    $rows = fn (int $id) => DB::table('kit_taggables')->where('workspace_id', $id)->count() + DB::table('kit_tags')->where('workspace_id', $id)->count();
    $before = $rows((int) $other->id);
    (new DeleteWorkspace)->deleteRows((int) $other->id);
    check($before > 0 && $rows((int) $other->id) === 0 && DB::table('kit_tags')->where('type', 'flag')->count() === 4, 'deleting a workspace deletes its tag rows and keeps the flag vocabulary');

    echo "Flags and tags on SQLite (or PostgreSQL with DB_CONNECTION=pgsql); no browser, Inertia page or RLS run.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
