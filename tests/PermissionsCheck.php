<?php

// Permission catalog, sync, default role grants, policies, `can` flags and the capability gate, on in-memory SQLite with synthetic fixtures; no Concierge tables, HTTP or MCP transport.
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Laravel\Pennant\FeatureManager;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace as WorkspaceContract;
use Tey\Concierge\Contracts\WorkspaceContext;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Models\Membership;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Access\PermissionSync;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function permissionCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class PermissionUser extends Model implements Member, Illuminate\Contracts\Auth\Authenticatable
{
    use HasRoles, Illuminate\Auth\Authenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function conciergeEmail(): string
    {
        return 'u@example.test';
    }

    public function conciergeHasVerifiedEmail(): bool
    {
        return true;
    }

    public function conciergeDisplayName(): string
    {
        return 'User';
    }
}

class PermissionWorkspace extends Workspace
{
    protected $table = 'workspaces';
}

/** Membership is a table of synthetic rows; the team context is Spatie's own, as Concierge's is. */
class PermissionConcierge extends Concierge
{
    /** @var array<string, bool> "workspace:user" => active */
    public array $members = [];

    public function membership(Member $user, WorkspaceContract $workspace): ?Membership
    {
        $active = $this->members[$workspace->getKey().':'.$user->getKey()] ?? null;

        return $active === null ? null : (new Membership)->forceFill(['status' => $active ? MembershipStatus::Active : MembershipStatus::Suspended]);
    }
}

class PermissionContext implements WorkspaceContext
{
    public function current(): ?WorkspaceContract
    {
        return null;
    }

