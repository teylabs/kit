<?php

// The workspace list (/me/workspaces): only the person's own workspaces, each with their role, the member count and a Current marker; New workspace only while
// provisioning allows it; an empty state with no workspace; open invitations to their verified email; and nothing from a workspace they are not in, in a few queries.
// The shell's switcher on a workspace page: each entry decided in its own workspace, and the same queries with one workspace or five.
// SQLite memory, real Concierge, Fortify and Inertia, synthetic users and workspaces (Northwind Farms, Acme Travel, Globex Labs); no network.
use Carbon\CarbonInterface;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
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
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Access\Entitlements;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function httpCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class HttpUser extends Authenticatable implements Member, MustVerifyEmail
{
    use HasApiTokens, HasWorkspaces, Illuminate\Auth\MustVerifyEmail, Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-chooser-'.bin2hex(random_bytes(4));
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
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true]],
        'concierge' => ['invitations' => ['deliver' => true]], 'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]], 'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FoundationServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, NotificationServiceProvider::class, MailServiceProvider::class, PasswordResetServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    // The kit's lang, as KitServiceProvider loads it: the plain breach message comes from kit::validation.
    $app['translator']->addNamespace('kit', dirname(__DIR__).'/lang');
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
    $app['router']->aliasMiddleware('guest', RedirectIfAuthenticated::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
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
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $provision = function (string $name, string $slug) use ($registry): Workspace {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);
        $workspace->execute(function () use ($registry, $workspace): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    };
    $join = function (HttpUser $user, Workspace $workspace, string $role, MembershipStatus $status = MembershipStatus::Active) use ($sync): void {
        $membership = ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => $status, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $user->assignRole($role), [$user]);
        if ($role === 'owner') {
            ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $membership->id]);
        }
        $sync->run(new ArrayInput([]), new NullOutput);
    };
    $invite = function (Workspace $workspace, string $email, ?HttpUser $inviter, string $status = 'pending', ?CarbonInterface $expires = null): void {
        ConciergeModels::invitation()::query()->forceCreate(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'email' => $email, 'grants' => '[]', 'grants_fingerprint' => str_repeat('0', 64), 'inviter_id' => $inviter?->id, 'token_hash' => bin2hex(random_bytes(32)), 'status' => $status, 'expires_at' => $expires ?? now()->addDays(7)]);
    };
    $northwind = $provision('Northwind Farms', 'northwind');
    $acme = $provision('Acme Travel', 'acme');
    $globex = $provision('Globex Labs', 'globex');
    $ada = HttpUser::create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now()]);
    $ben = HttpUser::create(['name' => 'Ben Sample', 'email' => 'ben@example.test', 'email_verified_at' => now()]);
    $cy = HttpUser::create(['name' => 'Cy Outsider', 'email' => 'cy@example.test', 'email_verified_at' => now()]);
    $dee = HttpUser::create(['name' => 'Dee Former', 'email' => 'dee@example.test', 'email_verified_at' => now()]);
    $nell = HttpUser::create(['name' => 'Nell Newcomer', 'email' => 'nell@example.test', 'email_verified_at' => now()]);
    $una = HttpUser::create(['name' => 'Una Unverified', 'email' => 'una@example.test']);
    // Ada owns Northwind (with Ben, an admin, and Dee, suspended: not counted) and is an admin in Acme (Ben owns it). Globex is Cy's alone: Ada is not in it.
    $join($ada, $northwind, 'owner');
    $join($ben, $northwind, 'admin');
    $join($dee, $northwind, 'admin', MembershipStatus::Suspended);
    $join($ben, $acme, 'owner');
    $join($ada, $acme, 'admin');
    $join($cy, $globex, 'owner');
    $invite($globex, 'ada@example.test', $cy);
    $invite($globex, 'ada@example.test', $cy, 'revoked');
    $invite($globex, 'ada@example.test', $cy, 'pending', now()->subDay());
    $invite($globex, 'nell@example.test', $cy);
    $invite($globex, 'una@example.test', $cy);

    // One Inertia GET through the router (or, `$json`, a plain JSON one), with a session the array handler keeps (cookie `s`): [status, the decoded page or body].
    $handler = $app['session']->driver()->getHandler();
    $send = function (?object $user, string $path, array $session = [], bool $json = false) use ($app, $handler): array {
        Auth::guard('web')->logout();
        if ($user !== null) {
            Auth::guard('web')->setUser($user);
        }
        $app['session']->driver()->flush();
        $id = bin2hex(random_bytes(20));
        $handler->write($id, serialize(['_token' => 'token', ...$session]));
        $request = Request::create('https://kit.test'.$path, 'GET', [], ['s' => $id], server: $json ? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => Auth::guard('web')->user());
        $app->instance('request', $request);
        RequestMemo::instance()->flush();
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    };
    $row = fn (array $page, string $name): ?array => collect($page['props']['workspaces'] ?? [])->firstWhere('name', $name);
    // What the switcher loads when it opens (GET /me/switcher): [status, the page each workspace opens on, by name (null: unavailable)].
    $landings = function (object $user) use ($send): array {
        [$status, $body] = $send($user, '/me/switcher', [], true);
        $names = Workspace::query()->whereIn('id', array_column($body['workspaces'] ?? [], 'id'))->pluck('name', 'id');

        return [$status, collect($body['workspaces'] ?? [])->mapWithKeys(fn (array $landing): array => [$names[$landing['id']] => $landing['url']])->all()];
    };

    // Ada: her two workspaces, never Globex; her role in each, the active member count (Dee, suspended, is not counted), and Leave only where she is not the owner.
    [$status, $page] = $send($ada, '/me/workspaces');
    httpCheck($status === 200 && ($page['component'] ?? null) === 'Workspace/Index/Page', 'Ada opens /me/workspaces: the list ('.$status.')');
    httpCheck(array_column($page['props']['workspaces'], 'name') === ['Acme Travel', 'Northwind Farms'], 'only her own workspaces, by name: never Globex Labs ('.json_encode(array_column($page['props']['workspaces'], 'name')).')');
    $nw = $row($page, 'Northwind Farms');
    $ac = $row($page, 'Acme Travel');
    httpCheck($nw['role'] === 'Owner' && $nw['members'] === 2 && $nw['leave'] === null, 'Northwind: Owner, 2 active members (the suspended one not counted), no Leave ('.json_encode([$nw['role'], $nw['members']]).')');
    httpCheck($ac['role'] === 'Admin' && $ac['members'] === 2 && ($ac['leave']['url'] ?? null) === '/me/workspaces/acme/membership', 'Acme: Admin (her role there, not Northwind\'s), 2 members, Leave ('.json_encode([$ac['role'], $ac['members']]).')');
    httpCheck($nw['initials'] === 'NF', 'each workspace carries its initials mark');
    $listed = collect($page['props']['shell']['switcher']['choices'] ?? [])->pluck('slug', 'name')->all();
    [$status, $links] = $landings($ada);
    httpCheck(collect(array_keys($listed))->sort()->values()->all() === ['Acme Travel', 'Northwind Farms'] && $status === 200 && collect(array_keys($links))->sort()->values()->all() === ['Acme Travel', 'Northwind Farms'], 'the shell\'s switcher, and the links the page loads as the switcher does, offer only her two workspaces ('.json_encode([array_keys($listed), $status, array_keys($links)]).')');
    httpCheck(str_contains((string) $links['Northwind Farms'], 'northwind'), 'and the link into each');
    httpCheck(! str_contains(json_encode($page['props']['workspaces']), 'Globex') && ! str_contains(json_encode($page['props']['workspaces']), 'Cy Outsider'), 'no other workspace\'s data in the list');
    httpCheck($nw['current'] === false && $ac['current'] === false, 'no workspace in use: none is Current');
    [, $page] = $send($ada, '/me/workspaces', ['kit' => ['workspace' => $acme->id]]);
    httpCheck($row($page, 'Acme Travel')['current'] === true && $row($page, 'Northwind Farms')['current'] === false, 'the workspace last used (the session\'s) is Current, only it');
    [, $page] = $send($ada, '/me/workspaces', ['kit' => ['workspace' => $globex->id]]);
    httpCheck(collect($page['props']['workspaces'])->where('current', true)->isEmpty() && ! str_contains(json_encode($page['props']['workspaces']), 'Globex'), 'a session pointing at a workspace she is not in marks none Current and lists nothing of it');

    // Invitations: only open ones to her own verified email, never another person's, a revoked or an expired one.
    httpCheck(count($page['props']['invitations']) === 1 && $page['props']['invitations'][0]['workspace'] === 'Globex Labs' && $page['props']['invitations'][0]['inviter'] === 'Cy Outsider', 'her one open invitation is listed, with its inviter ('.json_encode($page['props']['invitations']).')');

    // New workspace: absent by default (invite_only), present with self_serve, absent again for anything unknown.
    httpCheck($page['props']['create'] === null, 'invite_only (the default): no New workspace action');
    // self_serve with no creation route: the kit ships none, so nothing is offered (never a link to a 404), here or on the no-workspace page.
    $config->set('kit.workspaces.provisioning', 'self_serve');
    [, $page] = $send($ada, '/me/workspaces');
    httpCheck($page['props']['create'] === null, 'self_serve without a /me/workspaces/create route: no New workspace action');
    [, $page] = $send($nell, '/no-workspace');
    httpCheck(($page['component'] ?? null) === 'Provision/NoWorkspace/Page' && $page['props']['create'] === null, 'self_serve without the route: the no-workspace page offers no create action either');
    // The host's own creation page: now it is offered.
    $app['router']->get('me/workspaces/create', fn () => 'create');
    $app['router']->getRoutes()->refreshNameLookups();
    [, $page] = $send($ada, '/me/workspaces');
    httpCheck(($page['props']['create']['label'] ?? null) === 'New workspace' && ($page['props']['create']['url'] ?? null) === '/me/workspaces/create', 'self_serve: New workspace is offered');
    $config->set('kit.workspaces.provisioning', 'something_else');
    [, $page] = $send($ada, '/me/workspaces');
    httpCheck($page['props']['create'] === null, 'an unknown provisioning value offers nothing (fail closed)');
    $config->set('kit.workspaces.provisioning', 'invite_only');

    // No workspace: the page renders its empty state (no redirect), with her invitation; with self_serve, the action too.
    [$status, $page] = $send($nell, '/me/workspaces');
    httpCheck($status === 200 && $page['props']['workspaces'] === [] && count($page['props']['invitations']) === 1 && $page['props']['create'] === null, 'no workspace: the list renders empty, with the open invitation ('.$status.')');
    $config->set('kit.workspaces.provisioning', 'self_serve');
    [, $page] = $send($nell, '/me/workspaces');
    httpCheck($page['props']['create'] !== null, 'no workspace, self_serve: New workspace is offered');
    $config->set('kit.workspaces.provisioning', 'invite_only');

    // A suspended membership is not a workspace of yours; an unverified email sees no invitations; a guest gets nothing.
    [, $page] = $send($dee, '/me/workspaces');
    httpCheck(($page['props']['workspaces'] ?? null) === [], 'suspended in Northwind: it is not listed');
    [$status, $page] = $send($una, '/me/workspaces');
    httpCheck(($page['props']['invitations'] ?? []) === [], 'an unverified email lists no invitations ('.$status.')');
    httpCheck($send(null, '/me/workspaces')[0] !== 200, 'a guest gets no list');

    // No N+1: the list's own reads (memberships, member counts, ownerships, roles) are one query each, for six workspaces.
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    foreach (range(1, 4) as $i) {
        $join($ada, $provision('Many Works '.$i, 'many-'.$i), 'admin');
    }
    $queries = [];
    [, $page] = $send($ada, '/me/workspaces');
    $patterns = ['memberships' => '/from "memberships" where "user_id" = \? and "status" = \? and "workspace_id" in/', 'counts' => '/count\(\*\) as aggregate from "memberships"/', 'owners' => '/from "ownerships" where "workspace_id" in/', 'roles' => '/from "model_has_roles" inner join "roles"/'];
    $seen = array_map(fn (string $pattern): int => count(preg_grep($pattern, $queries)), $patterns);
    httpCheck(count($page['props']['workspaces']) === 6 && $seen === ['memberships' => 1, 'counts' => 1, 'owners' => 1, 'roles' => 1], 'six workspaces: role, count and ownership are one query each, not one per workspace ('.json_encode($seen).')');

    // The shell's switcher costs the same queries with one workspace or five: it sends only the list (id, name and slug), and decides the pages of the
    // workspace in the bar alone. The page each workspace opens on is loaded when the switcher opens (GET /me/switcher), decided inside that workspace,
    // link by link, fail closed: a role, a direct grant, a plan row and an override in one workspace reach no other, and a workspace with nothing the
    // viewer may open is unavailable.
    $registry->navigation('members.manage', 'People', 'people', order: -20);
    $registry->entitlement('workspace.dashboard');
    $sam = HttpUser::create(['name' => 'Sam Switcher', 'email' => 'sam@example.test', 'email_verified_at' => now()]);
    $plan = fn (Workspace $workspace) => app(Entitlements::class)->set('workspace.dashboard', $workspace, true);
    // Home: a member with a direct members.manage grant there (People), on the plan.
    $home = $provision('Switch Home', 'switch-home');
    $join($sam, $home, 'member');
    app(Concierge::class)->context()->run($home, fn () => $sam->givePermissionTo('workspace.members.manage'), [$sam]);
    $plan($home);
    $shellPage = function () use ($send, $sam, &$queries): array {
        $send($sam, '/@switch-home');
        $queries = [];
        [$status, $page] = $send($sam, '/@switch-home');

        return [$status, count($queries), $page['props']['shell'] ?? []];
    };
    [$status, $one, $shell] = $shellPage();
    httpCheck($status === 200 && array_column($shell['navigation'] ?? [], 'label') === ['People', 'Dashboard', 'Settings'], 'one workspace: her direct grant shows People, then Dashboard and Settings ('.json_encode(array_column($shell['navigation'] ?? [], 'label')).')');
    httpCheck(($shell['switcher']['choices'] ?? null) === [['id' => (int) $home->id, 'name' => 'Switch Home', 'slug' => 'switch-home', 'current' => true]] && ($shell['switcher']['landings'] ?? null) === '/me/switcher' && ($shell['workspace']['url'] ?? null) === '/people', 'one workspace: the switcher lists it by id, name and slug, with no link; the bar names it with its first page ('.json_encode([$shell['switcher'] ?? null, $shell['workspace']['url'] ?? null]).')');
    // An admin, on the plan: People first. A member, on the plan: no People (Home's grant is Home's alone). A member off the plan: no Dashboard, so Settings.
    // An admin on the plan, with the workspace's settings capability off and the dashboard off for her alone: nothing to open, so unavailable.
    $join($sam, $admin = $provision('Switch Admin', 'switch-admin'), 'admin');
    $plan($admin);
    $join($sam, $member = $provision('Switch Member', 'switch-member'), 'member');
    $plan($member);
    $join($sam, $provision('Switch Unplanned', 'switch-unplanned'), 'member');
    $join($sam, $off = $provision('Switch Off', 'switch-off'), 'admin');
    $plan($off);
    app(Capabilities::class)->setForWorkspace('workspace.settings', $off, false);
    app(Capabilities::class)->setForPerson('workspace.dashboard', $off, (int) $sam->id, false);
    [$status, $five, $shell] = $shellPage();
    $listed = $shell['switcher']['choices'] ?? [];
    httpCheck($status === 200 && array_column($listed, 'slug', 'name') === ['Switch Home' => 'switch-home', 'Switch Admin' => 'switch-admin', 'Switch Member' => 'switch-member', 'Switch Unplanned' => 'switch-unplanned', 'Switch Off' => 'switch-off'] && collect($listed)->every(fn (array $choice): bool => array_keys($choice) === ['id', 'name', 'slug', 'current']), 'five workspaces: the shell lists each by id, name and slug only, and no workspace\'s link ('.json_encode($listed).')');
    httpCheck(array_column($shell['navigation'], 'url') === ['/@switch-home/people', '/@switch-home', '/@switch-home/settings'] && ($shell['workspace']['url'] ?? null) === '/@switch-home/people', 'five workspaces: the page\'s own navigation is unchanged ('.json_encode(array_column($shell['navigation'], 'url')).')');
    httpCheck($one === $five, 'the shell\'s page costs the same queries with one workspace and with five ('.$one.' and '.$five.')');
    [$status, $opened] = $landings($sam);
    httpCheck($status === 200 && $opened === ['Switch Home' => '/@switch-home/people', 'Switch Admin' => '/@switch-admin/people', 'Switch Member' => '/@switch-member', 'Switch Unplanned' => '/@switch-unplanned/settings', 'Switch Off' => null], 'the switcher, opened: each workspace lands on the first page she may open there, decided by its own role, grant, plan and overrides, and none in Switch Off ('.json_encode($opened).')');
    [$status] = $send(null, '/me/switcher', [], true);
    httpCheck($status === 401, 'a guest loads no switcher links ('.$status.')');

    // The switcher's links read every kind of permission: (a) a global role (a role row with no workspace, held in one), (b) a direct permission, (c) a role
    // of one workspace's own, and (d) a permission granted to a role (the kit's sync grants a default to member). Each kind leads its own link, ahead of
    // People, so a kind missed moves that workspace's entry. A pivot row naming another workspace's role grants nothing. The links must equal the shell
    // built with no request memo, where every check is spatie's own, read in its workspace.
    foreach (['audit' => -60, 'billing' => -50, 'reports' => -40, 'files' => -30] as $name => $order) {
        $registry->abilities(['switch.'.$name => 'workspace.search'])->permission('workspace.switch.'.$name, $name === 'files' ? ['member'] : [], 'workspace.search', ['switch.'.$name]);
        $registry->navigation('switch.'.$name, ucfirst($name), $name, order: $order);
    }
    $sync->run(new ArrayInput([]), new NullOutput);
    $roleClass = app(PermissionRegistrar::class)->getRoleClass();
    $auditor = $roleClass::create(['name' => 'auditor', 'guard_name' => 'web'])->givePermissionTo('workspace.switch.audit');
    $gil = HttpUser::create(['name' => 'Gil Granted', 'email' => 'gil@example.test', 'email_verified_at' => now()]);
    $in = fn (Workspace $workspace, Closure $do) => app(Concierge::class)->context()->run($workspace, $do, [$gil]);
    $reviewer = fn (Workspace $workspace) => $in($workspace, fn () => $roleClass::create(['name' => 'reviewer', 'guard_name' => 'web'])->givePermissionTo('workspace.switch.reports'));
    // Mixed, on the plan: a member (Files) with the global auditor role, a direct Billing grant and Mixed's own reviewer role: every kind at once.
    $join($gil, $mixed = $provision('Grant Mixed', 'grant-mixed'), 'member');
    $plan($mixed);
    $mixedReviewer = $reviewer($mixed);
    $in($mixed, fn () => $gil->assignRole($auditor, $mixedReviewer)->givePermissionTo('workspace.switch.billing'));
    // One kind each, on an admin (People) or a member (Files) below it.
    $join($gil, $global = $provision('Grant Global', 'grant-global'), 'admin');
    $in($global, fn () => $gil->assignRole($auditor));
    $join($gil, $direct = $provision('Grant Direct', 'grant-direct'), 'admin');
    $in($direct, fn () => $gil->givePermissionTo('workspace.switch.billing'));
    $join($gil, $scoped = $provision('Grant Scoped', 'grant-scoped'), 'admin');
    $in($scoped, fn () => $gil->assignRole($reviewer($scoped)));
    $join($gil, $granted = $provision('Grant Role', 'grant-role'), 'member');
    DB::table('model_has_roles')->insert(['role_id' => $mixedReviewer->id, 'model_type' => $gil->getMorphClass(), 'model_id' => $gil->id, 'workspace_id' => $granted->id]);
    [$status, $page] = $send($gil, '/@grant-mixed');
    [$loaded, $opened] = $landings($gil);
    $app->forgetScopedInstances();
    $request = Request::create('https://kit.test/me/switcher');
    $request->setUserResolver(fn () => $gil);
    $request->setLaravelSession($app['session']->driver());
    $app->instance('request', $request);
    $names = Workspace::query()->pluck('name', 'id');
    $unmemoized = collect(app(PageShell::class)->landings($request))->mapWithKeys(fn (?string $url, int $id): array => [$names[$id] => $url])->all();
    httpCheck($status === 200 && $loaded === 200 && $opened === ['Grant Mixed' => '/@grant-mixed/audit', 'Grant Global' => '/@grant-global/audit', 'Grant Direct' => '/@grant-direct/billing', 'Grant Scoped' => '/@grant-scoped/reports', 'Grant Role' => '/@grant-role/files'], 'a global role, a direct permission, a workspace\'s own role and a role grant each open their workspace\'s first page; another workspace\'s role does not ('.json_encode($opened).')');
    httpCheck($opened === $unmemoized, 'the switcher\'s links match the shell read without the request memo, workspace by workspace ('.json_encode($unmemoized).')');
    httpCheck(array_column($page['props']['shell']['navigation'], 'label') === ['Audit', 'Billing', 'Reports', 'Files', 'Dashboard', 'Settings'] && ($page['props']['shell']['workspace']['url'] ?? null) === '/@grant-mixed/audit', 'Grant Mixed\'s own navigation holds every kind at once ('.json_encode(array_column($page['props']['shell']['navigation'], 'label')).')');

    // A role or grant written mid-request empties the request's permission sets, under whatever names the tables carry: spatie's configured names (also
    // schema-qualified), with the connection's table prefix, as each grammar writes them. (a) Every write form each grammar compiles for each of spatie's
    // tables, as the listener receives it (the statement and its connection's prefix), is a write; a read of them, or a write of a table that only shares a
    // part of the name, is not.
    $tables = (array) $config->get('permission.table_names');
    $statements = function (string $driver, string $prefix, Closure $run) use ($config): ?array {
        $config->set('database.connections.grammar', ['driver' => $driver, 'database' => $driver === 'sqlite' ? ':memory:' : 'kit', 'prefix' => $prefix]);
        DB::purge('grammar');
        $seen = [];
        $db = DB::connection('grammar');
        $db->listen(function ($query) use (&$seen): void {
            $seen[] = [$query->sql, $query->connection->getTablePrefix()];
        });
        try {
            $db->pretend(fn () => $run($db));
        } catch (RuntimeException $unsupported) {
            // A form the grammar does not compile (SQL Server's insert or ignore) is never sent.
            return str_contains($unsupported->getMessage(), 'does not support') ? null : throw $unsupported;
        }

        return $seen;
    };
    // No bindings (raw values, raw wheres): pretending substitutes them into its log through the driver's PDO, which only SQLite has here.
    $writes = [
        'insert' => fn ($db, string $table) => $db->table($table)->insert(['name' => $db->raw("'x'")]),
        'insert or ignore' => fn ($db, string $table) => $db->table($table)->insertOrIgnore(['name' => $db->raw("'x'")]),
        'upsert' => fn ($db, string $table) => $db->table($table)->upsert(['name' => $db->raw("'x'")], ['name']),
        'update' => fn ($db, string $table) => $db->table($table)->whereRaw('1 = 1')->update(['name' => $db->raw("'y'")]),
        'update with a join' => fn ($db, string $table) => $db->table($table)->join('users', 'users.id', '=', $table.'.id')->whereRaw('1 = 1')->update(['name' => $db->raw("'y'")]),
        'delete' => fn ($db, string $table) => $db->table($table)->whereRaw('1 = 1')->delete(),
        'delete with a join and an alias' => fn ($db, string $table) => $db->table($table.' as t')->join('users', 'users.id', '=', 't.id')->whereRaw('1 = 1')->delete(),
        'truncate' => fn ($db, string $table) => $db->table($table)->truncate(),
    ];
    $reads = [
        'select' => fn ($db, string $table) => $db->table($table)->whereRaw('1 = 1')->get(),
        'select for update' => fn ($db, string $table) => $db->table($table)->whereRaw('1 = 1')->lockForUpdate()->get(),
        'insert into a table named like one' => fn ($db, string $table) => $db->table(str_replace('roles', 'roles_archive', str_replace('permissions', 'permissions_log', $table)))->insert(['name' => $db->raw("'x'")]),
        'update of another table' => fn ($db, string $table) => $db->table('users')->whereRaw('1 = 1')->update(['name' => $db->raw("'y'")]),
    ];
    $missed = [];
    $wrong = [];
    $forms = 0;
    foreach (['sqlite', 'mysql', 'pgsql', 'sqlsrv'] as $driver) {
        foreach (['', 'kit_'] as $prefix) {
            foreach (['unqualified' => '', 'schema-qualified' => 'auth.'] as $qualified => $schema) {
                $config->set('permission.table_names', array_map(fn (string $table): string => $schema.$table, $tables));
                foreach ($tables as $table) {
                    foreach ($writes as $form => $write) {
                        $seen = $statements($driver, $prefix, fn ($db) => $write($db, $schema.$table));
                        $forms += $seen === null ? 0 : 1;
                        if ($seen === [] || $seen !== null && ! collect($seen)->contains(fn (array $statement): bool => Tey\Kit\Features\Access\Permissions::writes(...$statement))) {
                            $missed[] = "{$driver} '{$prefix}' {$qualified} {$table} {$form}: ".json_encode(array_column($seen, 0));
                        }
                    }
                    foreach ($reads as $form => $read) {
                        foreach ($statements($driver, $prefix, fn ($db) => $read($db, $schema.$table)) ?? [] as $statement) {
                            if (Tey\Kit\Features\Access\Permissions::writes(...$statement)) {
                                $wrong[] = "{$driver} '{$prefix}' {$qualified} {$table} {$form}: {$statement[0]}";
                            }
                        }
                    }
                }
            }
        }
    }
    $config->set('permission.table_names', $tables);
    httpCheck($missed === [], $forms.' writes of spatie\'s five tables (insert, insert or ignore, upsert, update, update with a join, delete, delete with a join and an alias, truncate) on SQLite, MySQL, PostgreSQL and SQL Server grammars, with and without a connection prefix, unqualified and schema-qualified, each empty the permission sets ('.json_encode($missed).')');
    httpCheck($wrong === [], 'a read of them (also for update), and a write of another table or one that only shares part of a name (roles_archive, permissions_log), empties nothing ('.json_encode($wrong).')');

    // (b) Through the listener, on a real connection with a table prefix: a write to kit_model_has_roles there empties the request's sets, so the next
    // check reads its workspace's set again; a read of it does not.
    $permissions = app(Tey\Kit\Features\Access\Permissions::class);
    $config->set('database.connections.prefixed', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'kit_', 'foreign_key_constraints' => false]);
    Schema::connection('prefixed')->create($tables['model_has_roles'], function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->unsignedBigInteger('workspace_id');
    });
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
        $reads += $query->connectionName === 'testing' ? 1 : 0;
    });
    $permissions->remember();
    $check = function () use ($permissions, $gil, $global, &$reads): array {
        $reads = 0;

        return [$permissions->granted($gil, 'workspace.switch.audit', $global), $reads];
    };
    $first = $check();
    $memoized = $check();
    DB::connection('prefixed')->table($tables['model_has_roles'])->get();
    $afterRead = $check();
    DB::connection('prefixed')->table($tables['model_has_roles'])->insert(['role_id' => $auditor->id, 'model_type' => $gil->getMorphClass(), 'model_id' => $gil->id, 'workspace_id' => $global->id]);
    $afterWrite = $check();
    httpCheck($first[0] && $first[1] > 0 && $memoized === [true, 0] && $afterRead === [true, 0] && $afterWrite[0] && $afterWrite[1] > 0, 'a write to kit_model_has_roles on a connection prefixed kit_ empties the sets: the next check reads afresh; a read of it keeps them ('.json_encode([$first, $memoized, $afterRead, $afterWrite]).')');
    echo "Workspace list checks on SQLite memory with real Concierge, Fortify and Inertia. No browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
