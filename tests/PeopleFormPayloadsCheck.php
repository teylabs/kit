<?php

// The browser job's recorded People and Delete workspace payloads (tests/fixtures/form-payloads/people-*.json and workspace-delete.json, written by
// browser/run.mjs) posted to the real routes as the page's Inertia visit sends them (X-Inertia, a JSON body), by the owner of a synthetic workspace on SQLite
// memory: real Concierge, spatie teams, Pennant, inertiaui/modal and the kit's router; no network. Each write is sent to the URL the real People page offers
// for it, with only the server-issued ids swapped in (the transfer's membership), and must redirect where the controller says, with no validation errors and
// the change stored. The two refused-role payloads must come back inline on `errors.role` (#823), with nothing changed.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
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
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use InertiaUI\Modal\ModalServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
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
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData;
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

class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-people-payloads-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.search' => true], 'search' => ['per_minute' => 1000], 'people' => ['hide_grants' => []], 'mcp' => ['ui' => true], 'lifecycle' => ['workspace_delete' => true]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, ModalServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class);
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    // Ownership and deletion are throttled (`throttle:5,1`), as a host's kernel aliases it.
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
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

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']], 'support' => ['label' => 'Support', 'grantable_by' => ['owner']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    // The browser's workspace and people (invented): Robin owns Northwind Farms, Ben is an admin, Ana a member, Kai a suspended member, and Sam is invited.
    $workspace = Workspace::create(['name' => 'Northwind Farms', 'slug' => 'northwind']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $people = [];
    $memberships = [];
    foreach (['robin' => ['Robin Park', 'owner'], 'ben' => ['Ben Sample', 'admin'], 'ana' => ['Ana Lopez', 'member'], 'kai' => ['Kai Moreno', 'member']] as $key => [$name, $role]) {
        $people[$key] = HttpUser::create(['name' => $name, 'email' => $key.'@example.com', 'email_verified_at' => now(), 'password' => Hash::make('correct horse battery staple')]);
        $memberships[$key] = ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$key]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $people[$key]->assignRole($role), [$people[$key]]);
    }
    $sync->run(new ArrayInput([]), new NullOutput);
    ConciergeModels::ownership()::query()->create(['workspace_id' => $workspace->id, 'membership_id' => $memberships['robin']->id]);
    $workspace->refresh();
    Auth::guard('web')->setUser($people['robin']);
    $workspace->execute(fn () => ManagePeople::make()->invite($people['robin'], 'sam@example.com', 'member'));

    $fixture = fn (string $name): array => json_decode((string) file_get_contents(dirname(__DIR__).'/tests/fixtures/form-payloads/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    $people_url = 'https://kit.test/@northwind/settings/people';
    // The page's write: an Inertia visit with the payload as its JSON body, sent from People (the Referer), as the browser sent it.
    $send = function (string $who, string $method, string $url, array $payload) use ($app, $people, $people_url): array {
        Auth::guard('web')->setUser($people[$who]);
        $request = Request::create($url, $method, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json', 'HTTP_REFERER' => $people_url], content: json_encode((object) $payload, JSON_THROW_ON_ERROR));
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        $app['session.store']->forget('errors');
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();
        $bag = $app['session.store']->get('errors');

        return [$response->getStatusCode(), (string) $response->headers->get('Location'), $bag ? $bag->getBag('default')->toArray() : []];
    };
    $describe = fn (array $result): string => ' (got '.$result[0].' '.$result[1].' '.json_encode($result[2]).')';
    // People's address in either form WorkspaceUrl gives it (the full /@northwind/... or the short one for a person with one workspace).
    $redirected = fn (array $result, string $to): bool => in_array($result[0], [302, 303], true) && in_array($result[1], [$to, str_replace('/@northwind', '', $to)], true) && $result[2] === [];
    // The URL the real People page offers for an action on a row, as the browser takes it from the descriptor.
    $offered = function (string $key, string $label, string $tab = 'members') use ($app, $people): string {
        Auth::guard('web')->setUser($people['robin']);
        $request = Request::create('https://kit.test/@northwind/settings/people'.($tab === 'members' ? '' : '?tab='.$tab), 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => $people['robin']);
        $app->instance('request', $request);
        $page = json_decode((string) $app['router']->dispatch($request)->getContent(), true);
        Feature::flushCache();
        foreach ($page['props']['table']['rows'] as $row) {
            if (($row['member']['name'] ?? $row['email'] ?? null) !== $label) {
                continue;
            }
            foreach ($row['actions'] as $action) {
                if ($action['key'] === $key) {
                    return str_starts_with($action['url'], 'http') ? $action['url'] : 'https://kit.test'.$action['url'];
                }
            }
        }
        throw new RuntimeException('People offers no '.$key.' on '.$label);
    };
    $status = fn (string $key): MembershipStatus => $memberships[$key]->fresh()->status;
    $role = fn (string $key): ?string => app(Concierge::class)->roleNames($memberships[$key]->fresh())[0] ?? null;

    // Change role: Ana, member to admin.
    $result = $send('robin', 'PUT', $offered('change-role', 'Ana Lopez'), $fixture('people-change-role'));
    httpCheck($redirected($result, $people_url), 'Change role: the recorded payload saves, redirecting to People with no errors'.$describe($result));
    httpCheck($role('ana') === 'admin', 'Change role: Ana is an admin');

    // A role refused on save (#823): Ben, an admin, may not give admin. Inline on the role field, nothing changed.
    $result = $send('ben', 'PUT', 'https://kit.test/@northwind/settings/people/members/'.$memberships['kai']->id, $fixture('people-change-role-refused'));
    httpCheck(in_array($result[0], [302, 303], true) && isset($result[2]['role']) && $role('kai') === 'member', 'Change role, refused: comes back on errors.role, not a 403, and Kai keeps his role'.$describe($result));

    // Invite member.
    $result = $send('robin', 'POST', $people_url.'/invitations', $fixture('people-invite'));
    httpCheck($redirected($result, $people_url), 'Invite member: the recorded payload sends, redirecting to People with no errors'.$describe($result));
    $invitation = ConciergeModels::invitation()::query()->where('email', 'lee@example.com')->first();
    httpCheck($invitation !== null && $invitation->roleNames() === ['admin'] && $invitation->message === 'Welcome to the farm team.', 'Invite member: the invitation is stored with its role and message');
    $result = $send('ben', 'POST', $people_url.'/invitations', $fixture('people-invite-refused'));
    httpCheck(in_array($result[0], [302, 303], true) && isset($result[2]['role']) && ConciergeModels::invitation()::query()->where('email', 'lee@example.com')->count() === 1, 'Invite member, refused: comes back on errors.role, not a 403, and nothing more is sent'.$describe($result));

    // Suspend and Reinstate Kai.
    $result = $send('robin', 'POST', $offered('suspend', 'Kai Moreno'), $fixture('people-suspend'));
    httpCheck($redirected($result, $people_url) && $status('kai') === MembershipStatus::Suspended, 'Suspend: the recorded payload suspends Kai'.$describe($result));
    $result = $send('robin', 'POST', $offered('reinstate', 'Kai Moreno'), $fixture('people-reinstate'));
    httpCheck($redirected($result, $people_url) && $status('kai') === MembershipStatus::Active && $role('kai') === 'member', 'Reinstate: the recorded payload reinstates Kai with his role'.$describe($result));

    // Resend and Revoke Sam's invitation (back() lands on the Referer, People).
    $sam = fn () => ConciergeModels::invitation()::query()->where('email', 'sam@example.com')->first();
    // Sent a week ago, as the browser's row shows it: past Concierge's resend cooldown.
    $sam()->forceFill(['last_sent_at' => now()->subWeek(), 'created_at' => now()->subWeek()])->save();
    $result = $send('robin', 'PUT', $offered('resend', 'sam@example.com', 'invitations'), $fixture('people-resend'));
    httpCheck(in_array($result[0], [302, 303], true) && $result[2] === [] && $app['session.store']->get('invite_link') !== null, 'Resend: the recorded payload issues a fresh link, with no errors'.$describe($result));
    $result = $send('robin', 'DELETE', $offered('revoke', 'sam@example.com', 'invitations'), $fixture('people-revoke'));
    httpCheck(in_array($result[0], [302, 303], true) && $result[2] === [] && ($sam() === null || $sam()->revoked_at !== null || $sam()->status?->value === 'revoked'), 'Revoke: the recorded payload revokes the invitation'.$describe($result));

    // Transfer ownership to Ana: the membership the page carries is the server's, swapped in for the browser's stand-in id.
    $offered('transfer-ownership', 'Ana Lopez');
    $payload = ['membership' => (string) $memberships['ana']->id] + $fixture('people-transfer');
    $result = $send('robin', 'PUT', 'https://kit.test/@northwind/settings/ownership', $payload);
    httpCheck($redirected($result, $people_url), 'Transfer ownership: the recorded payload transfers, redirecting to People with no errors'.$describe($result));
    httpCheck(app(Concierge::class)->isOwner($memberships['ana']->fresh()) && ! app(Concierge::class)->isOwner($memberships['robin']->fresh()), 'Transfer ownership: Ana owns the workspace and Robin no longer does');

    // Remove Kai (Ana, the owner now, removes him).
    $result = $send('ana', 'DELETE', 'https://kit.test/@northwind/settings/people/members/'.$memberships['kai']->id, $fixture('people-remove'));
    httpCheck($redirected($result, $people_url) && $status('kai') !== MembershipStatus::Active, 'Remove: the recorded payload removes Kai'.$describe($result));

    // The host's purge handler for its own data: none here, said explicitly (the kit refuses a deletion without one).
    $app->instance(PurgesWorkspaceData::class, new class implements PurgesWorkspaceData
    {
        public function purge(Tey\Concierge\Contracts\Workspace $workspace): void {}
    });
    // Delete workspace, by its owner (Ana) with her password.
    $result = $send('ana', 'DELETE', 'https://kit.test/@northwind/settings/delete', $fixture('workspace-delete'));
    httpCheck($redirected($result, 'https://kit.test/me/workspaces'), 'Delete workspace: the recorded payload deletes, redirecting to the workspace list with no errors'.$describe($result));
    httpCheck(Workspace::query()->whereKey($workspace->id)->doesntExist(), 'Delete workspace: the workspace is gone');

    httpCheck(Workspace::current() === null, 'no tenant context leaks out of the checks');
    echo "People form payload checks on SQLite memory: the browser job's recorded payloads, router dispatch; no network or browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
