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

/** A row its owner may move between workspaces they belong to: the model names its own, row-aware move ability. */
final class OwnedApp extends Model
{
    use BelongsToWorkspace;

    public static string $moveAbility = 'owned-app.move';

    protected $guarded = [];

    protected $casts = ['workspace_id' => 'integer', 'user_id' => 'integer'];

    public function workspaceMoveAbility(): string
    {
        return self::$moveAbility;
    }
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
        // SQLite memory by default; DB_CONNECTION=pgsql runs on the PostgreSQL the DB_* variables name (CI's service container; its public schema is recreated).
        'database' => ['default' => 'testing', 'connections' => ['testing' => getenv('DB_CONNECTION') === 'pgsql'
            ? ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer']
            : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
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
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

    if (DB::connection()->getDriverName() === 'pgsql') {
        DB::statement('drop schema if exists public cascade');
        DB::statement('create schema public');
    }
    echo 'Database: '.DB::connection()->getDriverName()."\n";
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

    // The stale-write contract the client reads (useSubmitForm, the Workbench's useForm + save): through the real kernel and exception handler, a stale guarded
    // save answers exactly tests/fixtures/stale-write-409.json, the file tests/js/submit-form.test.mjs feeds the client, on an Inertia request and a JSON one alike.
    // Every other refusal on an Inertia request is still the adopted error page (an Inertia page response), and so is a 404.
    $contract = json_decode((string) file_get_contents(dirname(__DIR__).'/tests/fixtures/stale-write-409.json'), true, flags: JSON_THROW_ON_ERROR);
    // The guard's own refusal, raised by a real stale updateIfCurrent() (the tenant boundary runs outside this bare request pipeline), thrown by the route.
    $stale = null;
    try {
        $in($workspace, fn () => Ticket::findOrFail($mine->id)->updateIfCurrent(1, ['title' => 'Stale']));
    } catch (RuntimeException $error) {
        $stale = $error;
    }
    check($stale?->getMessage() === 'stale_write', 'the guard refuses the stale save with stale_write');
    $router = app('router');
    $router->middleware('web')->post('/kit-stale', fn () => throw $stale);
    $router->middleware('web')->post('/kit-stale-settings', fn () => throw new RuntimeException('stale_settings'));
    $router->middleware('web')->post('/kit-mismatch', fn () => throw new RuntimeException('workspace_mismatch'));
    $router->getRoutes()->refreshNameLookups();
    // Already configured in memory: the kernel must not bootstrap again (that would reload config from the empty base path).
    $app->bootstrapWith([]);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $send = function (string $uri, array $headers) use ($kernel) {
        $request = Request::create('https://kit.test'.$uri, 'POST', [], [], [], array_combine(array_map(fn ($name) => 'HTTP_'.strtoupper(str_replace('-', '_', $name)), array_keys($headers)), array_values($headers)));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, application/xhtml+xml'];
    foreach (['an Inertia save' => $inertia, 'a JSON save' => ['Accept' => 'application/json']] as $label => $headers) {
        $response = $send('/kit-stale', $headers);
        check($response->getStatusCode() === $contract['status'] && json_decode((string) $response->getContent(), true) === $contract['body'] && $response->headers->get('X-Inertia') === null && str_contains((string) $response->headers->get('Content-Type'), 'application/json'), $label.' that hits the stale-write guard answers exactly the 409 stale_write JSON the client parses (tests/fixtures/stale-write-409.json), not a page');
    }
    check(DB::table('tickets')->where('id', $mine->id)->value('title') === 'Second', 'and the stale saves changed nothing');
    foreach (['/kit-stale-settings' => 409, '/kit-mismatch' => 404, '/kit-no-such-route' => 404] as $uri => $status) {
        $response = $send($uri, $inertia);
        $page = json_decode((string) $response->getContent(), true);
        check($response->getStatusCode() === $status && $response->headers->get('X-Inertia') === 'true' && ($page['component'] ?? null) === 'Workspace/Error/Page', 'on an Inertia request '.$uri.' is still the error page ('.$status.'), not JSON');
    }

    // Route binding: outside any workspace there is nothing to bind (a 404), not workspace_required (a 500); inside one, another workspace's row is a 404 through
    // the scope. Every other query outside a workspace still fails closed.
    check((new Ticket)->resolveRouteBinding($mine->id) === null, 'route binding outside any workspace finds nothing, without throwing');
    check($in($workspace, fn () => (new Ticket)->resolveRouteBinding($mine->id))?->id === $mine->id, 'inside the workspace its own row binds');
    check($in($workspace, fn () => (new Ticket)->resolveRouteBinding($theirs->id)) === null, 'and another workspace\'s row does not');
    check(refused(fn () => Ticket::whereKey($mine->id)->first()) === 'workspace_required', 'a plain query outside any workspace still throws workspace_required');
    check((new Ticket)->resolveChildRouteBinding('tickets', $mine->id, 'id') === null, 'a child binding outside any workspace finds nothing too');
    // Laravel's implicit binding (what SubstituteBindings runs) on a matched route: outside any workspace it raises ModelNotFoundException, a 404.
    $bind = function (int $id): ?Throwable {
        $route = (new Illuminate\Routing\Route(['GET'], '/kit-binding/{ticket}', ['uses' => fn (Ticket $ticket) => $ticket->id]))->bind(Request::create('/kit-binding/'.$id));
        $route->setContainer(app());
        try {
            Illuminate\Routing\ImplicitRouteBinding::resolveForRoute(app(), $route);
        } catch (Throwable $error) {
            return $error;
        }

        return null;
    };
    $outside = $bind($mine->id);
    check($outside instanceof Illuminate\Database\Eloquent\ModelNotFoundException, 'a route binding a workspace model outside any workspace is a 404 (ModelNotFoundException), not workspace_required (got '.($outside ? $outside::class.': '.$outside->getMessage() : 'no exception').')');
    check($in($workspace, fn () => $bind($mine->id)) === null && $in($workspace, fn () => $bind($theirs->id)) instanceof Illuminate\Database\Eloquent\ModelNotFoundException, 'inside a workspace the route binds its own row, and another workspace\'s is a 404');
    check(Ticket::SCOPE === 'workspace' && array_key_exists(Ticket::SCOPE, (new Ticket)->getGlobalScopes()), 'the scope is named BelongsToWorkspace::SCOPE, \'workspace\'');
    check(Ticket::withoutGlobalScope(Ticket::SCOPE)->count() === 2, 'and withoutGlobalScope(SCOPE) is the explicit opt-out');


    // MoveToWorkspace: one row from the current workspace to another, its id kept, by someone holding the move ability (members.manage) in both, audited.
    $member = function (string $email, array $roles): HttpUser {
        $user = HttpUser::create(['name' => $email, 'email' => $email, 'email_verified_at' => now()]);
        foreach ($roles as [$in_, $role]) {
            Tey\Concierge\Support\ConciergeModels::membership()::query()->create(['workspace_id' => $in_->id, 'user_id' => $user->id, 'status' => Tey\Concierge\Enums\MembershipStatus::Active, 'joined_at' => now()]);
            app(Tey\Concierge\Concierge::class)->context()->run($in_, fn () => $user->assignRole($role), [$user]);
        }

        return $user;
    };
    $both = $member('both@example.test', [[$workspace, 'admin'], [$other, 'admin']]);
    $viewOnly = $member('view@example.test', [[$workspace, 'member'], [$other, 'member']]);
    $sourceOnly = $member('source@example.test', [[$workspace, 'admin'], [$other, 'member']]);
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    $workspace->refresh();
    $other->refresh();
    $move = Tey\Kit\Features\Access\Operations\MoveToWorkspace::class;
    $movable = $in($workspace, fn () => Ticket::create(['title' => 'Movable']));
    $events = fn () => DB::table('audit_events')->where('event', 'workspace.row.moved')->count();
    foreach (['a member of both who can only view' => $viewOnly, 'an admin of the source who is only a member of the target' => $sourceOnly] as $who => $actor) {
        $denied = null;
        try {
            $in($workspace, fn () => $move::run(Ticket::findOrFail($movable->id), $other, new Tey\Kit\Features\Core\Principal($actor->id)));
        } catch (Illuminate\Auth\Access\AuthorizationException $error) {
            $denied = $error;
        }
        check($denied !== null && $denied->status() === 403 && $denied->response()?->code() === 'permission_denied' && DB::table('tickets')->where('id', $movable->id)->value('workspace_id') === (int) $workspace->id && $events() === 0, $who.' cannot move a row: a 403 (permission_denied), nothing changes, nothing audited');
    }
    check(app(Tey\Kit\Features\Workspaces\Workspaces::class) !== null && config('kit.workspaces.move_ability') === 'members.manage', 'the move ability defaults to members.manage (kit.workspaces.move_ability)');
    check(refused(fn () => $in($workspace, fn () => $move::run($theirs, $other, new Tey\Kit\Features\Core\Principal($both->id)))) === 'workspace_mismatch', 'another workspace\'s row cannot be moved from here');
    check(refused(fn () => $move::run($movable, $other, new Tey\Kit\Features\Core\Principal($both->id))) === 'workspace_required', 'outside any workspace nothing moves');
    check(refused(fn () => $in($workspace, fn () => $move::run(Ticket::findOrFail($movable->id), $workspace, new Tey\Kit\Features\Core\Principal($both->id)))) === 'workspace_same', 'the target must be another workspace');
    check(refused(fn () => $in($workspace, fn () => $move::run(Ticket::findOrFail($movable->id), 999999, new Tey\Kit\Features\Core\Principal($both->id)))) === 'workspace_not_found', 'and one that exists');
    $moved = $in($workspace, fn () => $move::run(Ticket::findOrFail($movable->id), $other, new Tey\Kit\Features\Core\Principal($both->id)));
    check($moved->id === $movable->id && $moved->workspace_id === (int) $other->id && DB::table('tickets')->where('id', $movable->id)->value('workspace_id') === (int) $other->id, 'an admin of both moves the row: same id, now in the target');
    $audit = DB::table('audit_events')->where('event', 'workspace.row.moved')->first();
    check($audit !== null && (int) $audit->workspace_id === (int) $workspace->id && (int) $audit->actor_id === $both->id && json_decode($audit->details, true) === ['from' => (int) $workspace->id, 'to' => (int) $other->id, 'model' => (new Ticket)->getMorphClass(), 'id' => $movable->id], 'and the move is audited as workspace.row.moved, with from, to, model and id');
    check($in($other, fn () => Ticket::find($movable->id))?->title === 'Movable' && $in($workspace, fn () => Ticket::find($movable->id)) === null, 'the target workspace reads it; the source no longer does');
    check(refused(fn () => $in($other, fn () => Ticket::findOrFail($movable->id)->fill(['workspace_id' => $workspace->id])->save())) === 'workspace_mismatch', 'a plain update still cannot move a row: the updating guard is untouched');
    check(! method_exists(Ticket::class, 'withoutWorkspaceGuard') && ! property_exists(Ticket::class, 'bypass'), 'and there is no public bypass of it');

    // A model's own, row-aware move ability: its owner self-moves between workspaces they are an active member of, without members.manage.
    Schema::create('owned_apps', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
    });
    Illuminate\Support\Facades\Gate::define('owned-app.move', fn ($user, OwnedApp $app, $workspace) => $app->user_id === (int) $user->getKey());
    $presenter = $member('presenter@example.test', [[$workspace, 'member'], [$other, 'member']]);
    $colleague = $member('colleague@example.test', [[$workspace, 'member'], [$other, 'member']]);
    $sourceMember = $member('only-source@example.test', [[$workspace, 'member']]);
    $denial = function (callable $run): ?string {
        try {
            $run();
        } catch (Illuminate\Auth\Access\AuthorizationException $error) {
            return $error->response()?->code();
        }

        return null;
    };
    $principal = fn (HttpUser $user) => new Tey\Kit\Features\Core\Principal($user->id);
    $app_ = $in($workspace, fn () => OwnedApp::create(['user_id' => $presenter->id]));
    check($denial(fn () => $in($workspace, fn () => $move::run(OwnedApp::findOrFail($app_->id), $other, $principal($colleague)))) === 'permission_denied' && DB::table('owned_apps')->where('id', $app_->id)->value('workspace_id') === (int) $workspace->id, 'with the model\'s own ability, a member who does not own the row is refused');
    $sourceApp = $in($workspace, fn () => OwnedApp::create(['user_id' => $sourceMember->id]));
    check($denial(fn () => $in($workspace, fn () => $move::run(OwnedApp::findOrFail($sourceApp->id), $other, $principal($sourceMember)))) === 'workspace_not_found', 'the owner is refused when not a member of the target, even though the ability would allow it');
    OwnedApp::$moveAbility = 'nobody-defines.this';
    check($denial(fn () => $in($workspace, fn () => $move::run(OwnedApp::findOrFail($app_->id), $other, $principal($presenter)))) === 'ability_unknown', 'an ability no gate or policy defines is refused, fail closed');
    OwnedApp::$moveAbility = 'owned-app.move';
    $selfMoved = $in($workspace, fn () => $move::run(OwnedApp::findOrFail($app_->id), $other, $principal($presenter)));
    check($selfMoved->workspace_id === (int) $other->id && DB::table('audit_events')->where('event', 'workspace.row.moved')->where('actor_id', $presenter->id)->exists(), 'its owner, a plain member of both without members.manage, self-moves it, audited');
    check($denial(fn () => $in($workspace, fn () => $move::run(Ticket::findOrFail($in($workspace, fn () => Ticket::create(['title' => 'Default'])->id)), $other, $principal($presenter)))) === 'permission_denied', 'a model without its own ability still needs members.manage in both: the plain member is refused');

    echo "Workspace model concerns on ".(DB::connection()->getDriverName() === 'pgsql' ? 'PostgreSQL' : 'SQLite memory')." with real Concierge and multitenancy. The database-level foreign key and any check constraints are the app's migration's; this checks the model layer.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
