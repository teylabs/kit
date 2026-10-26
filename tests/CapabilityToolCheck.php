<?php

// A CapabilityTool's main path through the kit's ConsentGate: admitted with a live connection, it refuses unknown and invalid arguments, maps a missing record and an
// off capability to their codes, and returns call()'s array as structured content. Real Passport, Concierge and kit tables, SQLite memory, synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Token;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;

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

// A tool over a stand-in operation: it echoes who it ran as, and a note id of 404 is a record that does not exist.
#[Capability('workspace.settings')]
class ReadNote extends CapabilityTool
{
    public const ABILITY = 'settings.read';

    protected string $description = 'Read one note by its id.';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        if ($validated['id'] === 404) {
            throw (new Illuminate\Database\Eloquent\ModelNotFoundException)->setModel('Note', [404]);
        }
        // Coded refusals: the kit's own workspace_not_found (plain, and as a ModelNotFoundException subclass carrying the code), a registered host code and an unregistered one.
        $code = [405 => new RuntimeException('workspace_not_found'), 406 => new Illuminate\Database\Eloquent\ModelNotFoundException('workspace_not_found'), 409 => new RuntimeException('stale_write'), 410 => new RuntimeException('report_blocked')][$validated['id']] ?? null;
        if ($code !== null) {
            throw $code;
        }

        return ['id' => $validated['id'], 'actor' => $principal->actorId, 'connection' => $principal->connectionId, 'workspace' => $workspace];
    }
}

// The web form's request, whose rules a tool reuses.
class StoreNoteRequest extends Illuminate\Foundation\Http\FormRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:20']];
    }
}

// A tool shaped the other way: the module switch on the attribute, the permission in ABILITY, its own name, and the web form's rules.
#[Capability('workspace.settings')]
class WriteNote extends CapabilityTool
{
    public const ABILITY = 'settings.write';

    protected string $name = 'notes_write';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['title' => $schema->string()->required()];
    }

    protected function rules(): array
    {
        return (new StoreNoteRequest)->rules();
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['title' => $validated['title'], 'actor' => $principal->actorId];
    }
}

// A destructive tool: the gate asks for confirmation in the chat before it runs.
#[Capability('workspace.settings', destructive: true)]
class DeleteNote extends CapabilityTool
{
    public const ABILITY = 'settings.write';

    protected string $name = 'notes_delete';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function summary(array $validated): string
    {
        return 'Delete note '.$validated['id'];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['deleted' => $validated['id']];
    }
}

// A create/copy tool that opts into intent replay by accepting `intent_key`; it counts its runs.
#[Capability('workspace.settings')]
class CopyNote extends CapabilityTool
{
    public const ABILITY = 'settings.read';

    public static int $runs = 0;

    protected string $name = 'notes_copy';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required(), 'intent_key' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1'], 'intent_key' => ['sometimes', 'string', 'max:120']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['copy' => ++self::$runs, 'of' => $validated['id'], 'actor' => $principal->actorId];
    }
}

// A destructive tool that also takes an intent: a retry after it ran replays, without asking again.
#[Capability('workspace.settings', destructive: true)]
class PurgeNote extends CapabilityTool
{
    public const ABILITY = 'settings.write';

    public static int $runs = 0;

    protected string $name = 'notes_purge';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required(), 'intent_key' => $schema->string(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer'], 'intent_key' => ['sometimes', 'string'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['purged' => $validated['id'], 'run' => ++self::$runs];
    }
}

// A tool that confirms only its delete mode, names its own not-found code and records the arguments call() received; the tool-level flag stays for the destructiveHint.
#[Capability('workspace.settings', destructive: true)]
class ProbeNote extends CapabilityTool
{
    public const ABILITY = 'settings.write';

    public static array $seen = [];

    protected string $name = 'notes_probe';

    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required(), 'mode' => $schema->string(), 'intent_key' => $schema->string(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer'], 'mode' => ['sometimes', 'string'], 'intent_key' => ['sometimes', 'string'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function destructive(array $validated): bool
    {
        return ($validated['mode'] ?? null) === 'delete';
    }

    protected function notFoundCode(): string
    {
        return 'note_workspace_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        self::$seen[] = $validated;
        if ($validated['id'] === 404) {
            throw (new Illuminate\Database\Eloquent\ModelNotFoundException)->setModel('Note', [404]);
        }
        if ($validated['id'] === 500) {
            throw new LogicException('boom');
        }

        return ['probed' => $validated['id']];
    }
}

// An exception handler that records what the host would log.
class RecordingHandler implements Illuminate\Contracts\Debug\ExceptionHandler
{
    public array $reported = [];

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e)
    {
        throw $e;
    }

