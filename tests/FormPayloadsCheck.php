<?php

// The browser job's recorded form payloads (tests/fixtures/form-payloads, written by browser/run.mjs's Save tests) posted to the real routes, as the page's
// Inertia visit sends them (X-Inertia, a JSON body), by an admin of a synthetic workspace on SQLite memory, with a fake SendingDomains: no provider is called.
// Each save must answer with the redirect to its page, no validation errors, and the values stored. The MCP consent payload is posted in
// McpExplicitGrantsCheck, which has Passport's authorization request to approve.
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
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
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
use Tey\Kit\Features\Settings\Mail\SendingDomains;
use Tey\Kit\Features\Settings\WorkspaceSettings;
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

class HttpUser extends Authenticatable implements Member
{
    use HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(HttpUser::class, 'App\Models\User');

try {
    $base = sys_get_temp_dir().'/kit-form-payloads-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
        'mail' => ['from' => ['address' => 'platform@kit.test', 'name' => 'Kit'], 'reply_to' => ['address' => 'support@kit.test', 'name' => 'Kit support']],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);

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
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach ($stubs as $file) {
        (require $file)->up();
    }

    $app['router']->middlewareGroup('web', [StartSession::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $workspace->execute(function () use ($registry, $workspace): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($workspace);
        }
    });
    $people = [];
    foreach (['owner', 'admin', 'member', 'outsider'] as $name) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        if ($name === 'outsider') {
            continue;
        }
        ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    // The provider is a fake that records its calls: no check reaches Resend.
    $fake = new class implements SendingDomains
    {
        public array $calls = [];

        public string $next = 'pending';

        public bool $down = false;

        public function create(string $domain): array
        {
            $this->calls[] = ['create', $domain];
            if ($this->down) {
                throw new RuntimeException('mail_domain_unavailable');
            }

            return ['id' => 'dom_1', 'status' => 'pending', 'records' => [['type' => 'TXT', 'name' => 'resend._domainkey.'.$domain, 'value' => 'p=KEY', 'priority' => null, 'status' => 'pending'], ['type' => 'MX', 'name' => $domain, 'value' => 'feedback-smtp.example.test', 'priority' => 10, 'status' => 'pending']]];
        }

        public function check(string $id): array
        {
            $this->calls[] = ['check', $id];

            return ['id' => $id, 'status' => $this->next, 'records' => []];
        }
    };
    $app->instance(SendingDomains::class, $fake);
    $sync->run(new ArrayInput([]), new NullOutput);
    $other = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    $other->execute(function () use ($registry, $other): void {
        foreach ($registry->provisioners() as $provision) {
            $provision($other);
        }
    });

    // One fresh workspace per fixture: each was recorded from a page at version 1.
    $fresh = function (string $slug) use ($registry, $people): Workspace {
        $created = Workspace::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $created->execute(function () use ($registry, $created): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($created);
            }
        });
        ConciergeModels::membership()::query()->create(['workspace_id' => $created->id, 'user_id' => $people['admin']->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($created, fn () => $people['admin']->assignRole('admin'), [$people['admin']]);

        return $created;
    };
    $fixture = fn (string $name): array => json_decode((string) file_get_contents(dirname(__DIR__).'/tests/fixtures/form-payloads/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    // The page's Save: an Inertia PUT with the payload as its JSON body, as the browser sent it.
    $save = function (Workspace $in, string $path, array $payload) use ($app, $people): array {
        Auth::guard('web')->setUser($people['admin']);
        $url = 'https://kit.test/@'.$in->slug.'/'.$path;
        $request = Request::create($url, 'PUT', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'CONTENT_TYPE' => 'application/json', 'HTTP_REFERER' => $url], content: json_encode($payload, JSON_THROW_ON_ERROR));
        $request->setUserResolver(fn () => $people['admin']);
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
    $saved = fn (array $result, string $url): bool => in_array($result[0], [302, 303], true) && $result[1] === $url && $result[2] === [];
    $describe = fn (array $result): string => ' (got '.$result[0].' '.$result[1].' '.json_encode($result[2]).')';

    // General settings.
    $general = $fresh('general');
    $result = $save($general, 'settings/general', $fixture('general'));
    httpCheck($saved($result, 'https://kit.test/@general/settings/general'), 'General: the recorded payload saves, redirecting to the page with no errors'.$describe($result));
    $general->refresh();
    $timezone = $general->execute(fn () => app(WorkspaceSettings::class)->timezone);
    httpCheck($general->name === 'Northwind Farms' && $timezone === 'Europe/Amsterdam' && (int) $general->settings_version === 2, 'General: the name and timezone are stored and the version moves on');

    // Email sending, own domain with a custom reply-to.
    $email = $fresh('email');
    $result = $save($email, 'settings/email', $fixture('email'));
    httpCheck($saved($result, 'https://kit.test/@email/settings/email'), 'Email sending: the recorded payload saves, redirecting to the page with no errors'.$describe($result));
    $row = DB::table('workspace_mail_identities')->where('workspace_id', $email->id)->first();
    httpCheck($row !== null && $row->sender_name === 'Northwind Support' && $row->from_mode === 'domain' && $row->domain === 'send.northwind.example' && $row->from_local === 'news' && $row->reply_to_mode === 'custom' && $row->reply_to_address === 'help@northwind.example', 'Email sending: every value is stored, the domain on its send. subdomain');
    httpCheck($fake->calls === [['create', 'send.northwind.example']], 'Email sending: the domain is added at the (fake) provider once');

    // Email sending, the domain itself (the page's recommended choice): posted as root, stored as domain without send.
    $root = $fresh('root');
    $fake->calls = [];
    $result = $save($root, 'settings/email', $fixture('email-root'));
    httpCheck($saved($result, 'https://kit.test/@root/settings/email'), 'Email sending, the domain itself: the recorded payload saves, redirecting to the page with no errors'.$describe($result));
    $row = DB::table('workspace_mail_identities')->where('workspace_id', $root->id)->first();
    httpCheck($row !== null && $row->from_mode === 'domain' && $row->domain === 'northwind.example' && $row->from_local === 'news', 'Email sending, the domain itself: stored as domain, with no send. prefix');
    httpCheck($fake->calls === [['create', 'northwind.example']], 'Email sending, the domain itself: the bare domain is added at the (fake) provider once');

    // Email sending, back to the platform address (the RadioCardGroup's other choice) with an emptied sender name.
    $platform = $fresh('platform');
    $result = $save($platform, 'settings/email', $fixture('email-platform'));
    httpCheck($saved($result, 'https://kit.test/@platform/settings/email'), 'Email sending, platform address: the recorded payload saves, redirecting to the page with no errors'.$describe($result));
    $row = DB::table('workspace_mail_identities')->where('workspace_id', $platform->id)->first();
    httpCheck($row !== null && $row->sender_name === null && $row->from_mode === 'platform' && $row->reply_to_mode === 'member', 'Email sending, platform address: stored, with the empty sender name kept as none');

    httpCheck(Workspace::current() === null, 'no tenant context leaks out of the checks');
    echo "Form payload checks on SQLite memory: the browser job's recorded payloads, router dispatch, a fake provider.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
