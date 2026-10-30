<?php

// Ownership transfer, workspace deletion and account deletion over HTTP: who may, the typed confirmations, the scoped-guest refusal, and the lifecycle events
// dispatched after the commit (never on a rollback). SQLite memory, real Concierge 0.8 tables, spatie teams, Pennant and Inertia, synthetic users.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\TestHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge;
use Tey\Kit\Features\SessionUi\Contracts\DeletesAccountRow;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;
use Tey\Kit\Features\SessionUi\Events\AccountDeleting;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Operations\FinishAccountDeletion;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Contracts\DefersWorkspacePurge;
use Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace;
use Tey\Kit\Features\Workspaces\Operations\FinishWorkspaceDeletion;
use Tey\Kit\Features\Workspaces\TenantBoundary;
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

final class GuestAwareConcierge extends Concierge
{
    /** @var list<int> */
    public static array $guests = [];

    public function isScopedGuest(Membership $membership): bool
    {
        return in_array((int) $membership->getKey(), self::$guests, true);
    }
}

/** A deletion that forgets one RESTRICT table: the database must refuse it, and nothing may be deleted or announced. */
final class DeleteWorkspaceSkippingGrants extends DeleteWorkspace
{
    protected function featureTables(): array
    {
        return array_values(array_diff(parent::featureTables(), ['complimentary_grants']));
    }
}

/** The recommended purge listener: plain queued (not TenantAware), working from the ids in the event. */
final class PurgeWorkspaceData implements ShouldQueue
{
    public function handle(WorkspaceDeleted $event): void {}
}

final class PurgeAccountData implements ShouldQueue
{
    public function handle(AccountDeleted $event): void {}
}

/** The host's purge handlers: each records what it saw (the transaction level, whether the kit's rows were still there) and can be told to throw. */
final class RecordingWorkspacePurge implements PurgesWorkspaceData
{
    public static array $seen = [];

    public static bool $throw = false;

    public function purge(Tey\Concierge\Contracts\Workspace $workspace): void
    {
        self::$seen[] = [(int) $workspace->getKey(), DB::transactionLevel(), ConciergeModels::membership()::query()->where('workspace_id', $workspace->getKey())->count()];
        if (self::$throw) {
            throw new RuntimeException('host purge failed');
        }
    }
}

final class RecordingAccountPurge implements PurgesAccountData
{
    public static array $seen = [];

    public static bool $throw = false;

    public function purge(Model $user): void
    {
        self::$seen[] = [(int) $user->getKey(), DB::transactionLevel(), HttpUser::query()->whereKey($user->getKey())->exists()];
        if (self::$throw) {
            throw new RuntimeException('host purge failed');
        }
    }
}

/** Deferred purges: begin() records what it saw (the transaction level, whether the workspace or account was already closed); purge() must never run. */
final class DeferredWorkspacePurge implements DefersWorkspacePurge
{
    public static array $begun = [];

    public function purge(Tey\Concierge\Contracts\Workspace $workspace): void
    {
        throw new RuntimeException('purge() ran in the deferred mode');
    }

    public function begin(Tey\Concierge\Contracts\Workspace $workspace): void
    {
        self::$begun[] = [(int) $workspace->getKey(), DB::transactionLevel(), DB::table('workspaces')->where('id', $workspace->getKey())->whereNotNull('deleting_at')->exists()];
    }
}

final class DeferredAccountPurge implements DefersAccountPurge
{
    public static array $begun = [];

    public function purge(Model $user): void
    {
        throw new RuntimeException('purge() ran in the deferred mode');
    }

    public function begin(Model $user): void
    {
        self::$begun[] = [(int) $user->getKey(), DB::transactionLevel(), DB::table('kit_account_deletions')->where('user_id', $user->getKey())->exists()];
    }
}

/** A host that keeps history on RESTRICT keys to users: the row is anonymised, never deleted. */
final class AnonymisingAccountRow implements DeletesAccountRow
{
    public function delete(Model $user): void
    {
        $user->forceFill(['name' => 'Deleted person', 'email' => 'deleted-'.$user->getKey().'@example.invalid'])->save();
    }
}

