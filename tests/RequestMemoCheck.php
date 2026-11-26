<?php

// Request memos (RequestMemo, on Laravel's once()): the lookups the shell, the page's layout and the page each repeat (the workspace by id, user_state,
// the workspace's settings, the avatar, the membership, ownership and account, the permission set) run once per request. (1) Query budgets for the
// dashboard, its deferred people list and the General settings page: with once() off every lookup repeats; on, each runs once and the only exact
// duplicates left are the documented ones. (2) A write and then a read in one request sees the write, for each lookup and by any path (the kit's
// writer, a raw query, a rollback, a read after a transaction begins). (3) Two requests in turn, as different people and workspaces, share nothing,
// also when nothing in the process wrote in between; and the flushes Octane runs after each request (Once::flush() and forgetting scoped instances)
// each empty a memo on their own. (4) A queued job, run in the request (sync) or after it by the worker (`queue:work --once`, which resets nothing),
// never sees what the request read. SQLite memory, real Concierge, spatie teams, Pennant and settings, synthetic people and workspaces.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\TestHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class MemoUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(MemoUser::class, 'App\Models\User');

function memoCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** A job that reads the person's own timezone (no workspace's, so not tenant-aware) and counts the queries it took: 0 would be an answer left from the request that dispatched it. */
final class MemoProbeJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var list<array{zone: ?string, queries: int}> */
    public static array $seen = [];

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $zone = UserState::get($this->userId, 'timezone');
        $before = $queries;
        UserState::get($this->userId, 'timezone');
        self::$seen[] = ['zone' => $zone, 'queries' => $before, 'again' => $queries - $before];
    }
}

