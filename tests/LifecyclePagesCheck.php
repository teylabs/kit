<?php

// Ownership transfer, workspace deletion and account deletion over HTTP: who may, the typed confirmations, the scoped-guest refusal, and the lifecycle events
// dispatched after the commit (never on a rollback). SQLite memory, real Concierge 0.8 tables, spatie teams, Pennant and Inertia, synthetic users.
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Events\AccountDeleted;
use Tey\Kit\Features\SessionUi\Events\AccountDeleting;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting;
use Tey\Kit\Features\Workspaces\Models\Workspace;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(HttpUser::class, 'App\Models\User');

final class GuestAwareConcierge extends Tey\Concierge\Concierge
{
    /** @var list<int> */
    public static array $guests = [];

    public function isScopedGuest(Membership $membership): bool
    {
        return in_array((int) $membership->getKey(), self::$guests, true);
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

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $base = sys_get_temp_dir().'/kit-lifecycle-pages-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp', 'notes'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => Monolog\Handler\TestHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Foundation\Providers\FoundationServiceProvider::class, Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class, InertiaUI\Modal\ModalServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // A stand-in for Concierge 0.9's scoped guests (0.8 has none): the kit's guard calls isScopedGuest() whenever Concierge has it.
    $app->singleton(Tey\Concierge\Concierge::class, GuestAwareConcierge::class);
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);
    // The transfer and the deletions are rate limited, as on a host.
    $app['router']->aliasMiddleware('throttle', Illuminate\Routing\Middleware\ThrottleRequests::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
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
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);
    $concierge = app(Tey\Concierge\Concierge::class);
    check($concierge instanceof GuestAwareConcierge, 'the scoped-guest stand-in is the Concierge the kit resolves');

    $people = [];
    foreach (['owner', 'admin', 'member', 'guest', 'leaver', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make('secret-'.$name)]);
    }
    $join = function (Workspace $workspace, string $who, string $role) use ($people, $concierge): Membership {
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
    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $http = function (string $who, string $method, string $path, array $data = [], bool $inertia = false) use ($app, $people): array {
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
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
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
        $settings = Tey\Kit\Features\Settings\WorkspaceSettings::current();
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
    check($counts() === [1, 3, 1, 1, 1, 1, 1, 1], 'the doomed workspace has its membership, ownership, note, settings, override, entitlement and complimentary grant rows');
    $seen = [];
    Event::listen(WorkspaceDeleting::class, function (WorkspaceDeleting $event) use (&$seen): void {
        $seen[] = ['deleting', DB::transactionLevel(), Workspace::query()->whereKey($event->workspaceId)->exists()];
    });
    Event::listen(WorkspaceDeleted::class, function (WorkspaceDeleted $event) use (&$seen): void {
        $seen[] = ['deleted', DB::transactionLevel(), Workspace::query()->whereKey($event->workspaceId)->exists(), $event->workspaceId, $event->slug, $event->memberUserIds, $event->actorId];
    });
    Event::listen(WorkspaceDeleted::class, PurgeWorkspaceData::class);
    $delete = fn (string $who, string $confirmation = 'Doomed Co') => $http($who, 'DELETE', '/@doomed/settings/delete', ['confirmation' => $confirmation]);
    [$status, $body] = $http('owner', 'GET', '/@doomed/settings/delete', inertia: true);
    check($status === 200 && str_contains($body, 'Workspace\/Settings\/Delete\/Page'), 'the owner opens Settings › Delete workspace');
    check($http('admin', 'GET', '/@doomed/settings/delete', inertia: true)[0] === 403, 'an admin cannot open Delete workspace (403)');
    check($delete('admin')[0] === 403 && $counts()[0] === 1, 'an admin is refused the deletion (403)');
    check($delete('member')[0] === 403 && $counts()[0] === 1, 'a member is refused the deletion (403)');
    check($delete('outsider')[0] === 404 && $counts()[0] === 1, 'a non-member gets 404');
    [$status, $body] = $delete('owner', 'Doomed');
    check($status === 422 && str_contains($body, 'confirmation') && $counts() === [1, 3, 1, 1, 1, 1, 1, 1] && $seen === [], 'a wrong typed confirmation is refused (422): nothing deleted, no event');

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
        app(Tey\Kit\Features\Workspaces\TenantBoundary::class)->run($doomed->id, fn () => Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace::run($people['owner'], 'Doomed Co'));
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
    check($status === 200 && $counts() === [0, 0, 0, 0, 0, 0, 0, 0], 'the owner deletes the workspace with the right confirmation: its memberships, ownership, notes, settings, overrides, entitlement and complimentary grant go with it');
    check(DB::table('audit_events')->where('event', 'synthetic.audit')->count() === 1, 'the audit row is never deleted (its foreign key, not the deletion, decides its workspace_id)');
    check(count($seen) === 2 && $seen[1][0] === 'deleted' && $seen[1][1] === 0 && $seen[1][2] === false, 'WorkspaceDeleted is dispatched after the commit: no open transaction, the workspace already gone');
    check(array_slice($seen[1], 3) === [(int) $doomed->id, 'doomed', $members, (int) $people['owner']->id], 'WorkspaceDeleted carries the workspace id, slug, member user ids and actor id');
    check(Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeWorkspaceData::class && $job->data[0] instanceof WorkspaceDeleted && $job->data[0]->workspaceId === (int) $doomed->id && $job->data[0]->memberUserIds === $members)->count() === 1, 'a queued (not TenantAware) purge listener is pushed once, with the deleted ids');
    check($http('owner', 'GET', '/@doomed/settings', inertia: true)[0] === 404 && Workspace::current() === null, 'the deleted workspace is gone (404) and no tenant stays current');

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
        [$status] = $http('owner', 'DELETE', '/@feature-'.$feature.'/settings/delete', ['confirmation' => 'Feature '.$feature]);
        check($status === 200 && Workspace::query()->whereKey($each->id)->doesntExist() && array_sum($rows()) === 0, 'deleting a workspace with '.$feature.' rows succeeds and leaves none behind');
    }

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
    check(Auth::guard('web')->guest() && $session->get('marker') === null && $session->getId() !== $before, 'they are logged out and the session is invalidated');
    $left = [(int) $acme->id, (int) $beta->id];
    sort($left);
    check(array_column($seen, 0) === ['deleting', 'deleted'] && $seen[0][1] >= 1 && $seen[0][2] === true, 'AccountDeleting runs inside the transaction, before the user row goes');
    check($seen[1][1] === 0 && $seen[1][2] === false && $seen[1][3] === $leaverId && $seen[1][4] === $left, 'AccountDeleted is dispatched after the commit with the user id and the workspaces they left');
    check(Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PurgeAccountData::class && $job->data[0]->userId === $leaverId)->count() === 1, 'a queued purge listener is pushed once, with the deleted user id');
    check($owner($acme) === $am['owner']->id && $owner($beta) === $bm['outsider']->id, 'the workspaces they left keep their owners');

    echo "Lifecycle page checks on SQLite memory (foreign keys off) with real Concierge 0.8 (scoped guests by a stand-in), spatie teams, Pennant and Inertia; router dispatch without the global middleware stack, no browser, no real queue worker.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