/** The refusal an operation throws, or null when it does not. */
function refusal(callable $operation): ?string
{
    try {
        $operation();
    } catch (RuntimeException $refused) {
        return $refused->getMessage();
    }

    return null;
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    // SQLite memory with foreign keys enforced by default. DB_CONNECTION=pgsql runs against the PostgreSQL the DB_* variables name (CI's service container),
    // in a schema this check creates and drops itself: never an existing one.
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $schema = 'kit_lifecycle_'.bin2hex(random_bytes(4));
    $pg = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'prefer'];
    $testing = $pgsql ? $pg + ['search_path' => $schema] : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
    $base = sys_get_temp_dir().'/kit-lifecycle-pages-'.bin2hex(random_bytes(4));
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
        'database' => ['default' => 'testing', 'connections' => ['testing' => $testing]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp', 'notes'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => TestHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        FoundationServiceProvider::class, EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class, MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // A stand-in for Concierge 0.9's scoped guests (0.8 has none): the kit's guard calls isScopedGuest() whenever Concierge has it.
    $app->singleton(Concierge::class, GuestAwareConcierge::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    // The transfer and the deletions are rate limited, as on a host.
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    if ($pgsql) {
        $config->set('database.connections.pg_admin', $pg + ['search_path' => 'public']);
        DB::connection('pg_admin')->statement('create schema '.$schema);
        register_shutdown_function(function () use ($schema): void {
            DB::disconnect('testing');
            DB::connection('pg_admin')->statement('drop schema if exists '.$schema.' cascade');
        });
    }
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    // Core's and workspaces' stubs first: Concierge's tables reference `workspaces`, which PostgreSQL (unlike SQLite) needs to exist already.
    foreach ($stubs as $name => $file) {
        if (strcmp($name, '0001_01_02_000300') < 0) {
            (require $file)->up();
            unset($stubs[$name]);
        }
    }
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
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);
    $concierge = app(Concierge::class);
    check($concierge instanceof GuestAwareConcierge, 'the scoped-guest stand-in is the Concierge the kit resolves');
    // Foreign keys are enforced: a RESTRICT row a deletion missed fails it, instead of passing silently.
    check($pgsql ? DB::selectOne('select current_schema() as name')->name === $schema : (int) (DB::selectOne('PRAGMA foreign_keys')->foreign_keys ?? 0) === 1, $pgsql ? 'PostgreSQL runs in the schema this check created' : 'SQLite enforces foreign keys in this check');

    $people = [];
    foreach (['owner', 'admin', 'member', 'guest', 'leaver', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make('secret-'.$name)]);
    }
    $join = function (Workspace $workspace, string $who, string $role) use (&$people, $concierge): Membership {
        $membership = ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$who]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $concierge->context()->run($workspace, fn () => $people[$who]->assignRole($role), [$people[$who]]);
        if ($role === 'owner') {
            ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $membership->id]);
        }

        return $membership;
    };
    $workspace = function (string $name, string $slug, array $members) use ($registry, $join): array {
        $created = Workspace::create(['name' => $name, 'slug' => $slug]);
        $created->execute(function () use ($registry, $created): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($created);
            }
        });
        $memberships = [];
        foreach ($members as $who => $role) {
            $memberships[$who] = $join($created, $who, $role);
        }

        return [$created->refresh(), $memberships];
    };
    [$acme, $am] = $workspace('Acme Travel', 'acme', ['owner' => 'owner', 'admin' => 'admin', 'member' => 'member', 'guest' => 'member', 'leaver' => 'member']);
    [$beta, $bm] = $workspace('Northwind Farms', 'northwind', ['outsider' => 'owner', 'leaver' => 'member']);
    GuestAwareConcierge::$guests = [(int) $am['guest']->id];
    $sync->run(new ArrayInput([]), new NullOutput);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $http = function (string $who, string $method, string $path, array $data = [], bool $inertia = false) use ($app, &$people): array {
        $user = $people[$who];
        Auth::guard('web')->setUser($user);
        $server = $inertia ? ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_ACCEPT' => 'application/json'];
        $request = Request::create('https://kit.test'.$path, $method, $data, server: $server);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
            if (getenv('KIT_DEBUG') && $response->getStatusCode() >= 500) {
                fwrite(STDERR, get_class($error).': '.$error->getMessage().' @ '.$error->getFile().':'.$error->getLine()."\n");
            }
        }
        Feature::flushCache();

        return [$response->getStatusCode(), (string) $response->getContent()];
    };
    $owner = fn (Workspace $at): int => (int) ConciergeModels::ownership()::query()->whereKey($at->id)->value('membership_id');
    $roles = fn (Workspace $at, string $who): array => $concierge->roleNames(ConciergeModels::membership()::query()->where('workspace_id', $at->id)->where('user_id', $people[$who]->id)->firstOrFail());

    // Transfer ownership.
    $transfer = fn (string $who, int $to, string $confirmation = 'Acme Travel', ?string $password = null) => $http($who, 'PUT', '/@acme/settings/ownership', ['membership' => $to, 'password' => $password ?? 'secret-'.$who, 'confirmation' => $confirmation]);
    [$status, $body] = $http('owner', 'GET', '/@acme/settings/people');
    check($status === 200 && str_contains($body, '\/members\/'.$am['member']->id.'\/transfer') && ! str_contains($body, '\/members\/'.$am['guest']->id.'\/transfer'), 'People offers Make owner on a full member, as the Transfer modal, and never on a scoped guest');
    [$status, $body] = $http('owner', 'GET', '/@acme/settings/people/members/'.$am['member']->id.'/transfer', inertia: true);
    check($status === 200 && str_contains($body, 'Workspace\/Settings\/People\/Transfer\/Page') && str_contains($body, '"workspace_name":"Acme Travel"'), 'the owner opens the Transfer ownership modal, which names the workspace to type');
    check($http('admin', 'GET', '/@acme/settings/people/members/'.$am['member']->id.'/transfer', inertia: true)[0] === 403, 'an admin cannot open the Transfer modal (403)');
    check($http('owner', 'GET', '/@acme/settings/people/members/'.$am['guest']->id.'/transfer', inertia: true)[0] === 403, 'the Transfer modal is refused for a scoped guest (403)');
    check($transfer('admin', $am['member']->id)[0] === 403 && $owner($acme) === $am['owner']->id, 'an admin is refused the transfer (403) and nothing changes');
    check($transfer('member', $am['admin']->id)[0] === 403 && $owner($acme) === $am['owner']->id, 'a member is refused the transfer (403) and nothing changes');
    check($transfer('outsider', $am['member']->id)[0] === 404 && $owner($acme) === $am['owner']->id, 'a non-member gets 404 and nothing changes');
    check($transfer('owner', $am['guest']->id)[0] === 403 && $owner($acme) === $am['owner']->id, 'a scoped guest is refused as the recipient on the server (403)');
    [$status, $body] = $transfer('owner', $am['member']->id, 'Acme');
    check($status === 422 && str_contains($body, 'confirmation') && $owner($acme) === $am['owner']->id, 'a wrong typed confirmation is refused (422) and nothing changes');
    check($transfer('owner', $am['member']->id, 'Acme Travel', 'wrong-password')[0] === 422 && $owner($acme) === $am['owner']->id, 'a wrong password is refused (422)');
    [$status] = $transfer('owner', $am['member']->id);
    check($status === 200 && $owner($acme) === $am['member']->id && $roles($acme, 'member') === ['owner'] && $roles($acme, 'owner') === ['admin'], 'the owner transfers to a full member: they become owner and the old owner is demoted to admin (Concierge)');
    [$status] = $transfer('member', $am['owner']->id);
    check($status === 200 && $owner($acme) === $am['owner']->id && $roles($acme, 'owner') === ['owner'] && $roles($acme, 'member') === ['admin'], 'the new owner can transfer it back');

    // `throttle:5,1` keys on the signed-in user, across routes: each section starts with a fresh limiter, as a minute later would.
    $app['cache']->store()->clear();

    // Workspace deletion: a third workspace with a note, a setting, an override and an audit row.
    [$doomed, $dm] = $workspace('Doomed Co', 'doomed', ['owner' => 'owner', 'admin' => 'admin', 'member' => 'member']);
    $doomed->execute(function (): void {
        $settings = WorkspaceSettings::current();
        $settings->display_name = 'Doomed display';
        $settings->save();
    });
    DB::table('kit_notes')->insert(['workspace_id' => $doomed->id, 'author_id' => $people['owner']->id, 'body' => 'A note', 'colour' => 'yellow', 'pinned' => false, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('kit_capability_overrides')->insert(['workspace_id' => $doomed->id, 'user_id' => $people['member']->id, 'capability' => 'workspace.dashboard', 'enabled' => true]);
    // Commercial records: RESTRICT foreign keys, so the deletion removes them itself, before the workspace row.
    DB::table('entitlements')->insert(['workspace_id' => $doomed->id, 'kind' => 'synthetic-plan']);
    DB::table('complimentary_grants')->insert(['workspace_id' => $doomed->id, 'granted_by' => $people['owner']->id, 'intent_key' => 'grant-1', 'fingerprint' => 'synthetic', 'reason' => 'synthetic', 'expires_at' => now()->addMonth()]);
    DB::table('audit_events')->insert(['actor_id' => $people['owner']->id, 'workspace_id' => $doomed->id, 'event' => 'synthetic.audit', 'details' => '{}', 'created_at' => now()]);
    $counts = fn (): array => [Workspace::query()->whereKey($doomed->id)->count(), ConciergeModels::membership()::query()->where('workspace_id', $doomed->id)->count(), ConciergeModels::ownership()::query()->whereKey($doomed->id)->count(), DB::table('kit_notes')->where('workspace_id', $doomed->id)->count(), DB::table('settings')->where('group', 'workspace.'.$doomed->id)->count() > 0 ? 1 : 0, DB::table('kit_capability_overrides')->where('workspace_id', $doomed->id)->count(), DB::table('entitlements')->where('workspace_id', $doomed->id)->count(), DB::table('complimentary_grants')->where('workspace_id', $doomed->id)->count()];
    $refused = false;
    try {
        DB::transaction(fn () => DB::table('workspaces')->where('id', $doomed->id)->delete());
    } catch (QueryException) {
        $refused = true;
    }
    check($refused, 'a raw delete of a workspace that still has rows on RESTRICT keys is refused by the database');
    check($counts() === [1, 3, 1, 1, 1, 1, 1, 1], 'the doomed workspace has its membership, ownership, note, settings, override, entitlement and complimentary grant rows');
    $seen = [];
    Event::listen(WorkspaceDeleting::class, function (WorkspaceDeleting $event) use (&$seen): void {
        $seen[] = ['deleting', DB::transactionLevel(), Workspace::query()->whereKey($event->workspaceId)->exists()];
    });
    Event::listen(WorkspaceDeleted::class, function (WorkspaceDeleted $event) use (&$seen): void {
        $seen[] = ['deleted', DB::transactionLevel(), Workspace::query()->whereKey($event->workspaceId)->exists(), $event->workspaceId, $event->slug, $event->memberUserIds, $event->actorId];
    });
    Event::listen(WorkspaceDeleted::class, PurgeWorkspaceData::class);
    $delete = fn (string $who, string $confirmation = 'Doomed Co', ?string $password = null) => $http($who, 'DELETE', '/@doomed/settings/delete', ['confirmation' => $confirmation, 'password' => $password ?? 'secret-'.$who]);
    // Off by default: the page and the action are 404, the settings landing does not list it, and nothing is deleted.
    check($config->get('kit.lifecycle.workspace_delete') === false && $http('owner', 'GET', '/@doomed/settings/delete', inertia: true)[0] === 404, 'workspace deletion is off by default: its page is 404');
    check($delete('owner')[0] === 404 && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'while off the deletion is 404: nothing deleted, no event');
    check(! str_contains($http('owner', 'GET', '/@doomed/settings', inertia: true)[1], 'settings\/delete'), 'while off the settings landing does not list Delete workspace');
    // On without a purge handler: refused as a configuration error, nothing deleted.
    $config->set('kit.lifecycle.workspace_delete', true);
    check($delete('owner')[0] === 500 && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'on without a PurgesWorkspaceData handler the deletion refuses: nothing deleted, no event');
    check(str_contains($http('owner', 'GET', '/@doomed/settings', inertia: true)[1], 'settings\/delete'), 'when on the settings landing lists Delete workspace');
    $app->bind(PurgesWorkspaceData::class, RecordingWorkspacePurge::class);
    // A handler that throws rolls the whole deletion back.
    RecordingWorkspacePurge::$throw = true;
    check($delete('owner')[0] === 500 && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && count(RecordingWorkspacePurge::$seen) === 1, 'a purge handler that throws rolls the deletion back: nothing deleted');
    RecordingWorkspacePurge::$throw = false;
    RecordingWorkspacePurge::$seen = [];
    $seen = [];
    $app['cache']->store()->clear();
    [$status, $body] = $http('owner', 'GET', '/@doomed/settings/delete', inertia: true);
    check($status === 200 && str_contains($body, 'Workspace\/Settings\/Delete\/Page'), 'the owner opens Settings › Delete workspace');
    check($http('admin', 'GET', '/@doomed/settings/delete', inertia: true)[0] === 403, 'an admin cannot open Delete workspace (403)');
    check($delete('admin')[0] === 403 && $counts()[0] === 1, 'an admin is refused the deletion (403)');
    check($delete('member')[0] === 403 && $counts()[0] === 1, 'a member is refused the deletion (403)');
    check($delete('outsider')[0] === 404 && $counts()[0] === 1, 'a non-member gets 404');
    [$status, $body] = $delete('owner', 'Doomed');
    check($status === 422 && str_contains($body, 'confirmation') && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'a wrong typed confirmation is refused (422): nothing deleted, no event');

    // The current password is asked for too, on by default: missing or wrong is a 422 and nothing changes. Only `false` exactly turns it off (fails closed).
    check(str_contains($http('owner', 'GET', '/@doomed/settings/delete', inertia: true)[1], '"confirms_password":true'), 'the Delete workspace page asks for the password by default');
    [$status, $body] = $http('owner', 'DELETE', '/@doomed/settings/delete', ['confirmation' => 'Doomed Co']);
    check($status === 422 && str_contains($body, 'password') && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'a workspace deletion without the password is refused (422): nothing deleted, no event');
    [$status, $body] = $delete('owner', 'Doomed Co', 'wrong-password');
    check($status === 422 && str_contains($body, 'password') && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'a workspace deletion with a wrong password is refused (422): nothing deleted, no event');
    $config->set('kit.lifecycle.confirm_password', 'nope');
    check($http('owner', 'DELETE', '/@doomed/settings/delete', ['confirmation' => 'Doomed Co'])[0] === 422 && $counts()[0] === 1, 'a confirm_password value other than false keeps the password required (fails closed)');
    $config->set('kit.lifecycle.confirm_password', true);
    $app['cache']->store()->clear();

    // A failure inside the transaction (a WorkspaceDeleting listener throws after the event): everything rolls back and WorkspaceDeleted never fires.
    $fail = true;
    Event::listen(WorkspaceDeleting::class, function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('forced failure inside the deletion');
        }
    });
    Queue::fake();
    [$status] = $delete('owner');
    check($status === 500 && $counts() === [1, 3, 1, 1, 1, 1, 1, 1], 'a failure inside the transaction rolls the deletion back');
    check(array_column($seen, 0) === ['deleting'] && $seen[0][1] >= 1 && $seen[0][2] === true, 'WorkspaceDeleting ran inside the transaction with the workspace still there');
    check(Queue::pushedJobs() === [], 'on the rollback WorkspaceDeleted was never dispatched and no queued listener was pushed');

    $fail = false;
    // A failure after WorkspaceDeleted was dispatched: the deletion runs to its end inside an outer transaction that then rolls back. The deferred event is dropped.
    $seen = [];
    Auth::guard('web')->setUser($people['owner']);
    DB::beginTransaction();
    try {
        app(TenantBoundary::class)->run($doomed->id, fn () => DeleteWorkspace::run($people['owner'], 'Doomed Co', 'secret-owner'));
        $inside = Workspace::query()->whereKey($doomed->id)->exists();
    } finally {
        DB::rollBack();
    }
    check($inside === false && $counts() === [1, 3, 1, 1, 1, 1, 1, 1], 'a deletion that completed inside a transaction which then rolled back leaves every row');
    check(array_column($seen, 0) === ['deleting'] && Queue::pushedJobs() === [], 'WorkspaceDeleted, dispatched before the rollback, is dropped with it: no listener ran and nothing was queued');

    $seen = [];
    $app['cache']->store()->clear();
    [$status, $body] = $delete('owner');
    $members = [(int) $people['owner']->id, (int) $people['admin']->id, (int) $people['member']->id];
    sort($members);
    check(($purged = end(RecordingWorkspacePurge::$seen)) !== false && $purged[0] === (int) $doomed->id && $purged[1] >= 1 && $purged[2] === 3, 'the purge handler ran inside the transaction, before the kit\'s rows went (its memberships were still there)');
    check($status === 200 && $counts() === [0, 0, 0, 0, 0, 0, 0, 0], 'the owner deletes the workspace with the right confirmation: its memberships, ownership, notes, settings, overrides, entitlement and complimentary grant go with it');
    check(DB::table('audit_events')->where('event', 'synthetic.audit')->count() === 1, 'the audit row is never deleted (its foreign key, not the deletion, decides its workspace_id)');
    check(count($seen) === 2 && $seen[1][0] === 'deleted' && $seen[1][1] === 0 && $seen[1][2] === false, 'WorkspaceDeleted is dispatched after the commit: no open transaction, the workspace already gone');
    check(array_slice($seen[1], 3) === [(int) $doomed->id, 'doomed', $members, (int) $people['owner']->id], 'WorkspaceDeleted carries the workspace id, slug, member user ids and actor id');
    check(Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeWorkspaceData::class && $job->data[0] instanceof WorkspaceDeleted && $job->data[0]->workspaceId === (int) $doomed->id && $job->data[0]->memberUserIds === $members)->count() === 1, 'a queued (not TenantAware) purge listener is pushed once, with the deleted ids');
    check($http('owner', 'GET', '/@doomed/settings', inertia: true)[0] === 404 && Workspace::current() === null, 'the deleted workspace is gone (404) and no tenant stays current');

    // Negative: one RESTRICT table's cleanup skipped. The workspace row's delete fails on the foreign key, everything rolls back, and WorkspaceDeleted never fires.
    $app['cache']->store()->clear();
    [$skipped] = $workspace('Skipped Grants', 'skipped', ['owner' => 'owner']);
    DB::table('complimentary_grants')->insert(['workspace_id' => $skipped->id, 'granted_by' => $people['owner']->id, 'intent_key' => 'grant-skipped', 'fingerprint' => 'synthetic', 'reason' => 'synthetic', 'expires_at' => now()->addMonth()]);
    DB::table('kit_notes')->insert(['workspace_id' => $skipped->id, 'author_id' => $people['owner']->id, 'body' => 'Kept', 'colour' => 'yellow', 'pinned' => false, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $kept = fn (): array => [Workspace::query()->whereKey($skipped->id)->count(), ConciergeModels::membership()::query()->where('workspace_id', $skipped->id)->count(), ConciergeModels::ownership()::query()->whereKey($skipped->id)->count(), DB::table('complimentary_grants')->where('workspace_id', $skipped->id)->count(), DB::table('kit_notes')->where('workspace_id', $skipped->id)->count()];
    $app->bind(DeleteWorkspace::class, DeleteWorkspaceSkippingGrants::class);
    $seen = [];
    $pushed = count(Queue::pushedJobs()[CallQueuedListener::class] ?? []);
    try {
        [$status] = $http('owner', 'DELETE', '/@skipped/settings/delete', ['confirmation' => 'Skipped Grants', 'password' => 'secret-owner']);
    } finally {
        $app->offsetUnset(DeleteWorkspace::class);
    }
    check($status === 500 && $kept() === [1, 1, 1, 1, 1], 'with complimentary_grants\' cleanup skipped the deletion fails on its RESTRICT key and everything rolls back, the notes it had already deleted included');
    check(array_column($seen, 0) === ['deleting'] && count(Queue::pushedJobs()[CallQueuedListener::class] ?? []) === $pushed, 'and WorkspaceDeleted is never dispatched: no listener ran and nothing was queued');
    $app['cache']->store()->clear();
    [$status] = $http('owner', 'DELETE', '/@skipped/settings/delete', ['confirmation' => 'Skipped Grants', 'password' => 'secret-owner']);
    check($status === 200 && array_sum($kept()) === 0, 'the rolled-back workspace is intact, and the real deletion then removes it with its grant');

    // One deletion per installed feature: a workspace holding only that feature's rows, deleted over HTTP, leaves none of them behind.
    $seeds = [
        'access' => function (int $id) use ($people): array {
            DB::table('entitlements')->insert(['workspace_id' => $id, 'kind' => 'synthetic-plan']);
            DB::table('complimentary_grants')->insert(['workspace_id' => $id, 'granted_by' => $people['owner']->id, 'intent_key' => 'grant-'.$id, 'fingerprint' => 'synthetic', 'reason' => 'synthetic', 'expires_at' => now()->addMonth()]);
            DB::table('kit_entitlements')->insert(['workspace_id' => $id, 'entitlement' => 'synthetic', 'included' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('kit_capability_overrides')->insert(['workspace_id' => $id, 'user_id' => $people['member']->id, 'capability' => 'workspace.dashboard', 'enabled' => true]);

            return ['entitlements', 'complimentary_grants', 'kit_entitlements', 'kit_capability_overrides'];
        },
        'mcp' => function (int $id) use ($people): array {
            $connection = DB::table('connections')->insertGetId(['workspace_id' => $id, 'actor_id' => $people['owner']->id, 'scopes' => '[]', 'intent_key' => 'connect-'.$id]);
            DB::table('kit_mcp_intents')->insert(['actor_id' => $people['owner']->id, 'connection_id' => $connection, 'intent_key' => 'intent-'.$id, 'fingerprint' => 'synthetic', 'result' => '{}', 'created_at' => now(), 'expires_at' => now()->addDay()]);

            return ['connections', 'kit_mcp_intents' => $connection];
        },
        'notes' => function (int $id) use ($people): array {
            DB::table('kit_notes')->insert(['workspace_id' => $id, 'author_id' => $people['owner']->id, 'body' => 'A note', 'colour' => 'yellow', 'pinned' => false, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('kit_note_intents')->insert(['workspace_id' => $id, 'actor_id' => $people['owner']->id, 'intent_key' => 'note-'.$id, 'fingerprint' => 'synthetic', 'result' => '{}', 'created_at' => now()]);

            return ['kit_notes', 'kit_note_intents'];
        },
        'settings' => function (int $id) use ($people): array {
            DB::table('settings_intents')->insert(['workspace_id' => $id, 'actor_id' => $people['owner']->id, 'intent_key' => 'settings-'.$id, 'fingerprint' => 'synthetic', 'result' => '{}']);

            return ['settings_intents', 'settings'];
        },
    ];
    foreach ($seeds as $feature => $seed) {
        $app['cache']->store()->clear();
        [$each] = $workspace('Feature '.$feature, 'feature-'.$feature, ['owner' => 'owner']);
        $tables = $seed((int) $each->id);
        $rows = function () use ($tables, $each): array {
            $found = [];
            foreach ($tables as $key => $value) {
                $found[] = match (true) {
                    $value === 'settings' => DB::table('settings')->where('group', 'workspace.'.$each->id)->count(),
                    is_string($key) => DB::table($key)->where('connection_id', $value)->count(),
                    default => DB::table($value)->where('workspace_id', $each->id)->count(),
                };
            }

            return $found;
        };
        check(! in_array(0, $rows(), true), 'the '.$feature.' workspace holds a row in each of its tables before the deletion');
        // The first one with kit.lifecycle.confirm_password off: no password is asked for.
        $off = $feature === array_key_first($seeds);
        $config->set('kit.lifecycle.confirm_password', ! $off);
        [$status] = $http('owner', 'DELETE', '/@feature-'.$feature.'/settings/delete', ['confirmation' => 'Feature '.$feature] + ($off ? [] : ['password' => 'secret-owner']));
        $config->set('kit.lifecycle.confirm_password', true);
        check($status === 200 && Workspace::query()->whereKey($each->id)->doesntExist() && array_sum($rows()) === 0, 'deleting a workspace with '.$feature.' rows succeeds and leaves none behind');
    }

    // Deferred workspace deletion: the handler implements DefersWorkspacePurge. The workspace closes at once and nothing is deleted until the finish hook.
    $app['cache']->store()->clear();
    [$closing, $cm] = $workspace('Closing Co', 'closing', ['owner' => 'owner', 'admin' => 'admin', 'member' => 'member']);
    DB::table('kit_notes')->insert(['workspace_id' => $closing->id, 'author_id' => $people['owner']->id, 'body' => 'A note', 'colour' => 'yellow', 'pinned' => false, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('kit_capability_overrides')->insert(['workspace_id' => $closing->id, 'user_id' => $people['member']->id, 'capability' => 'workspace.dashboard', 'enabled' => true]);
    $closingRows = fn (): array => [Workspace::query()->whereKey($closing->id)->count(), ConciergeModels::membership()::query()->where('workspace_id', $closing->id)->count(), ConciergeModels::ownership()::query()->whereKey($closing->id)->count(), DB::table('kit_notes')->where('workspace_id', $closing->id)->count(), DB::table('kit_capability_overrides')->where('workspace_id', $closing->id)->count()];
    $admits = fn (string $who, Workspace $at): ?string => refusal(fn () => app(TenantBoundary::class)->run((int) $at->id, fn () => app(Access::class)->authorize(new Principal((int) $people[$who]->id), 'dashboard.read')));
    check($admits('member', $closing) === null && $closing->conciergeAcceptsMembers() && in_array((int) $closing->id, Workspace::query()->memberOf($people['member'])->pluck('id')->map(fn ($id): int => (int) $id)->all(), true), 'before its deletion the workspace admits its members, accepts members and is listed');
    $app->bind(PurgesWorkspaceData::class, DeferredWorkspacePurge::class);
    $seen = [];
    $deletedBefore = count(Queue::pushedJobs()[CallQueuedListener::class] ?? []);
    [$status, $body] = $http('owner', 'DELETE', '/@closing/settings/delete', ['confirmation' => 'Closing Co', 'password' => 'secret-owner']);
    $closing->refresh();
    check($status === 200 && str_contains($body, '"deleting":true') && $closing->isDeleting() && (int) $closing->deleting_by === (int) $people['owner']->id, 'deferred: the owner\'s deletion closes the workspace at once (deleting_at, deleting_by) and answers deleting');
    check($closingRows() === [1, 3, 1, 1, 1], 'deferred: nothing is deleted in the request, the kit\'s and Concierge\'s rows included');
    check(DeferredWorkspacePurge::$begun === [[(int) $closing->id, 0, true]], 'deferred: begin() ran once, after the commit (no open transaction), with the workspace already closed; purge() never ran');
    check(array_column($seen, 0) === ['deleting'] && count(Queue::pushedJobs()[CallQueuedListener::class] ?? []) === $deletedBefore, 'deferred: WorkspaceDeleting is dispatched at the close, WorkspaceDeleted not yet');
    check(DB::table('audit_events')->where('workspace_id', $closing->id)->where('event', 'workspace.deletion_started')->where('actor_id', $people['owner']->id)->count() === 1, 'deferred: the close is audited, with the owner as actor');
    check($admits('owner', $closing) === 'workspace_deleting' && $admits('admin', $closing) === 'workspace_deleting' && $admits('member', $closing) === 'workspace_deleting', 'closed: admission is refused for every member, the owner included, as workspace_deleting');
    check($admits('outsider', $closing) === 'workspace_not_found', 'closed: a non-member still finds it absent (workspace_not_found), never told it is being deleted');
    check(! $closing->conciergeAcceptsMembers(), 'closed: the workspace accepts no new members (Concierge sees workspace_unavailable)');
    check(! in_array((int) $closing->id, Workspace::query()->memberOf($people['member'])->pluck('id')->map(fn ($id): int => (int) $id)->all(), true), 'closed: it is hidden from its members\' workspaces (the switcher and lists)');
    [$status, $body] = $http('member', 'GET', '/@acme/settings', inertia: true);
    check($status === 200 && ! str_contains($body, 'Closing Co') && str_contains($body, 'Acme Travel'), 'closed: the switcher on another workspace\'s page does not offer it, and still offers the others');
    [$status, $body] = $http('member', 'GET', '/@closing/settings', inertia: true);
    check($status === 302 && str_contains($body, '/me/workspaces') && str_contains(json_encode($app['session']->driver()->get('inertia.flash_data', $app['session']->driver()->all())), 'Closing Co is being deleted.'), 'closed: a member opening its pages is sent to the workspace list with a notice that the deletion is in progress');
    check($http('owner', 'GET', '/@closing/settings/delete')[0] === 403 && $http('outsider', 'GET', '/@closing/settings')[0] === 404, 'closed: a JSON request is refused (403 workspace_deleting) and a non-member gets 404');
    $invitations = ConciergeModels::invitation()::query()->where('workspace_id', $closing->id)->count();
    check($http('owner', 'POST', '/@closing/settings/people/invitations', ['email' => 'new@example.test', 'role' => 'member'])[0] === 403 && ConciergeModels::invitation()::query()->where('workspace_id', $closing->id)->count() === $invitations, 'closed: no invitation can be sent');
    check(refusal(fn () => app(TenantBoundary::class)->run((int) $closing->id, fn () => app(ManagePeople::class)->invite($people['owner'], 'new@example.test', 'member'))) === 'workspace_deleting', 'closed: the invite operation itself refuses (workspace_deleting), whatever route reaches it');
    $app['cache']->store()->clear();
    check($http('owner', 'DELETE', '/@closing/settings/delete', ['confirmation' => 'Closing Co', 'password' => 'secret-owner'])[0] === 403 && count(DeferredWorkspacePurge::$begun) === 1, 'closed: deleting it again is refused and begin() is not run twice');
    // The finish hook.
    check(refusal(fn () => app(FinishWorkspaceDeletion::class)((int) $acme->id)) === 'workspace_not_deleting' && Workspace::query()->whereKey($acme->id)->exists() && ConciergeModels::membership()::query()->where('workspace_id', $acme->id)->count() > 0, 'the finish hook refuses a workspace that is not being deleted, and deletes nothing');
    $seen = [];
    $finished = app(FinishWorkspaceDeletion::class)((int) $closing->id);
    check($finished === true && $closingRows() === [0, 0, 0, 0, 0], 'the finish hook deletes the workspace with its kit and Concierge rows');
    $members = [(int) $people['owner']->id, (int) $people['admin']->id, (int) $people['member']->id];
    sort($members);
    check(array_column($seen, 0) === ['deleted'] && $seen[0][1] === 0 && array_slice($seen[0], 3) === [(int) $closing->id, 'closing', $members, (int) $people['owner']->id], 'the finish hook dispatches WorkspaceDeleted after its commit, with the ids and the owner who closed it');
    check(Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeWorkspaceData::class && $job->data[0]->workspaceId === (int) $closing->id)->count() === 1, 'after the finish hook a queued WorkspaceDeleted listener is pushed once');
    $seen = [];
    check(app(FinishWorkspaceDeletion::class)((int) $closing->id) === false && $seen === [] && Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeWorkspaceData::class && $job->data[0]->workspaceId === (int) $closing->id)->count() === 1, 'calling the finish hook twice is a no-op: nothing deleted or announced again');
    $app->bind(PurgesWorkspaceData::class, RecordingWorkspacePurge::class);

    // Account deletion.
    $app['cache']->store()->clear();
    $seen = [];
    Event::listen(AccountDeleting::class, function (AccountDeleting $event) use (&$seen): void {
        $seen[] = ['deleting', DB::transactionLevel(), HttpUser::query()->whereKey($event->userId)->exists()];
    });
    Event::listen(AccountDeleted::class, function (AccountDeleted $event) use (&$seen): void {
        $seen[] = ['deleted', DB::transactionLevel(), HttpUser::query()->whereKey($event->userId)->exists(), $event->userId, $event->workspaceIds];
    });
    Event::listen(AccountDeleted::class, PurgeAccountData::class);
    // Off by default: the page and the action are 404, and nothing is deleted.
    check($http('leaver', 'GET', '/me/delete', inertia: true)[0] === 404 && $http('leaver', 'DELETE', '/me/delete', ['password' => 'secret-leaver'])[0] === 404 && HttpUser::query()->whereKey($people['leaver']->id)->exists() && $seen === [], 'account deletion is off by default: 404, nothing deleted, no event');
    check(! str_contains($http('leaver', 'GET', '/me', inertia: true)[1], 'me\/delete'), 'while off the account settings do not list Delete account');
    $config->set('kit.lifecycle.account_delete', true);
    $memberships = fn (): int => ConciergeModels::membership()::query()->where('user_id', $people['leaver']->id)->count();
    $before = $memberships();
    check($http('leaver', 'DELETE', '/me/delete', ['password' => 'secret-leaver'])[0] === 500 && HttpUser::query()->whereKey($people['leaver']->id)->exists() && $memberships() === $before && $seen === [], 'on without a PurgesAccountData handler the deletion refuses: nothing deleted, no event');
    check(str_contains($http('leaver', 'GET', '/me', inertia: true)[1], 'me\/delete'), 'when on the account settings list Delete account');
    $app->bind(PurgesAccountData::class, RecordingAccountPurge::class);
    RecordingAccountPurge::$throw = true;
    check($http('leaver', 'DELETE', '/me/delete', ['password' => 'secret-leaver'])[0] === 500 && HttpUser::query()->whereKey($people['leaver']->id)->exists() && $memberships() === $before && count(RecordingAccountPurge::$seen) === 1, 'a purge handler that throws rolls the account deletion back: nothing deleted');
    RecordingAccountPurge::$throw = false;
    RecordingAccountPurge::$seen = [];
    $seen = [];
    $app['cache']->store()->clear();
    [$status, $body] = $http('owner', 'GET', '/me/delete', inertia: true);
    check($status === 200 && str_contains($body, 'Workspace\/Account\/Delete\/Page') && str_contains($body, '"owned":["Acme Travel"]'), 'the Delete account page names the workspaces the person owns');
    [$status, $body] = $http('owner', 'DELETE', '/me/delete', ['password' => 'secret-owner']);
    check($status === 422 && str_contains($body, 'Acme Travel') && HttpUser::query()->whereKey($people['owner']->id)->exists() && $seen === [], 'the sole owner of a workspace is refused, with the workspace named: nothing deleted, no event');
    [$status, $body] = $http('leaver', 'DELETE', '/me/delete', ['password' => 'wrong']);
    check($status === 422 && str_contains($body, 'password') && HttpUser::query()->whereKey($people['leaver']->id)->exists() && $seen === [], 'a wrong password is refused (422): nothing deleted, no event');
    $leaverId = (int) $people['leaver']->id;
    $session = $app['session']->driver();
    $session->put('marker', 'before');
    $before = $session->getId();
    [$status] = $http('leaver', 'DELETE', '/me/delete', ['password' => 'secret-leaver']);
    check($status === 200 && ! HttpUser::query()->whereKey($leaverId)->exists() && ConciergeModels::membership()::query()->where('user_id', $leaverId)->count() === 0 && DB::table('model_has_roles')->where('model_id', $leaverId)->count() === 0, 'a member who owns nothing deletes their account: the user, memberships and role rows go');
    check(RecordingAccountPurge::$seen === [[$leaverId, RecordingAccountPurge::$seen[0][1] ?? 0, true]] && RecordingAccountPurge::$seen[0][1] >= 1, 'the account purge handler ran once, inside the transaction, before the user row went');
    check(Auth::guard('web')->guest() && $session->get('marker') === null && $session->getId() !== $before, 'they are logged out and the session is invalidated');
    $left = [(int) $acme->id, (int) $beta->id];
    sort($left);
    check(array_column($seen, 0) === ['deleting', 'deleted'] && $seen[0][1] >= 1 && $seen[0][2] === true, 'AccountDeleting runs inside the transaction, before the user row goes');
    check($seen[1][1] === 0 && $seen[1][2] === false && $seen[1][3] === $leaverId && $seen[1][4] === $left, 'AccountDeleted is dispatched after the commit with the user id and the workspaces they left');
    check(Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeAccountData::class && $job->data[0]->userId === $leaverId)->count() === 1, 'a queued purge listener is pushed once, with the deleted user id');
    check($owner($acme) === $am['owner']->id && $owner($beta) === $bm['outsider']->id, 'the workspaces they left keep their owners');

    // Deferred account deletion: the handler implements DefersAccountPurge. The account closes at once and nothing is deleted until the finish hook.
    $app['cache']->store()->clear();
    $people['closer'] = HttpUser::create(['name' => 'Closer', 'email' => 'closer@example.test', 'email_verified_at' => now(), 'password' => Hash::make('secret-closer')]);
    $join($acme, 'closer', 'member');
    $closerId = (int) $people['closer']->id;
    DB::table('oauth_access_tokens')->insert(['id' => str_repeat('a', 80), 'user_id' => $closerId, 'client_id' => '00000000-0000-0000-0000-000000000000', 'name' => 'synthetic', 'scopes' => '[]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addDay()]);
    $app->bind(PurgesAccountData::class, DeferredAccountPurge::class);
    $seen = [];
    $accountsBefore = Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeAccountData::class)->count();
    [$status, $body] = $http('closer', 'DELETE', '/me/delete', ['password' => 'secret-closer']);
    check($status === 200 && str_contains($body, '"deleting":true') && HttpUser::query()->whereKey($closerId)->exists(), 'deferred: the account deletion answers deleting and the user row stays for the host\'s job');
    check(! ConciergeModels::account()::query()->find($closerId)?->isActive() && DB::table('kit_account_deletions')->where('user_id', $closerId)->exists(), 'deferred: the account is closed at once (its Concierge account deactivated, a kit_account_deletions row)');
    check(ConciergeModels::membership()::query()->where('user_id', $closerId)->where('status', MembershipStatus::Active)->doesntExist() && (bool) DB::table('oauth_access_tokens')->where('user_id', $closerId)->value('revoked') && Auth::guard('web')->guest(), 'deferred: their workspaces are left, their API tokens revoked and the session signed out');
    check(DeferredAccountPurge::$begun === [[$closerId, 0, true]] && array_column($seen, 0) === ['deleting'], 'deferred: begin() ran once after the commit, with the account already closed; AccountDeleting at the close, AccountDeleted not yet');
    $signIn = null;
    try {
        Auth::guard('web')->attempt(['email' => 'closer@example.test', 'password' => 'secret-closer']);
    } catch (ValidationException $refused) {
        $signIn = $refused->errors()['email'][0] ?? null;
    }
    check(is_string($signIn) && Auth::guard('web')->guest(), 'closed: signing in is refused with a message ('.$signIn.')');
    check(refusal(fn () => app(FinishAccountDeletion::class)((int) $people['member']->id)) === 'account_not_deleting' && HttpUser::query()->whereKey($people['member']->id)->exists(), 'the account finish hook refuses an account that is not being deleted, and deletes nothing');
    $seen = [];
    check(app(FinishAccountDeletion::class)($closerId) === true && ! HttpUser::query()->whereKey($closerId)->exists() && ConciergeModels::membership()::query()->where('user_id', $closerId)->count() === 0 && ConciergeModels::account()::query()->whereKey($closerId)->doesntExist() && DB::table('kit_account_deletions')->where('user_id', $closerId)->doesntExist(), 'the account finish hook deletes the user, their memberships, Concierge account and the deletion record');
    check(array_column($seen, 0) === ['deleted'] && $seen[0][1] === 0 && $seen[0][3] === $closerId && $seen[0][4] === [(int) $acme->id] && Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeAccountData::class)->count() === $accountsBefore + 1, 'AccountDeleted follows the finish hook\'s commit, with the workspaces they left');
    $seen = [];
    check(app(FinishAccountDeletion::class)($closerId) === false && $seen === [], 'calling the account finish hook twice is a no-op');

    // A host that keeps the user row (RESTRICT history): DeletesAccountRow anonymises it. The finish hook succeeds, the row stays closed, and a retry is a no-op.
    $app['cache']->store()->clear();
    check($http('member', 'DELETE', '/me/delete', [])[0] === 422 && HttpUser::query()->whereKey($people['member']->id)->exists(), 'an account deletion without the password is refused (422)');
    $people['anon'] = HttpUser::create(['name' => 'Anon', 'email' => 'anon@example.test', 'email_verified_at' => now(), 'password' => Hash::make('secret-anon')]);
    $join($acme, 'anon', 'member');
    $anonId = (int) $people['anon']->id;
    $app->bind(DeletesAccountRow::class, AnonymisingAccountRow::class);
    check($http('anon', 'DELETE', '/me/delete', ['password' => 'secret-anon'])[0] === 200 && DB::table('kit_account_deletions')->where('user_id', $anonId)->exists(), 'deferred: the account to anonymise is closed');
    $seen = [];
    check(app(FinishAccountDeletion::class)($anonId) === true && HttpUser::query()->whereKey($anonId)->value('name') === 'Deleted person' && ConciergeModels::membership()::query()->where('user_id', $anonId)->doesntExist(), 'with DeletesAccountRow bound the finish hook anonymises the user row instead of deleting it, and the kit\'s rows go');
    check(! ConciergeModels::account()::query()->find($anonId)?->isActive() && DB::table('kit_account_deletions')->where('user_id', $anonId)->whereNotNull('finished_at')->exists() && array_column($seen, 0) === ['deleted'], 'the anonymised row stays closed (Concierge account deactivated), its record is marked finished, and AccountDeleted is dispatched');
    $refused = false;
    try {
        Auth::guard('web')->attempt(['email' => HttpUser::query()->whereKey($anonId)->value('email'), 'password' => 'secret-anon']);
    } catch (ValidationException) {
        $refused = true;
    }
    check($refused && Auth::guard('web')->guest(), 'the anonymised account cannot sign in');
    $seen = [];
    check(app(FinishAccountDeletion::class)($anonId) === false && $seen === [] && HttpUser::query()->whereKey($anonId)->exists(), 'calling the finish hook again on the anonymised account is a no-op (false)');
    $app->offsetUnset(DeletesAccountRow::class);

    echo 'Lifecycle page checks on '.($pgsql ? 'PostgreSQL (a throwaway schema)' : 'SQLite memory').' with foreign keys enforced, real Concierge 0.8 (scoped guests by a stand-in), spatie teams, Pennant and Inertia; router dispatch without the global middleware stack, no browser, no real queue worker.'."\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
