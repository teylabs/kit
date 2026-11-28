<?php

// Settings → Setup and the settings registry, on SQLite memory with synthetic users, config and a fake mailer: no provider, bucket or queue is called.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Console\Scheduling\Schedule;
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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\NullHandler;
use Psr\Log\AbstractLogger;
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
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Settings\Mail\FindsSendingDomains;
use Tey\Kit\Features\Settings\Mail\MailDomainUnavailable;
use Tey\Kit\Features\Settings\Mail\ResendSendingDomains;
use Tey\Kit\Features\Settings\Mail\SendingDomainLookup;
use Tey\Kit\Features\Settings\Mail\SendingDomains;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSetup;
use Tey\Kit\Features\Settings\Mail\WorkspaceMailIdentity;
use Tey\Kit\Features\Settings\Models\WorkspaceSecret;
use Tey\Kit\Features\Settings\SettingValues;
use Tey\Kit\Features\Settings\Setup\Setup;
use Tey\Kit\Features\Settings\Setup\SetupChecks;
use Tey\Kit\Features\Settings\Setup\SetupRow;
use Tey\Kit\Features\Settings\ViewModels\SetupViewModel;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function setupCheck(bool $ok, string $message): void
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
    $base = sys_get_temp_dir().'/kit-setup-'.bin2hex(random_bytes(4));
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
        'data' => ['structure_caching' => ['enabled' => false, 'cache' => ['store' => 'array']]],
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
    $fake = new class implements FindsSendingDomains, SendingDomains
    {
        public array $calls = [];

        public string $next = 'pending';

        public bool $down = false;

        public ?string $reason = null;

        public ?string $checkReason = null;

        public function create(string $domain): array
        {
            $this->calls[] = ['create', $domain];
            if ($this->down) {
                throw $this->reason === null ? new RuntimeException('mail_domain_unavailable') : new MailDomainUnavailable($this->reason);
            }

            return ['id' => 'dom_1', 'status' => 'pending', 'records' => [['type' => 'TXT', 'name' => 'resend._domainkey.'.$domain, 'value' => 'p=KEY', 'priority' => null, 'status' => 'pending'], ['type' => 'MX', 'name' => $domain, 'value' => 'feedback-smtp.example.test', 'priority' => 10, 'status' => 'pending']]];
        }

        public function check(string $id): array
        {
            $this->calls[] = ['check', $id];
            if ($this->checkReason !== null) {
                throw new MailDomainUnavailable($this->checkReason);
            }

            return ['id' => $id, 'status' => $this->next, 'records' => []];
        }

        public function find(string $id): array
        {
            $this->calls[] = ['find', $id];

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

    $call = function (string $who, string $method, string $path, array $input = [], bool $inertia = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $inertia ? ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_REFERER' => 'https://kit.test/@acme/settings/setup'] : ['HTTP_ACCEPT' => 'application/json'];
        $request = Request::create('https://kit.test/@acme/settings/setup'.$path, $method, $input, server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true), $response];
    };

    $owner = new Principal($people['owner']->id);
    $values = app(SettingValues::class);

    // The registry refuses what it can't resolve.
    foreach ([fn () => (new Registry)->setting('Bad Key', 'app'), fn () => (new Registry)->setting('a.b', 'tenant'), fn () => (new Registry)->setting('a.b', 'app', 'float'), fn () => (new Registry)->setting('a', 'app')->setting('a', 'app'), fn () => (new Registry)->setupRow('G', 'x', fn () => null)->setupRow('G', 'x', fn () => null)] as $wrong) {
        try {
            $wrong();
            $refused = false;
        } catch (RuntimeException) {
            $refused = true;
        }
        setupCheck($refused, 'the registry refuses a bad key, scope or type, and a setting or setup row registered twice');
    }

    // Resolution order: the environment variable, then the database, then the default.
    $registry->setting('billing.vendor', 'app', 'string', 'none', env: 'KIT_CHECK_VENDOR', check: fn ($v) => $v === 'bad' ? 'not a vendor' : null);
    $registry->setting('billing.token', 'app', 'string', null, encrypted: true, env: 'KIT_CHECK_TOKEN');
    $registry->setting('crm.api_key', 'workspace', 'string', null, env: 'KIT_CHECK_CRM');
    $registry->setting('crm.limit', 'workspace', 'int', 10);
    $setEnv = function (string $name, ?string $value): void {
        $value === null ? putenv($name) : putenv($name.'='.$value);
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    };
    // Unset, the queue rows assume the documented worker (docs/deploy.md): --timeout=85 on a Flex queue.
    $shipped = require dirname(__DIR__).'/config/kit.php';
    setupCheck(getenv('KIT_QUEUE_WORKER_TIMEOUT') !== false || ($shipped['setup']['queue']['worker_timeout'] === 85 && $shipped['setup']['queue']['flex'] === true), 'unset, the worker timeout defaults to 85 on a Flex queue, the documented worker');
    setupCheck($values->resolve('billing.vendor') === ['value' => 'none', 'source' => 'default'], 'with nothing stored and no variable, a setting is its default');
    $values->put('billing.vendor', 'stripe');
    setupCheck($values->resolve('billing.vendor') === ['value' => 'stripe', 'source' => 'database'], 'a stored value beats the default');
    $setEnv('KIT_CHECK_VENDOR', 'paddle');
    setupCheck($values->resolve('billing.vendor') === ['value' => 'paddle', 'source' => 'env'], 'the environment variable beats the stored value');
    $setEnv('KIT_CHECK_VENDOR', null);
    $values->forget('billing.vendor');
    setupCheck($values->get('billing.vendor') === 'none', 'forgetting the stored value falls back to the default');

    $values->put('billing.token', 'tok_plain_123');
    $raw = (string) DB::table('settings')->where('group', SettingValues::GROUP)->where('name', 'billing.token')->value('payload');
    setupCheck(! str_contains($raw, 'tok_plain_123') && $values->get('billing.token') === 'tok_plain_123', 'an encrypted app setting is stored unreadable and reads back');

    setupCheck($values->get('crm.api_key') === null, 'a workspace setting read outside a workspace has no stored value');
    $workspace->execute(fn () => $values->put('crm.api_key', 'sk_live_secret_acme'));
    $other->execute(fn () => $values->put('crm.api_key', 'sk_live_secret_beta'));
    $stored = DB::table('workspace_secrets')->where('workspace_id', $workspace->id)->where('key', 'crm.api_key')->value('value');
    setupCheck(is_string($stored) && ! str_contains($stored, 'sk_live_secret') && ! str_contains(json_encode(DB::table('workspace_secrets')->get()), 'sk_live'), 'a workspace secret is unreadable raw in workspace_secrets');
    setupCheck(json_decode(Crypt::decryptString($stored), true) === 'sk_live_secret_acme', 'and it is the value encrypted with the app key');
    setupCheck($workspace->execute(fn () => $values->resolve('crm.api_key')) === ['value' => 'sk_live_secret_acme', 'source' => 'database'] && $other->execute(fn () => $values->get('crm.api_key')) === 'sk_live_secret_beta', 'each workspace reads its own secret');
    $setEnv('KIT_CHECK_CRM', 'sk_env');
    setupCheck($workspace->execute(fn () => $values->resolve('crm.api_key')) === ['value' => 'sk_env', 'source' => 'env'], 'the environment variable beats a workspace secret too');
    $setEnv('KIT_CHECK_CRM', null);
    setupCheck($workspace->execute(fn () => $values->get('crm.limit')) === 10, 'a workspace setting with nothing stored is its default');
    $workspace->execute(fn () => $values->put('crm.limit', 25));
    setupCheck($workspace->execute(fn () => $values->get('crm.limit')) === 25 && $other->execute(fn () => $values->get('crm.limit')) === 10, 'a typed workspace value round-trips, for its workspace only');
    setupCheck(! array_key_exists('value', WorkspaceSecret::query()->first()->toArray()), 'the model never serializes its value');

    // Rows, state by state.
    $state = fn (?SetupRow $row): ?string => $row?->state;
    // Production rules: APP_PROFILE=production, so neither environment counts as local; `staging` is a deployed app that isn't production.
    $setEnv('APP_PROFILE', 'production');
    $prod = fn (bool $on) => $app->instance('env', $on ? 'production' : 'staging');
    $prod(false);

    $config->set('mail.default', 'log');
    $config->set('mail.mailers', ['log' => ['transport' => 'log'], 'resend' => ['transport' => 'resend']]);
    setupCheck($state(SetupChecks::mailer()) === 'info', 'mailer: log while building is info');
    $prod(true);
    setupCheck($state(SetupChecks::mailer()) === 'failing' && SetupChecks::mailer()->fix !== null, 'mailer: log in production is failing, with its fix');
    $config->set('mail.default', 'resend');
    setupCheck($state(SetupChecks::mailer()) === 'ok', 'mailer: resend is ok');
    $prod(false);

    $config->set('mail.allow_only', null);
    setupCheck($state(SetupChecks::allowOnly()) === 'ok', 'MAIL_ALLOW_ONLY unset: ok');
    $config->set('mail.allow_only', 'a@example.test, b@example.test,,c@example.test');
    setupCheck($state(SetupChecks::allowOnly()) === 'held' && str_contains(SetupChecks::allowOnly()->detail, '3 addresses'), 'MAIL_ALLOW_ONLY set: held, with its count of addresses');
    $config->set('mail.allow_only', null);

    setupCheck(SetupChecks::mailDomain() === null, 'the sending domain row is left out outside a workspace');
    setupCheck($workspace->execute(fn () => $state(SetupChecks::mailDomain())) === 'info', 'sending domain: the platform address is info');
    DB::table('workspace_mail_identities')->insert(['workspace_id' => $workspace->id, 'from_mode' => 'domain', 'domain' => 'send.acme.test', 'domain_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    setupCheck($workspace->execute(fn () => $state(SetupChecks::mailDomain())) === 'held', 'sending domain: waiting for DNS is held');
    DB::table('workspace_mail_identities')->where('workspace_id', $workspace->id)->update(['domain_status' => 'failed']);
    setupCheck($workspace->execute(fn () => $state(SetupChecks::mailDomain())) === 'failing', 'sending domain: failed verification is failing');
    DB::table('workspace_mail_identities')->where('workspace_id', $workspace->id)->update(['domain_status' => 'verified']);
    setupCheck($workspace->execute(fn () => $state(SetupChecks::mailDomain())) === 'ok', 'sending domain: verified is ok, read from the stored status (no provider call)');
    setupCheck($fake->calls === [], 'no setup row asked the mail provider anything');

    $config->set('filesystems', ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app'], 'r2' => ['driver' => 'fake-bucket'], 'down' => ['driver' => 'fake-down']]]);
    $app['filesystem']->extend('fake-bucket', function () use ($base) { return new Illuminate\Filesystem\FilesystemAdapter(new League\Flysystem\Filesystem($bucket = new League\Flysystem\Local\LocalFilesystemAdapter($base.'/bucket')), $bucket); });
    $app['filesystem']->extend('fake-down', fn () => throw new RuntimeException('unreachable'));
    setupCheck($state(SetupChecks::mediaDisk()) === 'ok' && SetupChecks::bucket() === null, 'media: a local disk while building is ok, with no bucket row');
    $prod(true);
    setupCheck($state(SetupChecks::mediaDisk()) === 'failing', 'media: a local disk in production is failing');
    $config->set('media-library.disk_name', 'r2');
    setupCheck($state(SetupChecks::mediaDisk()) === 'ok' && $state(SetupChecks::bucket()) === 'ok', 'media: the resolved bucket disk (media-library.disk_name) is ok, and answers');
    $config->set('media-library.disk_name', 'down');
    setupCheck($state(SetupChecks::bucket()) === 'failing', 'media: a bucket that can\'t be reached is failing');
    $config->set('media-library.disk_name', null);
    $prod(false);

    $config->set('kit.tenancy.rls.enabled', false);
    setupCheck($state(SetupChecks::rlsEnabled()) === 'missing' && SetupChecks::rlsRole() === null && SetupChecks::rlsUnprotected() === null, 'RLS off: missing, with no role or table rows');
    $config->set('kit.tenancy.rls.enabled', true);
    setupCheck($state(SetupChecks::rlsEnabled()) === 'missing', 'RLS configured on SQLite is still missing: it needs PostgreSQL (the PostgreSQL states are in SetupRlsCheck)');
    $config->set('kit.tenancy.rls.enabled', false);

    // Queue.
    // The kit's own queue rules first (standards off); its report's rows are checked after them.
    $config->set('kit.setup.queue.standards', false);
    $config->set('queue', ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync'], 'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90], 'sqs' => ['driver' => 'sqs', 'visibility_timeout' => 60]], 'failed' => ['driver' => 'database-uuids', 'table' => 'failed_jobs']]);
    $prod(true);
    $row = SetupChecks::queueConnection();
    setupCheck($row->state === 'info' && str_contains($row->detail, 'inline') && str_contains($row->detail, 'Sync queue (sync), profile production'), 'queue: sync in production is info, saying jobs run inline, with the connection and profile');
    setupCheck(SetupChecks::queueTimeout() === null && SetupChecks::queueFailed() === null && SetupChecks::queueLastFinished() === null && SetupChecks::queuePruneFailed() === null, 'queue: sync has no timeout, failed, last job or prune rows');
    $setEnv('APP_PROFILE', 'iterating');
    setupCheck(str_contains(SetupChecks::queueConnection()->detail, 'profile iterating'), 'queue: APP_PROFILE names the profile');
    $setEnv('APP_PROFILE', 'production');
    $prod(false);
    $config->set('queue.default', 'database');
    setupCheck($state(SetupChecks::queueConnection()) === 'ok' && str_contains(SetupChecks::queueConnection()->detail, 'Database queue'), 'queue: database is ok');
    foreach ([[89, false, 'ok'], [90, false, 'failing'], [91, false, 'failing'], [85, true, 'ok'], [86, true, 'failing']] as [$timeout, $flex, $want]) {
        $config->set('kit.setup.queue.worker_timeout', $timeout);
        $config->set('kit.setup.queue.flex', $flex);
        $row = SetupChecks::queueTimeout();
        setupCheck($row->state === $want && ($want === 'ok' || $row->link['url'] === SetupChecks::QUEUE_DOCS), 'queue timeout '.$timeout.'s against retry_after 90'.($flex ? ' on Flex' : '').': '.$want);
    }
    $config->set('kit.setup.queue.flex', false);
    $config->set('queue.default', 'sqs');
    $config->set('kit.setup.queue.worker_timeout', 60);
    setupCheck($state(SetupChecks::queueTimeout()) === 'failing' && str_contains(SetupChecks::queueTimeout()->detail, 'visibility timeout 60s'), 'queue timeout at the visibility timeout: failing');
    $config->set('kit.setup.queue.worker_timeout', 59);
    setupCheck($state(SetupChecks::queueTimeout()) === 'ok', 'queue timeout below the visibility timeout: ok');
    $config->set('queue.default', 'database');

    setupCheck($state(SetupChecks::queueFailed()) === 'missing', 'failed jobs: no failed_jobs table is missing');
    Schema::create('failed_jobs', function (Blueprint $t) {
        $t->id();
        $t->string('uuid')->unique();
        $t->text('connection');
        $t->text('queue');
        $t->longText('payload');
        $t->longText('exception');
        $t->timestamp('failed_at')->useCurrent();
    });
    $fail = function (int $n, $at = null): void {
        for ($i = 0; $i < $n; $i++) {
            DB::table('failed_jobs')->insert(['uuid' => bin2hex(random_bytes(8)), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => $at ?? now()]);
        }
    };
    $fail(40, now()->subHours(25));
    foreach ([0 => 'ok', 1 => 'warning', 25 => 'warning', 26 => 'failing'] as $n => $want) {
        DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->delete();
        $fail($n);
        $row = SetupChecks::queueFailed();
        setupCheck($row->state === $want && str_contains($row->detail, $n.' failed') && ($want === 'ok' || $row->link !== null), 'failed jobs: '.$n.' in the last 24 hours is '.$want.' (older ones not counted)');
    }
    DB::table('failed_jobs')->delete();
    $config->set('kit.setup.queue.failed_count', fn () => 2);
    $config->set('kit.setup.queue.failed_url', 'https://cloud.example.test/failed');
    $row = SetupChecks::queueFailed();
    setupCheck($row->state === 'warning' && str_contains($row->detail, '2 failed') && $row->link['url'] === 'https://cloud.example.test/failed', 'failed jobs: Cloud\'s store is added, and the row links to the failed-jobs list');
    $config->set('kit.setup.queue.failed_count', null);

    Schema::create('jobs', function (Blueprint $t) {
        $t->id();
        $t->string('queue');
        $t->longText('payload');
        $t->unsignedTinyInteger('attempts');
        $t->unsignedInteger('reserved_at')->nullable();
        $t->unsignedInteger('available_at');
        $t->unsignedInteger('created_at');
    });
    Cache::forget(SetupChecks::LAST_FINISHED);
    setupCheck($state(SetupChecks::queueLastFinished()) === 'ok', 'last job: none waiting and none finished is ok');
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()]);
    setupCheck($state(SetupChecks::queueLastFinished()) === 'warning', 'last job: jobs waiting and none ever finished is a warning');
    $app['events']->dispatch(new Illuminate\Queue\Events\JobProcessed('database', new class
    {
    }));
    setupCheck(Cache::get(SetupChecks::LAST_FINISHED) === now()->getTimestamp() && $state(SetupChecks::queueLastFinished()) === 'ok', 'last job: a job finishing records its time; one just now is ok');
    Cache::forever(SetupChecks::LAST_FINISHED, now()->subMinutes(14)->getTimestamp());
    setupCheck($state(SetupChecks::queueLastFinished()) === 'ok', 'last job: 14 minutes ago with jobs waiting is ok');
    Cache::forever(SetupChecks::LAST_FINISHED, now()->subMinutes(16)->getTimestamp());
    setupCheck($state(SetupChecks::queueLastFinished()) === 'warning' && SetupChecks::queueLastFinished()->link['url'] === SetupChecks::QUEUE_DOCS, 'last job: a stale queue (16 minutes, jobs waiting) is a warning');
    DB::table('jobs')->delete();
    setupCheck($state(SetupChecks::queueLastFinished()) === 'ok', 'last job: stale but nothing waiting is ok');

    $schedule = new Schedule;
    $app->instance(Schedule::class, $schedule);
    setupCheck($state(SetupChecks::queuePruneFailed()) === 'failing' && SetupChecks::queuePruneFailed()->link['url'] === SetupChecks::QUEUE_DOCS, 'prune-failed missing from the schedule: failing, linking to Queues');
    $schedule->command('queue:prune-failed --hours=168')->daily();
    setupCheck($state(SetupChecks::queuePruneFailed()) === 'ok', 'prune-failed scheduled: ok');

    // With tey/standards (v0.14 queueReport, the same method as preflight's queue.config): the real report answers every stable id, and the rows map it.
    $config->set('kit.setup.queue.standards', true);
    $report = SetupChecks::standardsQueue();
    setupCheck(is_array($report) && array_diff(['queue.worker-command', 'queue.retry-after', 'queue.cloud-tier', 'queue.job-timeouts', 'queue.job-tries', 'queue.prune-failed'], array_keys($report)) === [], 'standards: its queue report answers every stable check id for a real queue ('.json_encode(array_keys($report ?? [])).')');
    $worker = $report['queue.worker-command']['status'] ?? null;
    setupCheck($worker === 'fail' && $state(SetupChecks::queueWorker()) === 'failing' && str_contains(SetupChecks::queueWorker()->detail, 'queue:work'), 'standards: no committed worker command fails the Worker and jobs row with standards\' message');
    $prune = $report['queue.prune-failed']['status'] ?? null;
    setupCheck($prune === 'skipped' || $state(SetupChecks::queuePruneFailed()) === ['pass' => 'ok', 'warn' => 'warning', 'fail' => 'failing'][$prune], 'standards: the prune row follows its queue.prune-failed check ('.$prune.')');
    $setEnv('APP_ENV', 'local');
    $prod(false);
    setupCheck(! in_array($state(SetupChecks::queueTimeout()), ['failing'], true) || ($report['queue.retry-after']['status'] ?? '') === 'fail', 'standards: locally the Cloud-tier check never fails the Timeout row');
    $config->set('queue.default', 'sync');
    setupCheck(SetupChecks::queueWorker() === null, 'standards: a sync queue has no Worker and jobs row');
    $config->set('queue.default', 'database');
    $config->set('kit.setup.queue.standards', false);
    setupCheck(SetupChecks::standardsQueue() === null && SetupChecks::queueWorker() === null, 'standards off (or not installed): no report, no Worker row, the kit\'s own rules');

    setupCheck(SetupChecks::telescope() === null, 'Telescope: left out when it isn\'t installed');

    // Registered settings: a check's failure, then an environment override.
    $values->put('billing.vendor', 'bad');
    setupCheck($state(SetupChecks::registered()) === 'failing' && str_contains(SetupChecks::registered()->detail, 'billing.vendor: not a vendor'), 'registered settings: a failing check is failing, naming it');
    $values->forget('billing.vendor');
    $setEnv('KIT_CHECK_VENDOR', 'paddle');
    $row = SetupChecks::registered();
    setupCheck($row->state === 'overridden' && str_contains($row->detail, 'KIT_CHECK_VENDOR') && ! str_contains($row->detail, 'paddle'), 'registered settings: an env value is overridden, naming the variable and never the value');
    $setEnv('KIT_CHECK_VENDOR', null);
    setupCheck($state(SetupChecks::registered()) === 'ok' && ! str_contains(json_encode(SetupChecks::registered()), 'tok_plain'), 'registered settings: all fine is ok, and no value is shown');

    // An app's own row, and one that throws.
    $registry->setupRow('Billing', 'billing.webhook', fn () => SetupRow::of('Webhook', 'missing', 'No webhook yet.', 'Add it.', SetupRow::link('Billing', 'settings/billing')), 'workspace');
    $registry->setupRow('Billing', 'billing.broken', function () { DB::table('failed_jobs')->insert(['uuid' => 'setup-broken', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]); throw new RuntimeException('boom'); }, 'workspace');

    // The page: owners and admins; the app's rows for an operator only.
    [$status] = $call('member', 'GET', '');
    setupCheck($status === 403, 'a member is refused Setup');
    [$status] = $call('outsider', 'GET', '');
    setupCheck($status === 404 || $status === 403, 'an outsider is refused Setup');
    [$status, $body] = $call('admin', 'GET', '');
    $keys = array_column($body['rows'], 'key');
    setupCheck($status === 200 && $body['app_rows'] === false && $keys === ['mail.domain', 'billing.webhook', 'billing.broken'], 'an admin who isn\'t an operator sees the workspace\'s rows only');
    $rows = array_column($body['rows'], null, 'key');
    setupCheck(str_ends_with($rows['billing.webhook']['link']['url'], '/settings/billing') && $rows['billing.webhook']['group'] === 'Billing', 'an app\'s row is placed in its group, with its relative link made the workspace\'s');
    setupCheck($rows['billing.broken']['state'] === 'failing' && str_contains($rows['billing.broken']['detail'], 'RuntimeException'), 'a row whose check throws is shown failing, not hidden');
    setupCheck(DB::table('failed_jobs')->where('uuid', 'setup-broken')->doesntExist(), 'a row whose check throws runs in its own transaction, rolled back (on Postgres a failed query no longer aborts the rows after it)');
    DB::table('operators')->insert(['actor_id' => $people['owner']->id]);
    [$status, $body] = $call('owner', 'GET', '');
    $keys = array_column($body['rows'], 'key');
    $groups = array_values(array_unique(array_column($body['rows'], 'group')));
    setupCheck($status === 200 && $body['app_rows'] === true && $groups === ['Mail', 'Media', 'RLS', 'Queue', 'Settings', 'By hand', 'Billing'], 'an operator sees every group, in registration order (got '.implode(', ', $groups).')');
    setupCheck(array_intersect(['mail.mailer', 'mail.allow-only', 'media.disk', 'rls.enabled', 'queue.connection', 'queue.timeout', 'queue.failed', 'queue.last-finished', 'queue.prune-failed', 'manual.dns', 'manual.mail-dns', 'manual.resend', 'manual.bucket', 'manual.cloud-plan'], $keys) === ['mail.mailer', 'mail.allow-only', 'media.disk', 'rls.enabled', 'queue.connection', 'queue.timeout', 'queue.failed', 'queue.last-finished', 'queue.prune-failed', 'manual.dns', 'manual.mail-dns', 'manual.resend', 'manual.bucket', 'manual.cloud-plan'], 'the kit\'s rows are all there');
    $manual = array_filter($body['rows'], fn (array $r): bool => $r['group'] === 'By hand');
    setupCheck(count($manual) === 5 && array_filter($manual, fn (array $r): bool => $r['state'] !== 'manual' || $r['link'] === null) === [], 'each by-hand row is manual, with its link');
    $model = (new SetupViewModel('Setup', [], true, new Tey\Kit\Features\Core\Data\Action('Send a test mail', '/x', 'post')))->toArray();
    setupCheck(array_keys(array_intersect_key($model, array_flip(['title', 'rows', 'appRows', 'testMail']))) !== [] && isset($model['testMail']), 'the page\'s view model carries rows, appRows and testMail');

    // The test mail goes to the viewer, through the app's mailer.
    $config->set('mail.default', 'array');
    $config->set('mail.mailers', ['array' => ['transport' => 'array']]);
    $app->register(Illuminate\Mail\MailServiceProvider::class);
    $app->register(Illuminate\Notifications\NotificationServiceProvider::class);
    file_put_contents($base.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    [$status, $body] = $call('owner', 'POST', '/test-mail');
    $sent = $app['mail.manager']->mailer('array')->getSymfonyTransport()->messages();
    setupCheck($status === 200 && $body['sent_to'] === 'owner@example.test' && count($sent) === 1 && $sent[0]->getEnvelope()->getRecipients()[0]->getAddress() === 'owner@example.test', 'Send a test mail sends one to the viewer (array mailer)');
    [$status] = $call('member', 'POST', '/test-mail');
    setupCheck($status === 403 && count($app['mail.manager']->mailer('array')->getSymfonyTransport()->messages()) === 1, 'a member can\'t send one');

    // MCP: the same rows as the page, through the consent gate.
    $gate = new class
    {
        public ?Principal $as = null;

        public function admit(string $ability): array
        {
            if ($ability !== 'settings.write' || $this->as === null) {
                throw new RuntimeException('connection_denied');
            }

            return [$this->as, (int) Tey\Kit\Features\Workspaces\Workspaces::required()->getKey()];
        }

        public function refusal(Throwable $e): array
        {
            return ['error' => $e->getMessage()];
        }
    };
    $app->instance(Tey\Kit\Features\Mcp\ConsentGate::class, $gate);
    $tool = new ReadSetup;
    setupCheck($tool->name() === 'workspace_setup_read', 'the MCP tool is workspace_setup_read');
    $gate->as = new Principal($people['owner']->id);
    $result = $workspace->execute(fn () => $tool->handle(new Laravel\Mcp\Request([])));
    $structured = $result instanceof Laravel\Mcp\ResponseFactory ? $result->getStructuredContent() : null;
    $page = $workspace->execute(fn () => app(Setup::class)->read($gate->as));
    setupCheck(is_array($structured) && array_column($structured['rows'], 'state', 'key') === array_column(array_map(fn ($r) => $r->toArray(), $page['rows']), 'state', 'key') && $structured['app_rows'] === true, 'the MCP read returns the page\'s rows and states');
    $result = $workspace->execute(fn () => $tool->handle(new Laravel\Mcp\Request(['x' => 1])));
    setupCheck($result instanceof Laravel\Mcp\Response && $result->isError() && str_contains((string) $result->content(), 'unknown_arguments'), 'the MCP read refuses arguments');
    $gate->as = new Principal($people['member']->id);
    $result = $workspace->execute(fn () => $tool->handle(new Laravel\Mcp\Request([])));
    setupCheck($result instanceof Laravel\Mcp\Response && $result->isError() && str_contains((string) $result->content(), 'permission_denied'), 'the MCP read refuses a member');


    // A fresh local app (Herd): local mail and disk, the database queue, RLS off, no Cloud, bucket or Resend keys. Nothing missing or failing.
    $gate->as = new Principal($people['owner']->id);
    $fresh = function (string $env) use ($app, $config, $setEnv, $base): void {
        $app->instance('env', $env);
        $setEnv('APP_PROFILE', null);
        foreach (['RESEND_API_KEY', 'AWS_BUCKET', 'AWS_ACCESS_KEY_ID', 'LARAVEL_CLOUD', 'CLOUDFLARE_API_TOKEN', 'MAIL_ALLOW_ONLY'] as $name) {
            $setEnv($name, null);
        }
        $config->set('mail.default', 'log');
        $config->set('mail.mailers', ['log' => ['transport' => 'log']]);
        $config->set('mail.allow_only', null);
        $config->set('filesystems', ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app']]]);
        $config->set('media-library.disk_name', null);
        $config->set('kit.tenancy.rls.enabled', false);
        $config->set('queue.default', 'database');
        $config->set('kit.setup.queue', ['worker_timeout' => 60, 'flex' => false, 'failed_count' => null, 'failed_url' => null]);
        $app->instance(Schedule::class, new Schedule);
        DB::table('failed_jobs')->delete();
        DB::table('jobs')->delete();
    };
    $states = function () use ($workspace, $gate, $tool): array {
        $page = array_column(array_map(fn ($r) => $r->toArray(), $workspace->execute(fn () => app(Setup::class)->read($gate->as))['rows']), 'state', 'key');
        $mcp = array_column($workspace->execute(fn () => $tool->handle(new Laravel\Mcp\Request([])))->getStructuredContent()['rows'], 'state', 'key');
        unset($page['billing.webhook'], $page['billing.broken'], $mcp['billing.webhook'], $mcp['billing.broken']);

        return [$page, $mcp];
    };
    $fresh('local');
    [$page, $mcp] = $states();
    $red = array_filter($page, fn (string $st): bool => in_array($st, ['missing', 'failing'], true));
    setupCheck($red === [], 'a fresh local app has no row missing or failing (got '.json_encode($red).')');
    foreach (['media.disk', 'mail.allow-only', 'mail.domain', 'queue.prune-failed', 'rls.enabled', 'manual.dns', 'manual.mail-dns', 'manual.resend', 'manual.bucket', 'manual.cloud-plan'] as $key) {
        setupCheck(($page[$key] ?? null) === 'na', 'local: '.$key.' is not applicable');
    }
    setupCheck($mcp === $page, 'local: the MCP read reports the same states');
    $config->set('kit.setup.queue.flex', true);
    $config->set('kit.setup.queue.worker_timeout', 88);
    $config->set('kit.setup.queue.failed_count', fn () => 30);
    [$page] = $states();
    setupCheck($page['queue.timeout'] === 'ok' && $page['queue.failed'] === 'ok', 'local: the Flex limit and Cloud\'s failed-job store don\'t apply');
    $setEnv('APP_PROFILE', 'iterating');
    $app->instance('env', 'production');
    [$page] = $states();
    setupCheck($page['media.disk'] === 'na' && $page['manual.dns'] === 'na', 'production with the iterating profile is local too');

    // The same config deployed: the production rows fail or warn.
    $fresh('production');
    [$page, $mcp] = $states();
    $want = ['mail.mailer' => 'failing', 'media.disk' => 'failing', 'rls.enabled' => 'missing', 'queue.prune-failed' => 'failing', 'mail.allow-only' => 'ok', 'mail.domain' => 'ok', 'manual.dns' => 'manual', 'manual.mail-dns' => 'manual', 'manual.resend' => 'manual', 'manual.bucket' => 'manual', 'manual.cloud-plan' => 'manual'];
    setupCheck(array_intersect_key($page, $want) == $want, 'production: the same config fails its mailer, disk and prune, misses RLS, and lists the steps by hand (got '.json_encode(array_intersect_key($page, $want)).')');
    $config->set('mail.allow_only', 'a@example.test');
    $config->set('kit.setup.queue.flex', true);
    $config->set('kit.setup.queue.worker_timeout', 86);
    $config->set('kit.setup.queue.failed_count', fn () => 1);
    [$page, $mcp] = $states();
    setupCheck($page['mail.allow-only'] === 'held' && $page['queue.timeout'] === 'failing' && $page['queue.failed'] === 'warning', 'production: MAIL_ALLOW_ONLY is held, the Flex limit fails, Cloud\'s failed jobs warn');
    setupCheck($mcp === $page, 'production: the MCP read reports the same states');

    echo "Setup and the settings registry on SQLite memory, synthetic users and config, a fake bucket and the array mailer. Not covered: a real provider, bucket or queue worker, a consumer app, and the browser.\n";
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