    public function renderForConsole($output, Throwable $e): void {}
}

// A tool with nothing to be admitted under.
#[Capability('workspace.settings')]
class UnadmittedNote extends CapabilityTool
{
    public function schema(Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }

    protected function rules(): array
    {
        return ['id' => ['integer']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['ran' => true];
    }
}

// A host's hand-written tool that names its own MCP tool id in CAPABILITY, an id no feature declares, with no attribute: the kit gates it by ABILITY alone.
class HostAssignmentsTool extends Laravel\Mcp\Server\Tool
{
    use Tey\Kit\Features\Mcp\GatedByCapability;

    public const CAPABILITY = 'assignments.list';

    public const ABILITY = 'settings.read';

    protected string $description = 'List assignments.';

    public function handle(Laravel\Mcp\Request $request): Laravel\Mcp\Response
    {
        return Laravel\Mcp\Response::text('ok');
    }
}

// A tool whose ability another capability gates.
#[Capability('workspace.other')]
class MisgatedNote extends WriteNote {}

try {
    $base = sys_get_temp_dir().'/kit-capability-tool-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'mcp'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true]],
        'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]],
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
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Registry::class)->capabilityTools([ReadNote::class, WriteNote::class, DeleteNote::class, CopyNote::class, PurgeNote::class, ProbeNote::class])->mcpRefusalCodes(['stale_write']);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

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
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach ($stubs as $file) {
        (require $file)->up();
    }