try {
    $base = sys_get_temp_dir().'/kit-request-memo-'.bin2hex(random_bytes(4));
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
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => MemoUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90], 'sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => MemoUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true]],
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
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);

    // Real tables: the host's users and jobs, the kit's stubs with Concierge's right after the workspaces table, Passport's and medialibrary's.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach ($stubs as $name => $file) {
        (require $file)->up();
        if (str_contains($name, '000200_create_kit_workspaces_table')) {
            foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $migration) {
                (require $migration)->up();
            }
        }
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);
    $spaces = [];
    foreach (['acme' => 'Acme', 'globex' => 'Globex'] as $slug => $name) {
        $spaces[$slug] = Workspace::create(['name' => $name, 'slug' => $slug]);
        $spaces[$slug]->execute(function () use ($registry, $spaces, $slug): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($spaces[$slug]);
            }
        });
    }
    // Ada owns Acme and is an admin of Globex; Grace is a member of Acme. Ada has a photo, a saved timezone and confirmed it.
    $people = [];
    $memberships = [];
    foreach (['ada' => ['acme' => 'owner', 'globex' => 'admin'], 'grace' => ['acme' => 'member']] as $name => $roles) {
        $people[$name] = MemoUser::create(['name' => ucfirst($name).' Lovelace', 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        foreach ($roles as $slug => $role) {
            $memberships[$name][$slug] = ConciergeModels::membership()::query()->create(['workspace_id' => $spaces[$slug]->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
            app(Concierge::class)->context()->run($spaces[$slug], fn () => $people[$name]->assignRole($role), [$people[$name]]);
        }
    }
    DB::table('concierge_ownerships')->insert(['workspace_id' => $spaces['acme']->id, 'membership_id' => $memberships['ada']['acme']->id, 'created_at' => now(), 'updated_at' => now()]);
    $avatar = function (MemoUser $user, string $token): void {
        DB::table('media')->insert(['model_type' => 'user-avatar', 'model_id' => (string) $user->id, 'collection_name' => 'avatar', 'name' => 'photo', 'file_name' => 'photo.jpg', 'disk' => 'local', 'size' => 1,
            'manipulations' => '[]', 'custom_properties' => json_encode(['token' => $token]), 'generated_conversions' => '[]', 'responsive_images' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    };
    $avatar($people['ada'], str_repeat('a', 40));
    UserState::set((int) $people['ada']->id, 'timezone', 'Europe/Paris');
    UserState::set((int) $people['ada']->id, 'timezone.confirmed');
    $sync->run(new ArrayInput([]), new NullOutput);
    foreach ($spaces as $space) {
        $space->refresh();
    }

    // A write made where this process can't see it (another worker, another request): no event fires here, so no memo is emptied by it.
    $elsewhere = function (Closure $write): void {
        $connection = DB::connection();
        $events = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();
        try {
            $write();
        } finally {
            $connection->setEventDispatcher($events);
        }
    };
    /** @var list<array{0: string, 1: string}> */
    $log = [];
    $logging = false;
    DB::listen(function ($query) use (&$log, &$logging): void {
        if ($logging) {
            $log[] = [$query->sql, json_encode($query->bindings)];
        }
    });
    $send = function (string $who, string $path, array $headers = []) use ($app, $people, &$log, &$logging): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', ...$headers]);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        $log = [];
        $logging = true;
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        } finally {
            $logging = false;
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true), $log];
    };
    // How many queries each covered lookup ran, by its statement; and the exact duplicates (same statement, same bindings) by statement.
    $lookups = [
        'workspace by id' => '/^select \* from "workspaces" where "workspaces"\."id" = \?/',
        'user_state' => '/from "user_state"/',
        'settings' => '/from "settings" where "group"/',
        'avatar' => '/from "media" where "model_type"/',
        'membership' => '/from "concierge_memberships" where "workspace_id" = \? and "user_id" = \?/',
        'ownership' => '/from "concierge_ownerships"/',
        'account (kit)' => '/from "concierge_accounts"/',
        'permission set' => '/from "permissions" inner join "model_has_permissions"/',
    ];
    $count = fn (array $log): array => array_map(fn (string $pattern): int => count(array_filter($log, fn (array $query): bool => preg_match($pattern, $query[0]) === 1)), $lookups);
    $duplicates = function (array $log): array {
        $seen = [];
        foreach ($log as [$sql, $bindings]) {
            $seen[$sql.' '.$bindings] = ($seen[$sql.' '.$bindings] ?? 0) + 1;
        }
        $repeated = [];
        foreach ($seen as $query => $times) {
            if ($times > 1) {
                $repeated[] = (preg_match('/ from ("[a-z_]+")/', $query, $table) === 1 ? $table[1] : $query).' ×'.$times;
            }
        }
        sort($repeated);

        return $repeated;
    };
    $people_list = ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Workspace/Dashboard/Page', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'dashboard_people'];
    // A first request warms what the app builds once per process (the permission catalog, the column checks); every count below is a later request.
    $send('ada', '/@acme');

    // (1) The query budgets. With once() off (no memo at all) every covered lookup repeats on these pages, so they exercise each one.
    Once::disable();
    try {
        [$offStatus, , $off] = $send('ada', '/@acme');
        [, , $offPeople] = $send('ada', '/@acme', $people_list);
        [, , $offSettings] = $send('ada', '/@acme/settings/general');
    } finally {
        Once::enable();
    }
    [$status, $dashboard, $on] = $send('ada', '/@acme');
    [$peopleStatus, , $onPeople] = $send('ada', '/@acme', $people_list);
    [$settingsStatus, , $onSettings] = $send('ada', '/@acme/settings/general');
    echo 'Dashboard: '.count($off).' queries with once() off, '.count($on).' on; its people list '.count($offPeople).' → '.count($onPeople).'; General settings '.count($offSettings).' → '.count($onSettings)."\n";
    echo 'Dashboard lookups, off: '.json_encode($count($off)).' on: '.json_encode($count($on))."\n";
    $repeatedOff = array_filter($count($off), fn (int $n): bool => $n > 1);
    memoCheck($offStatus === 200 && array_keys($repeatedOff) === ['user_state', 'settings', 'avatar', 'membership', 'ownership', 'account (kit)', 'permission set'], 'with once() off the dashboard repeats each covered lookup it makes: user_state, settings, avatar, membership, ownership, account and the permission set ('.json_encode($repeatedOff).')');
    memoCheck($count($offSettings)['workspace by id'] > 1, 'with once() off the General settings page reads its workspace by id more than once ('.$count($offSettings)['workspace by id'].')');
    memoCheck($status === 200 && ($dashboard['component'] ?? null) === 'Workspace/Dashboard/Page' && $count($on) === ['workspace by id' => 0, 'user_state' => 3, 'settings' => 1, 'avatar' => 1, 'membership' => 1, 'ownership' => 1, 'account (kit)' => 1, 'permission set' => 1]
        && count(array_filter($on, fn (array $query): bool => str_contains($query[0], 'from "user_state" where ("user_id" = ? and "workspace_id" = ?)'))) === 2, 'on the dashboard each lookup runs once: user_state once for Ada\'s own state, once for hers in Acme and once for Acme\'s confirmed timezone, and one settings, avatar, membership, ownership and permission-set read ('.json_encode($count($on)).')');
    memoCheck($peopleStatus === 200 && $count($onPeople) === $count($on), 'the dashboard\'s deferred people list runs each lookup once too ('.json_encode($count($onPeople)).')');
    memoCheck($settingsStatus === 200 && $count($onSettings)['workspace by id'] === 1 && $count($onSettings)['settings'] === 1 && $count($onSettings)['user_state'] === 1, 'the General settings page reads its workspace by id, its settings and user_state once each ('.json_encode($count($onSettings)).')');
    // The duplicate that is left, documented: spatie reloads the roles (Concierge's own account read, concierge#164, now goes through the kit's memo)
    // pivot inside each Concierge context run (once for the You card's role, once for the permission set), so roles from another workspace never answer here.
    $documented = ['"roles" ×2'];
    memoCheck($duplicates($on) === $documented, 'the dashboard\'s only exact duplicates are the documented ones ('.json_encode($duplicates($on)).')');
    memoCheck($duplicates($onPeople) === $documented && $duplicates($onSettings) === [], 'and so are the people list\'s and the settings page\'s ('.json_encode([$duplicates($onPeople), $duplicates($onSettings)]).')');
    memoCheck(count($on) === 17 && count($onPeople) === 18 && count($onSettings) === 14, 'the budgets: 17 queries for the dashboard, 18 for its people list, 14 for General settings ('.count($on).', '.count($onPeople).', '.count($onSettings).')');

    // (2) A write, then a read, in one request: each lookup sees the write, whichever way it was made.
    $ada = (int) $people['ada']->id;
    $grace = (int) $people['grace']->id;
    $app['router']->middleware('web')->get('/memo/writes', function () use ($ada, $grace, $spaces, $memberships, $avatar, $people, $elsewhere): array {
        $seen = [];
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        UserState::get($ada, 'timezone');
        $before = $queries;
        $seen['memo live'] = UserState::get($ada, 'timezone') === 'Europe/Paris' && $queries === $before;
        UserState::set($ada, 'timezone', 'Asia/Tokyo');
        $seen['user_state set'] = UserState::get($ada, 'timezone');
        UserState::forget($ada, 'timezone.confirmed');
        $seen['user_state forget'] = UserState::has($ada, 'timezone.confirmed');
        DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'America/Toronto']);
        $seen['user_state raw'] = UserState::many($ada, ['timezone']);
        $seen['settings'] = Workspaces::run($spaces['acme'], function (): array {
            $first = WorkspaceSettings::current()->timezone;
            $edit = WorkspaceSettings::current();
            $edit->timezone = 'Europe/Berlin';
            $unsaved = WorkspaceSettings::current()->timezone;
            $edit->save();

            return [$first, $unsaved, WorkspaceSettings::current()->timezone];
        });
        $seen['avatar'] = [Avatars::urls($grace), $avatar($people['grace'], str_repeat('g', 40)), Avatars::urls($grace)['url'] ?? null];
        $seen['workspace'] = [Workspaces::find($spaces['globex']->id)->name, DB::table('workspaces')->where('id', $spaces['globex']->id)->update(['name' => 'Globex Corp']), Workspaces::find($spaces['globex']->id)->name];
        $seen['ownership'] = [RequestMemo::owns($memberships['ada']['globex']), DB::table('concierge_ownerships')->insert(['workspace_id' => $spaces['globex']->id, 'membership_id' => $memberships['ada']['globex']->id]), RequestMemo::owns($memberships['ada']['globex'])];
        $seen['membership'] = [RequestMemo::membership($people['grace'], $spaces['acme'])?->isActive(), DB::table('concierge_memberships')->where('id', $memberships['grace']['acme']->id)->update(['status' => MembershipStatus::Suspended->value]), RequestMemo::membership($people['grace'], $spaces['acme'])?->isActive()];
        // A rollback undoes a write the memo has seen since: the read after it is the row as it was.
        try {
            DB::transaction(function () use ($ada): void {
                UserState::set($ada, 'timezone', 'Pacific/Auckland');
                UserState::get($ada, 'timezone');
                throw new RuntimeException('roll back');
            });
        } catch (RuntimeException) {
        }
        $seen['rollback'] = UserState::get($ada, 'timezone');
        // A transaction that locks a row before reading reads afresh after it begins (SQLite compiles no lock; PostgreSQL and MySQL send one, which empties it too).
        UserState::get($ada, 'timezone');
        $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Africa/Nairobi']));
        $seen['outside, unseen'] = UserState::get($ada, 'timezone');
        $seen['locked read'] = DB::transaction(fn (): ?string => UserState::get($ada, 'timezone'));

        return $seen;
    });
    $app['router']->getRoutes()->refreshNameLookups();
    [, $writes] = $send('ada', '/memo/writes');
    memoCheck(($writes['memo live'] ?? null) === true, 'within a request a second user_state read is answered from the memo, with no query');
    memoCheck($writes['user_state set'] === 'Asia/Tokyo' && $writes['user_state forget'] === false && $writes['user_state raw'] === ['timezone' => 'America/Toronto'], 'UserState::set() and forget(), and a raw update of user_state, are each seen by the next read in the request');
    memoCheck($writes['settings'] === ['UTC', 'UTC', 'Europe/Berlin'], 'settings: a change one caller makes without saving reaches no other caller, and the saved value is read back at once ('.json_encode($writes['settings']).')');
    memoCheck($writes['avatar'][0] === null && $writes['avatar'][2] === '/avatars/'.str_repeat('g', 40), 'avatar: a new photo is seen by the next read in the request');
    memoCheck($writes['workspace'] === ['Globex', 1, 'Globex Corp'], 'workspace by id: a renamed workspace is seen by the next read in the request');
    memoCheck($writes['ownership'] === [false, true, true], 'ownership: a new owner is seen by the next read in the request');
    memoCheck($writes['membership'] === [true, 1, false], 'membership: a suspended membership is seen by the next read in the request (fails closed)');
    memoCheck($writes['rollback'] === 'America/Toronto', 'a rollback empties the memo: the read after it is the committed row, not the write it undid');
    memoCheck($writes['outside, unseen'] === 'America/Toronto' && $writes['locked read'] === 'Africa/Nairobi', 'a write made outside this process is not seen while the request\'s memo stands, and is seen once a transaction begins, as a locked read needs');
    memoCheck(RequestMemo::writesOrLocks('select * from "users" where "id" = ? limit 1 for update') && RequestMemo::writesOrLocks('select * from "users" for no key update') && RequestMemo::writesOrLocks('select * from `users` lock in share mode') && RequestMemo::writesOrLocks('select * from "users" for share')
        && RequestMemo::writesOrLocks('insert or ignore into "user_state" ("key") values (?)') && RequestMemo::writesOrLocks('with "x" as (select 1) update "users" set "name" = ?') && RequestMemo::writesOrLocks('  delete from "media"')
        && ! RequestMemo::writesOrLocks('select set_config(?, ?, true)') && ! RequestMemo::writesOrLocks('select "updated_at", "deleted" from "updates"') && ! RequestMemo::writesOrLocks('select * from "users" where "name" = \'for\''), 'writesOrLocks(): writes on every grammar, behind a CTE, and locking reads (for update, no key update, share, lock in share mode) empty the memo; plain reads, set_config and a column named updated_at do not');
    DB::table('concierge_memberships')->where('id', $memberships['grace']['acme']->id)->update(['status' => MembershipStatus::Active->value]);
    DB::table('concierge_ownerships')->where('workspace_id', $spaces['globex']->id)->delete();
    DB::table('workspaces')->where('id', $spaces['globex']->id)->update(['name' => 'Globex']);
    UserState::set($ada, 'timezone', 'Europe/Paris');
    UserState::set($ada, 'timezone.confirmed');

    // (3) Two requests in turn as different people, then as the same person in another workspace: neither sees the other's answers.
    $you = fn (array $page): array => array_column($page['props']['widgets'] ?? [], null, 'key')['kit.you']['person'] ?? [];
    [, $first] = $send('ada', '/@acme');
    [, $second] = $send('grace', '/@acme');
    [, $third] = $send('ada', '/@globex');
    memoCheck($you($first)['name'] === 'Ada Lovelace' && $you($first)['timezone'] === 'Europe/Paris' && $you($first)['avatar']['url'] === '/avatars/'.str_repeat('a', 40) && $first['props']['shell']['user']['email'] === 'ada@example.test', 'request 1 (Ada, Acme): her name, timezone and photo');
    memoCheck($you($second)['name'] === 'Grace Lovelace' && $you($second)['timezone'] === 'Europe/Berlin' && $you($second)['avatar']['url'] === '/avatars/'.str_repeat('g', 40) && $you($second)['role'] === 'Member' && $second['props']['shell']['user']['email'] === 'grace@example.test' && array_column($second['props']['shell']['switcher']['choices'], 'slug') === ['acme'], 'request 2 (Grace, Acme): her own name, photo, role and shell, Acme\'s timezone (she has none of her own), and only her workspace in the switcher: none of Ada\'s');
    memoCheck($you($third)['workspace'] === 'Globex' && $you($third)['role'] === 'Admin' && $third['props']['shell']['workspace']['name'] === 'Globex', 'request 3 (Ada, Globex): Globex\'s name and her role there, not Acme\'s');
    // A change made where this process sees no event, between two requests: the next request reads it.
    $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Asia/Kolkata']));
    [, $fourth] = $send('ada', '/@acme');
    memoCheck($you($fourth)['timezone'] === 'Asia/Kolkata', 'the next request reads afresh, also with no write seen in between: a new request object starts its own memo');
    $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Europe/Paris']));

    // Octane serves requests from one long-lived app. After each it runs FlushOnce (Once::flush()) and FlushTemporaryContainerInstances (forgetScopedInstances());
    // each is run here on its own, against a memo that is live (the same request object, so nothing else would empty it), and must empty it.
    $memoRead = function () use ($ada): array {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $zone = UserState::get($ada, 'timezone');

        return [$zone, $queries];
    };
    $lifecycle = [];
    $app->instance('request', $worker = Request::create('https://kit.test/octane'));
    foreach (['Once::flush()' => fn () => Once::flush(), 'forgetScopedInstances()' => fn () => $app->forgetScopedInstances()] as $flush => $run) {
        $memo = RequestMemo::instance();
        $memo->remember();
        $memoRead();
        $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Asia/Seoul']));
        $held = $memoRead();
        $run();
        $lifecycle[$flush] = ['held' => $held, 'after' => $memoRead(), 'same instance' => RequestMemo::instance() === $memo];
        $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Europe/Paris']));
    }
    memoCheck($lifecycle['Once::flush()']['held'] === ['Europe/Paris', 0] && $lifecycle['Once::flush()']['after'] === ['Asia/Seoul', 1] && $lifecycle['Once::flush()']['same instance'], 'Octane\'s FlushOnce: with the memo live and holding the old value, Once::flush() alone empties it (the next read queries and sees the new value) ('.json_encode($lifecycle['Once::flush()']).')');
    memoCheck($lifecycle['forgetScopedInstances()']['held'] === ['Europe/Paris', 0] && $lifecycle['forgetScopedInstances()']['after'] === ['Asia/Seoul', 1] && ! $lifecycle['forgetScopedInstances()']['same instance'], 'Octane\'s FlushTemporaryContainerInstances (and the queue worker\'s reset): forgetScopedInstances() alone gives a new memo that holds nothing ('.json_encode($lifecycle['forgetScopedInstances()']).')');
    $memo = RequestMemo::instance();
    $memo->remember();
    $memoRead();
    $app->instance('request', Request::create('https://kit.test/next'));
    memoCheck($memoRead()[1] === 1 && $memo->get('probe', fn (): int => 1) === 1 && $memo->get('probe', fn (): int => 2) === 2, 'with neither flush, a memo begun for one request answers nothing for the next request object: it reads afresh');
    unset($worker);

    // (4) A queued job never sees what the request that dispatched it read: run in the request (sync), or after it on the database queue by the worker's
    // runNextJob (queue:work --once), which resets no scoped instance and keeps the same request object.
    $app['router']->middleware('web')->get('/memo/dispatch', function () use ($ada, $elsewhere): array {
        $read = UserState::get($ada, 'timezone');
        $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Asia/Dubai']));
        dispatch(new MemoProbeJob($ada))->onConnection('sync');
        dispatch(new MemoProbeJob($ada));

        return ['read' => $read, 'after the job' => UserState::get($ada, 'timezone')];
    });
    $app['router']->getRoutes()->refreshNameLookups();
    MemoProbeJob::$seen = [];
    [$dispatchStatus, $dispatched] = $send('ada', '/memo/dispatch');
    $elsewhere(fn () => DB::table('user_state')->where(['user_id' => $ada, 'workspace_id' => 0, 'key' => 'timezone'])->update(['value' => 'Asia/Jakarta']));
    $app->make('queue.worker')->runNextJob('database', 'default', new WorkerOptions);
    memoCheck($dispatchStatus === 200 && $dispatched['read'] === 'Europe/Paris' && (MemoProbeJob::$seen[0] ?? null) === ['zone' => 'Asia/Dubai', 'queries' => 1, 'again' => 0], 'a job run in the request (sync) reads afresh: the current value, with a query, not the request\'s answer ('.json_encode(MemoProbeJob::$seen[0] ?? null).')');
    memoCheck($dispatched['after the job'] === 'Asia/Dubai', 'and the request after the job does not keep what the job read: it reads afresh too');
    memoCheck((MemoProbeJob::$seen[1] ?? null) !== null && MemoProbeJob::$seen[1]['zone'] === 'Asia/Jakarta' && MemoProbeJob::$seen[1]['queries'] === 1, 'a job the worker runs after the request (runNextJob, no reset) reads afresh: the value written since, with a query ('.json_encode(MemoProbeJob::$seen[1] ?? null).')');
} catch (Throwable $error) {
    echo 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n";
    exit(1);
}
echo "Request memo checks on SQLite memory with real Concierge, spatie teams, Pennant and settings, through router dispatch; the database queue's worker in process. Not covered: PostgreSQL with RLS here (RlsCheck covers the set_config skip), a connection pooler, a real Octane worker and consumer apps.\n";