    public function run(WorkspaceContract $workspace, Closure $callback, iterable $refresh = []): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($workspace->getKey());
        foreach ($refresh as $member) {
            $member->unsetRelation('roles')->unsetRelation('permissions');
        }
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            foreach ($refresh as $member) {
                $member->unsetRelation('roles')->unsetRelation('permissions');
            }
        }
    }

    /** @var list<mixed> the team each enter() replaced (Concierge 0.8.4's enter/leave; nothing here calls them, but the contract requires them) */
    private array $entered = [];

    public function enter(WorkspaceContract $workspace, iterable $refresh = []): void
    {
        $registrar = app(PermissionRegistrar::class);
        $this->entered[] = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($workspace->getKey());
        foreach ($refresh as $member) {
            $member->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function leave(): void
    {
        if ($this->entered === []) {
            throw new LogicException('Nothing entered to leave.');
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId(array_pop($this->entered));
    }
}

try {
    $app = new Application(dirname(__DIR__));
    $app->instance('config', new Repository([
        'app' => ['url' => 'https://kit.test', 'locale' => 'en'],
        'auth' => ['defaults' => ['guard' => 'web'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => PermissionUser::class]]],
        'kit' => ['models' => ['user' => PermissionUser::class], 'capabilities' => ['workspace.settings' => true]],
        'concierge' => ['guard' => 'web', 'roles' => ['owner' => [], 'admin' => [], 'member' => []]],
        'permission' => ['teams' => true, 'column_names' => ['team_foreign_key' => 'workspace_id', 'role_pivot_key' => null, 'permission_pivot_key' => null, 'model_morph_key' => 'model_id'], 'table_names' => ['roles' => 'roles', 'permissions' => 'permissions', 'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions'], 'models' => ['permission' => Spatie\Permission\Models\Permission::class, 'role' => Spatie\Permission\Models\Role::class], 'cache' => ['expiration_time' => 3600, 'key' => 'permission.cache', 'store' => 'array'], 'register_permission_check_method' => false, 'register_octane_reset_listener' => false, 'events_enabled' => false, 'use_passport_client_credentials' => false, 'display_permission_in_exception' => false, 'display_role_in_exception' => false, 'enable_wildcard_permission' => false],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'pennant' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
    ]));
    $app->instance('events', new Dispatcher($app));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    Model::unsetEventDispatcher();
    $app->singleton('cache', fn ($app) => new CacheManager($app));
    $app->singleton('cache.store', fn ($app) => $app['cache']->driver());
    $app->register(Illuminate\Database\DatabaseServiceProvider::class);
    Model::setConnectionResolver($app['db']);
    $app->singleton(PermissionRegistrar::class, fn ($app) => new PermissionRegistrar($app->make("cache")));
    $app->singleton(FeatureManager::class, fn ($app) => new FeatureManager($app));
    $app->singleton(Concierge::class, PermissionConcierge::class);
    $app->bind(WorkspaceContext::class, PermissionContext::class);
    $app->singleton(GateContract::class, fn ($app) => new Illuminate\Auth\Access\Gate($app, fn () => null));
    Illuminate\Support\Facades\Gate::swap($app->make(GateContract::class));

    Schema::create('users', function (Blueprint $table) {
        $table->id();
    });
    Schema::create('workspaces', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->nullable();
    });
    (require dirname(__DIR__).'/resources/features/access/migrations/0001_01_02_000301_create_kit_permission_defaults_table.php.stub')->up();
    (require dirname(__DIR__).'/resources/features/access/migrations/0001_01_02_000302_create_kit_capability_overrides_table.php.stub')->up();
    (require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

    // The registry as the adopted features fill it, plus an app's own declaration the same way.
    $registry = FeaturesServiceProvider::registry(['core', 'workspaces', 'access', 'settings']);
    $registry->permission('cases.close', ['owner'], 'workspace.cases');
    $app->instance(Registry::class, $registry);
    $catalog = $registry->permissionCatalog();
    permissionCheck(array_keys($catalog) === ['workspace.members.manage', 'workspace.settings.update', 'cases.close'], 'features and apps declare permissions with default roles on the one registry');
    permissionCheck($catalog['workspace.settings.update'] === ['roles' => ['owner', 'admin'], 'capability' => 'workspace.settings'] && $registry->permissionFor('settings.write') === 'workspace.settings.update', 'a permission carries its capability and the legacy ability it replaces');
    try {
        $registry->permission('workspace.settings.update', ['owner']);
        permissionCheck(false, 'a conflicting redeclaration is refused');
    } catch (RuntimeException $error) {
        permissionCheck(str_contains($error->getMessage(), 'Conflicting declaration'), 'a conflicting redeclaration is refused');
    }
    $registry->permission('workspace.members.manage', ['owner', 'admin']);

    $roleClass = app(PermissionRegistrar::class)->getRoleClass();
    foreach (['owner', 'admin', 'member'] as $name) {
        $roleClass::create(['name' => $name, 'guard_name' => 'web', 'workspace_id' => null]);
    }

    $sync = new PermissionSync;
    $first = $sync->run();
    permissionCheck(count($first['created']) === 3 && count($first['granted']) === 5 && $first['missing_roles'] === [], 'sync creates the declared permissions and grants the defaults');
    $second = $sync->run();
    permissionCheck($second === ['created' => [], 'granted' => [], 'missing_roles' => [], 'unconfigured_roles' => [], 'skipped_grants' => [], 'custom' => [], 'undeclared' => []], 'a second sync changes and reports nothing');
    $grants = fn (string $role): array => $roleClass::query()->where('name', $role)->first()->permissions->pluck('name')->sort()->values()->all();
    permissionCheck($grants('owner') === ['cases.close', 'workspace.members.manage', 'workspace.settings.update'], 'owner holds every default grant');
    permissionCheck($grants('admin') === ['workspace.members.manage', 'workspace.settings.update'], 'admin holds its default grants');
    permissionCheck($grants('member') === [], 'member holds none by default');

    $roleClass::query()->where('name', 'member')->first()->givePermissionTo('workspace.settings.update');
    app(PermissionRegistrar::class)->getPermissionClass()::create(['name' => 'legacy.thing', 'guard_name' => 'web']);
    $drift = $sync->run();
    permissionCheck($drift['custom'] === ['member workspace.settings.update'] && $drift['undeclared'] === ['legacy.thing'] && $drift['created'] === [] && $drift['granted'] === [], 'sync reports custom grants and undeclared permissions');
    permissionCheck($grants('member') === ['workspace.settings.update'], 'sync keeps custom grants');
    $roleClass::query()->where('name', 'member')->first()->revokePermissionTo('workspace.settings.update');
    app(PermissionRegistrar::class)->getPermissionClass()::query()->where('name', 'legacy.thing')->delete();

    // A default someone removed is not granted again; one that is new since the last sync is.
    $roleClass::query()->where('name', 'admin')->first()->revokePermissionTo('workspace.members.manage');
    $removed = $sync->run();
    permissionCheck($removed['granted'] === [] && $grants('admin') === ['workspace.settings.update'], 'sync does not grant a default that was deliberately removed');
    $registry->permission('workspace.reports.view', ['admin']);
    $added = $sync->run();
    permissionCheck($added['granted'] === ['admin workspace.reports.view'] && $grants('admin') === ['workspace.reports.view', 'workspace.settings.update'], 'sync grants a default that is new since the last sync, and still not the removed one');
    $roleClass::query()->where('name', 'admin')->first()->givePermissionTo('workspace.members.manage');
    $roleClass::query()->where('name', 'admin')->first()->revokePermissionTo('workspace.reports.view');

    $roleClass::query()->where('name', 'admin')->delete();
    $missing = (new PermissionSync)->run();
    permissionCheck($missing['missing_roles'] === ['admin'], 'sync reports a default role that does not exist');
    $roleClass::create(['name' => 'admin', 'guard_name' => 'web', 'workspace_id' => null]);
    (new PermissionSync)->run();

    // A default role the app does not configure is skipped and reported, not a failure.
    $registry->permission('workspace.audit.view', ['owner', 'auditor']);
    $unconfigured = $sync->run();
    permissionCheck($unconfigured['unconfigured_roles'] === ['auditor'] && $unconfigured['missing_roles'] === [] && $unconfigured['granted'] === ['owner workspace.audit.view'], 'sync skips and reports a default role the app does not configure');
    permissionCheck($unconfigured['skipped_grants'] === ['auditor' => ['workspace.audit.view']], 'and names the grants that role would have had; the owner still gets its own');
    $command = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $command->setLaravel(app());
    $registry->permission('workspace.audit.export', ['owner', 'auditor']);
    $output = new Symfony\Component\Console\Output\BufferedOutput;
    $command->run(new Symfony\Component\Console\Input\ArrayInput([]), $output);
    $printed = $output->fetch();
    permissionCheck(str_contains($printed, "No 'auditor' role in this app (concierge.roles does not name it), so nothing to grant it: workspace.audit.view, workspace.audit.export. Nothing to configure unless you want that role.") && ! str_contains($printed, 'Default role not configured'), 'kit:permissions:sync says the role is absent from this app, not misconfigured, and lists what it would have had');

    // Users: roles are per workspace.
    $one = PermissionWorkspace::create(['name' => 'One']);
    $two = PermissionWorkspace::create(['name' => 'Two']);
    $users = [];
    foreach (['owner', 'admin', 'member', 'outsider', 'lapsed'] as $name) {
        $users[$name] = PermissionUser::create([]);
    }
    $concierge = app(Concierge::class);
    $registrar = app(PermissionRegistrar::class);
    foreach (['owner', 'admin', 'member'] as $role) {
        $registrar->setPermissionsTeamId($one->id);
        $users[$role]->assignRole($role);
        $concierge->members[$one->id.':'.$users[$role]->id] = true;
    }
    $registrar->setPermissionsTeamId($two->id);
    $users['admin']->assignRole('member');
    $concierge->members[$two->id.':'.$users['admin']->id] = true;
    $concierge->members[$one->id.':'.$users['lapsed']->id] = false;
    $registrar->setPermissionsTeamId($one->id);
    $users['lapsed']->assignRole('owner');
    $registrar->setPermissionsTeamId(null);

    $gate = fn (string $user) => Illuminate\Support\Facades\Gate::forUser($users[$user]);
    app(AccessProvider::class, ['app' => $app])->boot();
    $update = fn (string $user, $workspace) => $gate($user)->allows('update', $workspace);
    $manage = fn (string $user, $workspace) => $gate($user)->allows('manageMembers', $workspace);
    permissionCheck($update('owner', $one) && $update('admin', $one) && $manage('owner', $one) && $manage('admin', $one), 'the policy allows owner and admin to update settings and manage members');
    permissionCheck(! $update('member', $one) && ! $manage('member', $one), 'the policy denies a member');
    permissionCheck(! $update('outsider', $one) && ! $gate('outsider')->allows('view', $one) && $gate('member')->allows('view', $one), 'the policy denies a non-member and lets a member view');
    permissionCheck(! $update('lapsed', $one) && ! $gate('lapsed')->allows('view', $one), 'the policy denies a lapsed membership even with a role');
    permissionCheck(! $update('admin', $two) && $update('admin', $one), 'roles are per workspace: admin of one is a member of the other');
    permissionCheck($registrar->getPermissionsTeamId() === null, 'the team context is restored after a check');

    $permissions = app(Permissions::class);

    // Pennant stays separate: the permission without the capability is still denied.
    config(['kit.capabilities' => ['workspace.settings' => false]]);
    Feature::purge();
    permissionCheck(! $update('owner', $one) && $permissions->granted($users['owner'], 'workspace.settings.update', $one), 'a capability that is off denies a permission that is granted');
    permissionCheck($manage('owner', $one), 'a permission without a capability is unaffected: the gate follows the grant');
    Feature::purge();
    config(['kit.capabilities' => ['workspace.settings' => true]]);
    permissionCheck($update('owner', $one), 'turning the capability on allows it again');

    permissionCheck(! $permissions->granted($users['owner'], 'undeclared.permission', $one), 'a permission no feature declares is never granted');
    permissionCheck(class_exists(Access::class) && (new ReflectionMethod(SyncPermissionsCommand::class, 'handle'))->getNumberOfParameters() === 1, 'kit:permissions:sync is a command over the sync');
    echo "Permission checks on in-memory SQLite with synthetic membership; no Concierge tables, HTTP, Inertia props rendering or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