    $app['router']->middlewareGroup('web', [Illuminate\Session\Middleware\StartSession::class]);
    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
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
        app(Tey\Concierge\Concierge::class)->context()->run($workspace, fn () => $people[$name]->assignRole($name), [$people[$name]]);
    }

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);

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
    $call = function (array $arguments, ?int $bound = null, string $who = 'owner', string $class = ReadNote::class) use ($as, &$detail): array {
        return $as($who, $class, function (CapabilityTool $tool) use ($arguments, &$detail): array {
            $response = $tool->handle(new Laravel\Mcp\Request($arguments));
            $detail = null;
            if ($response instanceof Laravel\Mcp\ResponseFactory && $response->responses()->first()?->isError()) {
                $detail = $response->getStructuredContent();

                return ['error', (string) $response->responses()->first()->content()];
            }

            return $response instanceof Laravel\Mcp\ResponseFactory ? ['ok', $response->getStructuredContent()] : ['error', (string) $response->content()];
        }, $bound);
    };
    // Every ToolCalled the calls dispatch.
    $fired = [];
    $app['events']->listen(Tey\Kit\Features\Mcp\Events\ToolCalled::class, function (Tey\Kit\Features\Mcp\Events\ToolCalled $event) use (&$fired): void {
        $fired[] = $event;
    });

    httpCheck($app->make(ReadNote::class)->name() === 'workspace_settings', 'the tool is named after its capability');
    httpCheck($call(['id' => 7]) === ['ok', ['id' => 7, 'actor' => (int) $owner->id, 'connection' => $connection, 'workspace' => (int) $workspace->id]], 'an admitted call returns call()\'s array as structured content, run as the connection\'s principal in the workspace');
    httpCheck($call(['id' => 7, 'extra' => true]) === ['error', 'unknown_arguments'], 'an argument rules() does not name is refused as unknown_arguments');
    httpCheck($call(['id' => 'seven']) === ['error', 'validation_failed'] && $call([]) === ['error', 'validation_failed'], 'an invalid or missing argument is refused as validation_failed');
    httpCheck($call(['id' => 404]) === ['error', 'not_found'], 'a record the operation cannot find is not_found');
    httpCheck($call(['id' => 'seven']) === ['error', 'validation_failed'] && $detail === ['error' => 'validation_failed', 'errors' => ['id' => ['The id field must be an integer.']]], 'validation_failed carries the field errors as structured detail');
    httpCheck($call(['id' => 7, 'extra' => true]) === ['error', 'unknown_arguments'] && $detail === null, 'a refusal without detail stays a bare error code');
    httpCheck($call(['id' => 405]) === ['error', 'workspace_not_found'], 'a RuntimeException(workspace_not_found) passes through, not as not_found');
    httpCheck($call(['id' => 406]) === ['error', 'workspace_not_found'], 'a coded RuntimeException subclass wins over the not_found mapping');
    httpCheck($call(['id' => 409]) === ['error', 'stale_write'], 'a domain code the host registered passes through as it is');
    httpCheck($call(['id' => 410]) === ['error', 'operation_failed'], 'an unregistered domain code becomes operation_failed');
    // The after-call event: once for a call that ran, never for a refusal.
    $fired = [];
    $call(['id' => 7]);
    httpCheck(count($fired) === 1 && $fired[0]->tool === 'workspace_settings' && $fired[0]->class === ReadNote::class && $fired[0]->principal->actorId === (int) $owner->id && $fired[0]->principal->connectionId === $connection
        && $fired[0]->workspaceId === (int) $workspace->id && $fired[0]->arguments === ['id' => 7] && $fired[0]->result['id'] === 7, 'a successful call dispatches ToolCalled once, with the tool, class, principal, workspace, validated arguments and result');
    $fired = [];
    $call(['id' => 404]);
    $call(['id' => 'seven']);
    $call(['id' => 409]);
    httpCheck($fired === [], 'a refused call dispatches no ToolCalled');
    httpCheck($call(['id' => 7], 999999) === ['error', 'connection_denied'], 'a principal with no live connection is refused as connection_denied');
    app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, false);
    httpCheck($call(['id' => 7]) === ['error', 'capability_unavailable'], 'with its capability off, a call is refused as capability_unavailable before the operation runs');
    httpCheck($workspace->execute(fn (): ?string => $app->make(ReadNote::class)->unavailable()) === 'capability_unavailable', 'and the tool is absent from the list for the same reason');
    app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, null);
    httpCheck($call(['id' => 7])[0] === 'ok', 'switched back on, the call runs again');

    // A CAPABILITY constant is never read as the capability: the host tool keeps its pre-0.4 behaviour, listed and gated by its ABILITY's capability.
    httpCheck(Capability::of(HostAssignmentsTool::class) === null, 'a CAPABILITY constant without the attribute declares no capability');
    httpCheck($as('owner', HostAssignmentsTool::class, fn ($tool): ?string => $tool->unavailable()) === null && $as('owner', HostAssignmentsTool::class, fn ($tool): bool => $tool->shouldRegister()), 'a tool whose CAPABILITY names an undeclared id is listed, not refused as capability_unavailable');
    app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, false);
    httpCheck($as('owner', HostAssignmentsTool::class, fn ($tool): ?string => $tool->unavailable()) === 'capability_unavailable', 'it is still hidden when the capability behind its ABILITY is off');
    app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, null);

    // The other shape: an explicit name wins, the permission in ABILITY hides the tool from a person whose role lacks it and refuses their call, and the form's rules apply.
    httpCheck($app->make(WriteNote::class)->name() === 'notes_write', 'an explicit $name wins over the capability-derived name');
    httpCheck($call(['title' => 'Plan'], null, 'owner', WriteNote::class) === ['ok', ['title' => 'Plan', 'actor' => (int) $owner->id]], 'a role holding the ability calls the tool');
    httpCheck($call(['title' => str_repeat('x', 21)], null, 'owner', WriteNote::class) === ['error', 'validation_failed'], 'rules() from the web form\'s FormRequest validate the call');
    // In-chat confirmation, through the real gate: ask, then run with the token; a token never carries over to other arguments.
    $fired = [];
    [$asked, $prompt] = $call(['id' => 3], null, 'owner', DeleteNote::class);
    httpCheck($asked === 'ok' && $prompt['status'] === 'confirmation_required' && $prompt['summary'] === 'Delete note 3' && is_string($prompt['confirm_token']), 'a destructive tool first returns confirmation_required with its summary and a confirm token');
    httpCheck($fired === [], 'a confirmation_required prompt dispatches no ToolCalled');
    httpCheck($call(['id' => 3, 'confirm_token' => $prompt['confirm_token']], null, 'owner', DeleteNote::class) === ['ok', ['deleted' => 3]] && count($fired) === 1, 'the same call carrying the confirm token runs, and dispatches ToolCalled');
    $prompt = $call(['id' => 3], null, 'owner', DeleteNote::class)[1];
    httpCheck($call(['id' => 4, 'confirm_token' => $prompt['confirm_token']], null, 'owner', DeleteNote::class) === ['error', 'confirmation_invalid'], 'a confirm token is refused when the arguments change');
    httpCheck($call(['id' => 3, 'confirm_token' => $prompt['confirm_token']], null, 'owner', DeleteNote::class) === ['error', 'confirmation_invalid'], 'and is spent by that refused attempt');

    // Intent replay: the same person, connection, intent_key, tool and arguments replay the stored result; anything else under that key is intent_conflict.
    $first = $call(['id' => 5, 'intent_key' => 'copy-1'], null, 'owner', CopyNote::class);
    httpCheck($first === ['ok', ['copy' => 1, 'of' => 5, 'actor' => (int) $owner->id]], 'a call with an intent_key runs once and returns its result');
    $fired = [];
    httpCheck($call(['intent_key' => 'copy-1', 'id' => 5], null, 'owner', CopyNote::class) === $first && CopyNote::$runs === 1 && $fired === [], 'a retry with the same intent_key and arguments replays the stored result without running again or dispatching ToolCalled');
    httpCheck($call(['id' => 6, 'intent_key' => 'copy-1'], null, 'owner', CopyNote::class) === ['error', 'intent_conflict'] && CopyNote::$runs === 1, 'the same intent_key with different arguments is intent_conflict and does not run');
    httpCheck($call(['id' => 5, 'intent_key' => 'copy-1'], null, 'owner', ReadNote::class) === ['error', 'unknown_arguments'], 'a tool that does not accept intent_key refuses it');
    httpCheck($call(['id' => 5, 'intent_key' => 'copy-1'], null, 'member', CopyNote::class) === ['ok', ['copy' => 2, 'of' => 5, 'actor' => (int) $people['member']->id]], "another person's connection with the same intent_key runs on its own");
    $call(['id' => 5], null, 'owner', CopyNote::class);
    $call(['id' => 5], null, 'owner', CopyNote::class);
    httpCheck(CopyNote::$runs === 4, 'a call without an intent_key runs every time');
    httpCheck(DB::table('kit_mcp_intents')->count() === 2, 'only calls carrying an intent_key are stored');
    // A destructive call with an intent: confirm, run, then a retry replays rather than asking again or running twice.
    $fired = [];
    $prompt = $call(['id' => 9, 'intent_key' => 'purge-1'], null, 'owner', PurgeNote::class)[1];
    httpCheck(($prompt['status'] ?? null) === 'confirmation_required' && PurgeNote::$runs === 0, 'a destructive call with an intent still asks first, and stores nothing until it runs');
    $done = $call(['id' => 9, 'intent_key' => 'purge-1', 'confirm_token' => $prompt['confirm_token']], null, 'owner', PurgeNote::class);
    httpCheck($done === ['ok', ['purged' => 9, 'run' => 1]], 'the confirmed call runs');
    httpCheck(count($fired) === 1 && $fired[0]->tool === 'notes_purge', 'only the confirmed run of a destructive call dispatches ToolCalled');
    httpCheck($call(['id' => 9, 'intent_key' => 'purge-1'], null, 'owner', PurgeNote::class) === $done && PurgeNote::$runs === 1 && count($fired) === 1, 'its retry, with or without the spent token, replays the result instead of asking again');
    httpCheck($call(['id' => 10, 'intent_key' => 'purge-1'], null, 'owner', PurgeNote::class) === ['error', 'intent_conflict'], "the destructive call's key reused for other arguments is intent_conflict");
    httpCheck($call(['id' => 9, 'intent_key' => 'copy-1'], null, 'owner', PurgeNote::class) === ['error', 'intent_conflict'], 'a key reused across tools is intent_conflict');
    // A failed call stores nothing, so a retry runs it.
    DB::table('kit_mcp_intents')->delete();
    $gate = $app->make(Tey\Kit\Features\Mcp\ConsentGate::class);
    $me = new Principal((int) $owner->id, $connection);
    try {
        $gate->remember('notes_copy', $me, ['id' => 1, 'intent_key' => 'fails'], fn (): array => throw new RuntimeException('limit_reached'));
    } catch (RuntimeException) {
    }
    httpCheck(DB::table('kit_mcp_intents')->count() === 0 && $gate->recall('notes_copy', $me, ['id' => 1, 'intent_key' => 'fails']) === null, 'a call that fails stores nothing, so its retry runs');
    // A race: the second call's row hits the unique index, its work rolls back, and it returns the first call's result.
    DB::table('kit_mcp_intents')->insert(['actor_id' => $owner->id, 'connection_id' => $connection, 'intent_key' => 'race', 'fingerprint' => hash('sha256', json_encode([CopyNote::class, ['id' => 2, 'intent_key' => 'race']])), 'result' => json_encode(['winner' => true]), 'created_at' => now(), 'expires_at' => now()->addHour()]);
    $raced = $gate->remember('notes_copy', $me, ['id' => 2, 'intent_key' => 'race'], function (): array {
        DB::table('audit_events')->insert(['actor_id' => 0, 'workspace_id' => 0, 'event' => 'race.loser', 'details' => '{}', 'created_at' => now()]);

        return ['winner' => false];
    });
    httpCheck($raced === ['winner' => true] && ! DB::table('audit_events')->where('event', 'race.loser')->exists(), "a racing call that loses the unique index rolls its work back and returns the winner's result");

    // Expiry: a stored intent lasts kit.mcp.intent_ttl (24 hours by default); after that its key is new again, and model:prune deletes it.
    DB::table('kit_mcp_intents')->delete();
    $runs = CopyNote::$runs;
    $kept = $call(['id' => 8, 'intent_key' => 'ttl'], null, 'owner', CopyNote::class);
    $row = DB::table('kit_mcp_intents')->where('intent_key', 'ttl')->first();
    httpCheck(Illuminate\Support\Carbon::parse($row->expires_at)->equalTo(Illuminate\Support\Carbon::parse($row->created_at)->addDay()), 'a stored intent expires 24 hours after the call by default');
    httpCheck($call(['id' => 8, 'intent_key' => 'ttl'], null, 'owner', CopyNote::class) === $kept && CopyNote::$runs === $runs + 1, 'within its lifetime a retry still replays');
    // Expire it (the clock is left alone: the test's access token lasts an hour).
    DB::table('kit_mcp_intents')->where('intent_key', 'ttl')->update(['expires_at' => now()->subSecond()]);
    httpCheck($call(['id' => 8, 'intent_key' => 'ttl'], null, 'owner', CopyNote::class)[1]['copy'] === $runs + 2, 'after it expires, the same call runs again instead of replaying');
    DB::table('kit_mcp_intents')->where('intent_key', 'ttl')->update(['expires_at' => now()->subSecond()]);
    $fresh = $call(['id' => 9, 'intent_key' => 'ttl'], null, 'owner', CopyNote::class);
    httpCheck($fresh[0] === 'ok' && $fresh[1]['of'] === 9 && CopyNote::$runs === $runs + 3, 'an expired key is treated as new: other arguments run instead of intent_conflict');
    httpCheck(DB::table('kit_mcp_intents')->where('intent_key', 'ttl')->count() === 1 && DB::table('kit_mcp_intents')->where('intent_key', 'ttl')->value('fingerprint') !== $row->fingerprint, 'the expired row is replaced by the new call');
    $config->set('kit.mcp.intent_ttl', 60);
    $call(['id' => 1, 'intent_key' => 'short'], null, 'owner', CopyNote::class);
    $short = DB::table('kit_mcp_intents')->where('intent_key', 'short')->first();
    httpCheck(Illuminate\Support\Carbon::parse($short->expires_at)->equalTo(Illuminate\Support\Carbon::parse($short->created_at)->addSeconds(60)), 'kit.mcp.intent_ttl sets the lifetime');
    $config->set('kit.mcp.intent_ttl', 86400);
    // Pruning: model:prune on McpIntent deletes only expired rows.
    DB::table('kit_mcp_intents')->delete();
    DB::table('kit_mcp_intents')->insert([
        ['actor_id' => $owner->id, 'connection_id' => $connection, 'intent_key' => 'old', 'fingerprint' => 'x', 'result' => '{}', 'created_at' => now()->subDays(2), 'expires_at' => now()->subDay()],
        ['actor_id' => $owner->id, 'connection_id' => $connection, 'intent_key' => 'live', 'fingerprint' => 'x', 'result' => '{}', 'created_at' => now(), 'expires_at' => now()->addHour()],
    ]);
    $pruned = (new Tey\Kit\Features\Mcp\Models\McpIntent)->pruneAll();
    httpCheck($pruned === 1 && DB::table('kit_mcp_intents')->pluck('intent_key')->all() === ['live'], 'pruning deletes expired intents and keeps live ones');
    $command = new Illuminate\Database\Console\PruneCommand;
    $command->setLaravel($app);
    DB::table('kit_mcp_intents')->update(['expires_at' => now()->subSecond()]);
    $command->run(new Symfony\Component\Console\Input\ArrayInput(['--model' => [Tey\Kit\Features\Mcp\Models\McpIntent::class]]), new Symfony\Component\Console\Output\NullOutput);
    httpCheck(DB::table('kit_mcp_intents')->count() === 0, 'the scheduled `model:prune --model=McpIntent` the docs give prunes them');

    // 0.4.3: per-call destructive, control arguments kept from call(), a tool's own not-found code, and unexpected failures reported.
    $handler = new RecordingHandler;
    $app->instance(Illuminate\Contracts\Debug\ExceptionHandler::class, $handler);
    httpCheck($call(['id' => 1, 'mode' => 'read'], null, 'owner', ProbeNote::class) === ['ok', ['probed' => 1]], 'a call the tool does not mark destructive runs without confirmation, though the tool carries the flag');
    $prompt = $call(['id' => 1, 'mode' => 'delete'], null, 'owner', ProbeNote::class)[1];
    httpCheck(($prompt['status'] ?? null) === 'confirmation_required', 'the destructive call of the same tool asks first');
    ProbeNote::$seen = [];
    httpCheck($call(['id' => 1, 'mode' => 'delete', 'confirm_token' => $prompt['confirm_token']], null, 'owner', ProbeNote::class) === ['ok', ['probed' => 1]], 'and runs with its token');
    $call(['id' => 2, 'intent_key' => 'probe-1'], null, 'owner', ProbeNote::class);
    httpCheck(ProbeNote::$seen === [['id' => 1, 'mode' => 'delete'], ['id' => 2]], 'call() never sees intent_key or confirm_token');
    httpCheck($call(['id' => 2, 'intent_key' => 'probe-1'], null, 'owner', ProbeNote::class) === ['ok', ['probed' => 2]] && count(ProbeNote::$seen) === 2, 'the intent_key still reaches the gate, which replays the retry');
    httpCheck($call(['id' => 404], null, 'owner', ProbeNote::class) === ['error', 'note_workspace_not_found'], 'notFoundCode() is what an uncoded ModelNotFoundException is refused with');
    httpCheck($call(['id' => 404]) === ['error', 'not_found'], 'and not_found stays the default');
    httpCheck($handler->reported === [], 'validation, not-found and coded refusals are not reported');
    $call(['id' => 405]);
    $call(['id' => 'seven']);
    httpCheck($handler->reported === [], 'coded refusals are not reported either');
    httpCheck($call(['id' => 500], null, 'owner', ProbeNote::class) === ['error', 'operation_failed'] && count($handler->reported) === 1 && $handler->reported[0]->getMessage() === 'boom', 'an unexpected exception is operation_failed and is reported once');

    httpCheck($call(['id' => 7], null, 'owner', UnadmittedNote::class) === ['error', 'connection_denied'], 'a tool with no ability or scope is never admitted, even if it is not registered through capabilityTools()');
    httpCheck($as('owner', WriteNote::class, fn (CapabilityTool $tool): bool => $tool->shouldRegister()) && $as('member', ReadNote::class, fn (CapabilityTool $tool): bool => $tool->shouldRegister()), 'the tool is listed for a role holding its ability, and a tool with no ability is listed for any member');
    httpCheck(! $as('member', WriteNote::class, fn (CapabilityTool $tool): bool => $tool->shouldRegister()), 'the tool is absent from the list for a role lacking its ability');
    httpCheck($call(['title' => 'Plan'], null, 'member', WriteNote::class) === ['error', 'permission_denied'], 'and a call to it anyway is refused as permission_denied');
    foreach ([
        'an ability another capability gates' => fn () => Capability::check(MisgatedNote::class, ['workspace.settings', 'workspace.other'], ['settings.write' => 'workspace.settings']),
        'no ability or scope to be admitted under' => fn () => $app->make(Registry::class)->capabilityTools([UnadmittedNote::class]),
    ] as $what => $wrong) {
        try {
            $wrong();
            $refused = false;
        } catch (RuntimeException) {
            $refused = true;
        }
        httpCheck($refused, 'capabilityTools() refuses a tool with '.$what);
    }
    echo "CapabilityTool checks on SQLite memory with real Passport tables, Concierge and spatie teams, through the real ConsentGate on an acts-as-me connection whose token holds only mcp:use and mcp:act; the connection is bound as BindMcpConnection binds it, with no OAuth HTTP exchange or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
