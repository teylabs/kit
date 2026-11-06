<?php

// Queued MCP runs (kit#673): a QueuedCapabilityTool answers at once with a run token; RunQueuedTool runs call() in the run's workspace after re-checking the live connection; runs_read
// reads the run back only for the same person, connection and workspace, with progress, result, failure code and expiry. Synthetic SQLite memory, real Passport and Concierge tables, a null queue the check drains itself.
// off capability to their codes, and returns call()'s array as structured content. Real Passport, Concierge and kit tables, SQLite memory, synthetic users.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Laravel\Mcp\ResponseFactory;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Passport\Token;
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
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\Mcp\Events\ToolCalled;
use Tey\Kit\Features\Mcp\Events\ToolRunFinished;
use Tey\Kit\Features\Mcp\Runs\QueuedCapabilityTool;
use Tey\Kit\Features\Mcp\Runs\ReadRun;
use Tey\Kit\Features\Mcp\Runs\RunQueuedTool;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;
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

// A slow report: it reports two steps, lets the check look in mid-run, and fails on purpose for some ids (500: an uncoded failure carrying a secret; 409: a host code).
#[Capability('workspace.settings')]
class SlowReport extends QueuedCapabilityTool
{
    public const ABILITY = 'settings.read';

    protected string $description = 'Build a slow report.';

    /** @var (Closure(): void)|null what the check does between the two steps */
    public static ?Closure $midRun = null;

    public static ?int $ranIn = null;

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required(), 'intent_key' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1'], 'intent_key' => ['sometimes', 'string', 'max:120']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        self::$ranIn = (int) Workspaces::current()?->getKey();
        $this->progress('Freezing report', 1, 2);
        if (self::$midRun !== null) {
            (self::$midRun)();
        }
        if ($validated['id'] === 500) {
            throw new LogicException('secret detail: db password hunter2');
        }
        if ($validated['id'] === 409) {
            throw new RuntimeException('stale_write');
        }
        $this->progress('Rendering PDF', 2, 2);

        return ['id' => $validated['id'], 'actor' => $principal->actorId, 'pages' => 3];
    }
}

try {
    $base = sys_get_temp_dir().'/kit-mcp-queued-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
        'queue' => ['default' => 'null', 'connections' => ['null' => ['driver' => 'null']]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class)->capabilityTools([SlowReport::class])->mcpRefusalCodes(['stale_write']);
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

    $sync->run(new ArrayInput([]), new NullOutput);

    $owner = $people['owner'];
    $client = Client::query()->forceCreate(['owner_type' => null, 'owner_id' => null, 'name' => 'Assistant', 'secret' => null, 'provider' => 'users', 'redirect_uris' => ['https://assistant.test/callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false]);
    // Each person's live connection to the workspace, as the consent flow leaves it. The token also carries the settings scopes, so the checks hold whether the gate
    // admits on the one MCP grant or on the tool's own scope; the member's role still lacks settings.write.
    $connections = [];
    foreach (['owner', 'member'] as $who) {
        $token = Token::query()->forceCreate(['id' => bin2hex(random_bytes(40)), 'user_id' => $people[$who]->id, 'client_id' => $client->id, 'name' => null, 'scopes' => ['mcp:use', 'mcp:act'], 'revoked' => false, 'expires_at' => now()->addHour()]);
        $connections[$who] = [$token, DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $people[$who]->id, 'oauth_token_id' => $token->id, 'scopes' => json_encode(['mcp:act']), 'intent_key' => bin2hex(random_bytes(4)), 'fingerprint' => 'x', 'expires_at' => $token->expires_at])];
    }
    $connection = $connections['owner'][1];
    // Runs $then on a tool as the server would once BindMcpConnection has bound the person's connection: the token's user, its principal, in the workspace's context.
    $as = function (string $who, string $class, callable $then, ?int $bound = null) use ($app, $people, $connections, $workspace): mixed {
        return $workspace->execute(function () use ($app, $who, $class, $then, $bound, $people, $connections): mixed {
            [$token, $connection] = $connections[$who];
            $request = Request::create('https://kit.test/mcp', 'POST');
            $user = HttpUser::query()->findOrFail($people[$who]->id)->withAccessToken(new AccessToken(['oauth_access_token_id' => $token->id, 'oauth_client_id' => $token->client_id, 'oauth_user_id' => (string) $people[$who]->id, 'oauth_scopes' => $token->scopes]));
            $request->attributes->set('mcp.principal', new Principal((int) $people[$who]->id, $bound ?? $connection));
            // Binding the request resets its user resolver to the auth manager, so the token's user is set after.
            $app->instance('request', $request);
            $request->setUserResolver(fn () => $user);

            return $then($app->make($class));
        });
    };
    // A call: ['ok', structured content] or ['error', code]; a refusal's structured detail, when it has one, is left in $detail.
    $detail = null;
    $call = function (array $arguments, ?int $bound = null, string $who = 'owner', string $class = SlowReport::class) use ($as, &$detail): array {
        return $as($who, $class, function (CapabilityTool $tool) use ($arguments, &$detail): array {
            $response = $tool->handle(new Laravel\Mcp\Request($arguments));
            $detail = null;
            if ($response instanceof ResponseFactory && $response->responses()->first()?->isError()) {
                $detail = $response->getStructuredContent();

                return ['error', (string) $response->responses()->first()->content()];
            }

            return $response instanceof ResponseFactory ? ['ok', $response->getStructuredContent()] : ['error', (string) $response->content()];
        }, $bound);
    };
    // Every ToolCalled the calls dispatch.
    $fired = [];
    $app['events']->listen(ToolCalled::class, function (ToolCalled $event) use (&$fired): void {
        $fired[] = $event;
    });

    // Reads a run as $who would through runs_read: ['ok', content] or ['error', code].
    $read = function (array $arguments, string $who = 'owner', ?int $bound = null) use ($as): array {
        return $as($who, ReadRun::class, function (ReadRun $tool) use ($arguments): array {
            $response = $tool->handle(new Laravel\Mcp\Request($arguments));

            return $response instanceof ResponseFactory ? ['ok', $response->getStructuredContent()] : ['error', (string) $response->content()];
        }, $bound);
    };
    $finished = [];
    $app['events']->listen(ToolRunFinished::class, function ($event) use (&$finished): void {
        $finished[] = $event;
    });
    // Runs a queued run's job as a worker would: outside any workspace.
    $work = fn (string $run) => $app->call([new RunQueuedTool($run), 'handle']);

    httpCheck($as('owner', ReadRun::class, fn (ReadRun $tool): bool => $tool->shouldRegister()), 'runs_read is listed while a queued tool is registered');
    $config->set('kit.mcp.tools', ['workspace_settings' => false]);
    httpCheck(! $as('owner', ReadRun::class, fn (ReadRun $tool): bool => $tool->shouldRegister()) && $read(['run' => 'run_'.str_repeat('0', 26)]) === ['error', 'capability_unavailable'], 'with no queued tool served, runs_read is neither listed nor callable');
    $config->set('kit.mcp.tools', []);

    // Queue: the call answers at once and runs nothing.
    [$state, $queued] = $call(['id' => 7]);
    $run = $queued['run'] ?? '';
    httpCheck($state === 'ok' && $queued['status'] === 'queued' && preg_match('/^run_[0-9a-z]{26}$/', $run) === 1 && $queued['poll_after_seconds'] === 5 && is_string($queued['expires_at']), 'a queued tool answers at once with {status: queued, run, poll_after_seconds, expires_at}');
    httpCheck($fired === [] && SlowReport::$ranIn === null, 'queueing runs no call() and dispatches no ToolCalled');
    httpCheck($read(['run' => $run]) === ['ok', ['run' => $run, 'status' => 'queued', 'poll_after_seconds' => 5]], 'runs_read reads a queued run as queued');

    // Scoping: someone else, another connection of the same person, a made-up token and bad arguments.
    httpCheck($read(['run' => $run], 'member') === ['error', 'not_found'], 'another person reading the run gets not_found');
    $second = DB::table('connections')->insertGetId(['workspace_id' => $workspace->id, 'actor_id' => $owner->id, 'oauth_token_id' => $connections['owner'][0]->id, 'scopes' => json_encode(['mcp:act']), 'intent_key' => 'second', 'fingerprint' => 'x', 'expires_at' => now()->addHour()]);
    httpCheck($read(['run' => $run], 'owner', $second) === ['error', 'not_found'], 'the same person on another connection gets not_found');
    httpCheck($read(['run' => 'run_'.str_repeat('a', 26)]) === ['error', 'not_found'] && $read(['run' => '../etc']) === ['error', 'not_found'], 'a made-up or malformed token is not_found');
    httpCheck($read(['run' => $run, 'more' => 1]) === ['error', 'unknown_arguments'], 'an argument besides run is unknown_arguments');
    $other = Workspace::create(['name' => 'Other', 'slug' => 'other']);
    DB::table('kit_mcp_runs')->where('id', $run)->update(['workspace_id' => $other->id]);
    httpCheck($read(['run' => $run]) === ['error', 'not_found'], 'a run is read only in the workspace it was queued in');
    DB::table('kit_mcp_runs')->where('id', $run)->update(['workspace_id' => $workspace->id]);

    // Run it: progress is readable mid-run, the result matches, the events fire once, and the workspace is the run's.
    $midRun = null;
    SlowReport::$midRun = function () use ($read, $run, &$midRun): void {
        $midRun = $read(['run' => $run]);
    };
    $work($run);
    SlowReport::$midRun = null;
    httpCheck($midRun === ['ok', ['run' => $run, 'status' => 'running', 'step' => 'Freezing report', 'done' => 1, 'total' => 2, 'poll_after_seconds' => 5]], 'mid-run, runs_read shows the step and done/total the tool reported');
    httpCheck(SlowReport::$ranIn === (int) $workspace->id && Workspaces::current() === null, 'the job runs call() in the run\'s workspace and leaves no workspace current after');
    httpCheck($read(['run' => $run]) === ['ok', ['run' => $run, 'status' => 'done', 'result' => ['id' => 7, 'actor' => (int) $owner->id, 'pages' => 3]]], 'once done, runs_read returns call()\'s result');
    httpCheck(count($fired) === 1 && $fired[0]->tool === 'workspace_settings' && $fired[0]->arguments === ['id' => 7] && count($finished) === 1 && $finished[0]->status === 'done' && $finished[0]->error === null, 'ToolCalled fires once when call() runs, then ToolRunFinished(done)');
    $work($run);
    httpCheck(count($fired) === 1 && count($finished) === 1, 'a second worker on the same run does nothing');
    DB::table('kit_mcp_runs')->where('id', $run)->update(['step' => 'Late']);
    $late = new class extends SlowReport
    {
        public function late(string $run): void
        {
            $this->progress('Too late', 9, 9);
        }
    };
    $late->late($run);
    httpCheck(DB::table('kit_mcp_runs')->where('id', $run)->value('step') === 'Late', 'progress outside a running run writes nothing');

    // Replay: the same intent_key and arguments get the same run, not a second job.
    [, $first] = $call(['id' => 8, 'intent_key' => 'report-8']);
    [, $again] = $call(['id' => 8, 'intent_key' => 'report-8']);
    httpCheck($first['run'] === $again['run'] && DB::table('kit_mcp_runs')->where('intent_key', 'report-8')->count() === 1, 'a retry with the same intent_key gets the same run token and queues nothing new');
    httpCheck($call(['id' => 9, 'intent_key' => 'report-8']) === ['error', 'intent_conflict'], 'the key reused for other arguments is intent_conflict');

    // Failures: a code only, never the message; tenant context restored after a throw.
    $finished = [];
    [, $failing] = $call(['id' => 500]);
    $work($failing['run']);
    $row = (array) DB::table('kit_mcp_runs')->where('id', $failing['run'])->first();
    httpCheck($read(['run' => $failing['run']]) === ['ok', ['run' => $failing['run'], 'status' => 'failed', 'error' => 'operation_failed']] && ! str_contains(json_encode($row), 'hunter2'), 'an uncoded failure is failed with operation_failed, and its message is stored nowhere');
    httpCheck(Workspaces::current() === null && $finished[0]->status === 'failed' && $finished[0]->error === 'operation_failed', 'after a failed run no workspace is current, and ToolRunFinished(failed) carries the code');
    [, $coded] = $call(['id' => 409]);
    $work($coded['run']);
    httpCheck($read(['run' => $coded['run']])[1]['error'] === 'stale_write', 'a host-registered code passes through as the run\'s error');

    // Consent: a run queued before its connection is revoked or narrowed is refused at run time and at read time.
    [, $revokedRun] = $call(['id' => 11]);
    [, $narrowRun] = $call(['id' => 12]);
    DB::table('connections')->where('id', $connection)->update(['scopes' => json_encode(['notes.read'])]);
    $work($narrowRun['run']);
    httpCheck(DB::table('kit_mcp_runs')->where('id', $narrowRun['run'])->value('error') === 'connection_denied', 'a connection narrowed after queueing fails the run with connection_denied');
    DB::table('connections')->where('id', $connection)->update(['scopes' => json_encode(['mcp:act']), 'revoked_at' => now()]);
    $work($revokedRun['run']);
    httpCheck(DB::table('kit_mcp_runs')->where('id', $revokedRun['run'])->value('error') === 'connection_denied', 'a connection revoked after queueing fails the run with connection_denied');
    httpCheck($read(['run' => $revokedRun['run']]) === ['error', 'connection_denied'], 'and reading it on the revoked connection is refused');
    DB::table('connections')->where('id', $connection)->update(['revoked_at' => null]);

    // Expiry: past the TTL a run reads as expired and its job does nothing; the next queue on the connection deletes it.
    [, $stale] = $call(['id' => 13]);
    // The connection's token outlives the run here, so only the run's own expiry is under test.
    DB::table('oauth_access_tokens')->update(['expires_at' => now()->addDays(3)]);
    DB::table('connections')->update(['expires_at' => now()->addDays(3)]);
    Carbon::setTestNow(now()->addSeconds(86401));
    try {
        $work($stale['run']);
        httpCheck($read(['run' => $stale['run']]) === ['ok', ['run' => $stale['run'], 'status' => 'expired']] && DB::table('kit_mcp_runs')->where('id', $stale['run'])->value('status') === 'queued', 'past kit.mcp.run_ttl the run reads expired and its job does not run');
        $call(['id' => 14]);
        httpCheck(DB::table('kit_mcp_runs')->where('id', $stale['run'])->doesntExist(), 'queueing the next run on the connection deletes expired ones');
    } finally {
        Carbon::setTestNow();
    }

    echo "Queued MCP run checks on SQLite memory with real Passport tables, Concierge and spatie teams, through the real ConsentGate; jobs run by the check on a null queue (no worker, no MCP transport, no protocol-level MCP tasks).\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
