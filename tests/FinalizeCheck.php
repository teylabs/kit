<?php

// The finalize feature: steps computed from real state, recorded confirmations and their invalidation by fingerprint, the certified commit and its
// refusals (stale revision, incomplete, uncertified), scoped read-only, un-finalize with a reason and the optional extra rule, lock_version, tenant
// isolation, permissions and the per-subject check, descriptors, the HTTP routes and the MCP channel. SQLite memory, real Concierge, spatie teams and
// Pennant, synthetic users.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationException;
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
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Finalize\Data\GateError;
use Tey\Kit\Features\Finalize\Events\Finalized;
use Tey\Kit\Features\Finalize\Events\Unfinalized;
use Tey\Kit\Features\Finalize\FinalizesWithinTransaction;
use Tey\Kit\Features\Finalize\Flow;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Integrations\Mcp\FinalizeSubject;
use Tey\Kit\Features\Finalize\Models\Confirmation;
use Tey\Kit\Features\Finalize\Models\Finalization;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;
use Tey\Kit\Features\Finalize\Step;
use Tey\Kit\Features\Finalize\StepOutcome;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;
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

// The app's subject: a report in a workspace, with an amount in minor units, a printed note, a field that does not print, and an optimistic lock.
final class Report extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['secret' => 'boolean', 'amount' => 'integer', 'lock_version' => 'integer'];
}

// The same report as a subject that bumps its own guard inside the finalize transaction (FinalizesWithinTransaction), with a switch to fail.
final class GuardedReport extends Model implements FinalizesWithinTransaction
{
    public $timestamps = false;

    protected $table = 'reports';

    protected $guarded = [];

    protected $casts = ['lock_version' => 'integer'];

    /** @var list<array{string, string, int, int, int|null, array<string, mixed>}> */
    public static array $calls = [];

    public static bool $fail = false;

    public function onFinalizing(string $flow, Finalization $finalization, Principal $principal, array $extras): void
    {
        $this->hook('finalizing', $flow, $finalization, $principal, $extras);
    }

    public function onUnfinalizing(string $flow, Finalization $finalization, Principal $principal): void
    {
        $this->hook('unfinalizing', $flow, $finalization, $principal, []);
    }

    /** @param  array<string, mixed>  $extras */
    private function hook(string $kind, string $flow, Finalization $finalization, Principal $principal, array $extras): void
    {
        self::$calls[] = [$kind, $flow, DB::transactionLevel(), $principal->actorId, $finalization->exists ? (int) $finalization->getKey() : null, $extras];
        $this->forceFill(['lock_version' => $this->lock_version + 1, 'internal' => $kind])->saveQuietly();
        if (self::$fail) {
            throw new RuntimeException('hook_refused');
        }
    }
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The refusal code an operation throws, or null when it ran. */
function refusal(callable $operation): ?string
{
    try {
        $operation();
    } catch (ValidationException) {
        return 'validation';
    } catch (RuntimeException|InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return null;
}

try {
    $base = sys_get_temp_dir().'/kit-finalize-'.bin2hex(random_bytes(4));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp', 'notes', 'finalize'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true, 'workspace.finalize' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
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
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class, MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // The host's `web` group, with an Inertia middleware (the kit's plain one): session-ui's props are shared by its provider, not by a middleware.
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
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
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']], 'viewer' => ['label' => 'Viewer', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $beta = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    foreach ([$workspace, $beta] as $each) {
        $each->execute(function () use ($registry, $each): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($each);
            }
        });
    }
    $people = [];
    // Two members, so a member can meet someone else's note; `outsider` owns Beta and belongs to no Acme.
    foreach (['owner' => 'owner', 'admin' => 'admin', 'member' => 'member', 'other' => 'member', 'viewer' => 'viewer', 'outsider' => 'owner'] as $name => $role) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        $in = $name === 'outsider' ? $beta : $workspace;
        ConciergeModels::membership()::query()->create(['workspace_id' => $in->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Concierge::class)->context()->run($in, fn () => $people[$name]->assignRole($role), [$people[$name]]);
    }
    Schema::create('reports', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->string('title');
        $table->integer('amount')->nullable();
        $table->string('note')->default('');
        $table->string('internal')->default('');
        $table->boolean('secret')->default(false);
        $table->unsignedInteger('lock_version')->default(1);
        $table->unsignedBigInteger('customer_id')->nullable();
    });
    // A record the report prints but does not own: edited on its own page, outside the finalize gate.
    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('address');
    });

    $sync->run(new ArrayInput([]), new NullOutput);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();

    $sync->run(new ArrayInput([]), new NullOutput);
    // This app's member role reads and confirms; only owner and admin certify, and only the owner un-finalizes (the kit's defaults).
    $roles = app(PermissionRegistrar::class)->getRoleClass();
    $roles::query()->where('name', 'member')->get()->each(fn ($role) => $role->givePermissionTo(['workspace.finalize.view', 'workspace.finalize.confirm']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $workspace->refresh();
    $beta->refresh();
    $in = fn (Workspace $at, callable $operation) => Workspaces::run($at->id, Closure::fromCallable($operation));
    $as = fn (string $who): Principal => new Principal((int) $people[$who]->id);
    $key = fn (): string => bin2hex(random_bytes(8));
    $http = function (string $who, string $method, string $path, array $data = [], array $files = [], bool $inertia = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $inertia ? ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_ACCEPT' => 'application/json'];
        $request = Request::create('https://kit.test'.$path, $method, $data, files: $files, server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response, (string) $response->getContent()];
    };

    // The app's flow: an amount check, a confirmation of what prints (amount and note), an optional step that does not apply, and the certify statement
    // the workspace edits (a placeholder until the client's wording). The subject key is composite: workspace and report number.
    $statement = ['version' => 'placeholder-1', 'text' => 'I certify this report. (Placeholder until the client supplies the wording.)'];
    $fourEyes = false;
    $flows = $app->make(Flows::class);
    $flows->register(Flow::define('report.finalize')
        ->subject('report', function (string $key, bool $lock): ?Report {
            [$at, $id] = array_pad(explode(':', $key, 2), 2, '');
            if ((int) $at !== (int) Workspaces::required()->getKey() || ! ctype_digit($id)) {
                return null;
            }

            return Report::query()->where('workspace_id', (int) $at)->whereKey((int) $id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        }, fn (Report $report): string => $report->workspace_id.':'.$report->id)
        ->steps([
            Step::check('amount', 'Amount entered', fn (Report $r) => $r->amount !== null ? StepOutcome::done() : StepOutcome::todo([new GateError('Enter the amount.', 'amount', '/reports/'.$r->id.'/edit')], [new Action('Enter the amount', '/reports/'.$r->id.'/edit', 'get', key: 'fix-amount')])),
            Step::confirmation('review', 'Printed figures reviewed', fn (Report $r): array => ['amount' => $r->amount, 'note' => trim($r->note)], ready: fn (Report $r) => $r->amount !== null ? true : StepOutcome::blocked('Enter the amount first.')),
            Step::check('photos', 'Photos attached', fn () => StepOutcome::skipped('This report has no photos.'))->optional(),
        ])
        ->statement(function (Report $r) use (&$statement): array {
            return $statement;
        })
        ->can(fn (Principal $principal, Report $r, string $ability): bool => ! $r->secret || $principal->actorId === (int) $people['owner']->id)
        ->lockColumn('lock_version')
        ->unfinalizeCheck(function (Principal $principal, Report $r, $finalization) use (&$fourEyes): ?string {
            return $fourEyes && (int) $finalization->finalized_by === $principal->actorId ? 'A second person must un-finalize.' : null;
        })
        ->url(fn (Report $r): string => '/reports/'.$r->id)
        ->snapshot(function (Report $r): array {
            $customer = DB::table('customers')->find($r->customer_id);

            return ['customer.name' => $customer?->name, 'customer.address' => $customer?->address];
        }));
    check(refusal(fn () => $flows->register(Flow::define('report.finalize')->subject('report', fn () => null)->statement(fn () => $statement)->steps([Step::check('a', 'A', fn () => true)]))) === 'Finalize flow registered twice: report.finalize', 'a flow registers once');
    check(refusal(fn () => Flow::define('x')->steps([Step::check('certify', 'C', fn () => true)])) !== null, 'a flow cannot name a step certify, the kit\'s own');
    $bad = fn (array $values) => refusal(fn () => Flow::define('x')->snapshot(fn () => $values)->snapshotFor(new Report));
    check($bad(['total' => 12.5]) !== null && $bad(['lines' => [1, 2]]) !== null && $bad([0 => 'a']) !== null && $bad(array_fill_keys(array_map(fn ($i) => 'k'.$i, range(1, 51)), 1)) !== null && $bad(['name' => 'A', 'paid' => true, 'cents' => 5, 'note' => null]) === null, 'a snapshot holds named scalars (no floats, lists or numeric keys), at most 50 of them');

    $gate = app(FinalizeGate::class);
    $customer = DB::table('customers')->insertGetId(['name' => 'Robin Vale', 'address' => '1 Main St']);
    $report = $in($workspace, fn () => Report::create(['workspace_id' => $workspace->id, 'title' => 'Main St', 'note' => 'As seen.', 'customer_id' => $customer]));
    $other = $in($beta, fn () => Report::create(['workspace_id' => $beta->id, 'title' => 'Beta report', 'amount' => 100]));
    $subject = $workspace->id.':'.$report->id;
    $state = fn (string $who = 'owner') => $in($workspace, fn () => $gate->state($as($who), 'report.finalize', $subject));
    $step = fn ($s, string $key) => collect($s->steps)->firstWhere('key', $key);

    // Steps from real state, server progress, descriptors.
    $s = $state();
    check($step($s, 'amount')->state === 'active' && $step($s, 'amount')->errors[0]->field === 'amount' && $step($s, 'amount')->actions[0]->key === 'fix-amount', 'a failing check is the current step, with its error, field and fix descriptor');
    check($step($s, 'review')->state === 'pending' && $step($s, 'review')->reason === 'Enter the amount first.' && $step($s, 'review')->actions === [], 'a confirmation that is not ready is blocked with a reason and offers no Confirm');
    check($step($s, 'photos')->state === 'skipped' && $step($s, 'certify')->kind === 'certify' && $step($s, 'certify')->state === 'pending', 'an optional step that does not apply is skipped; certify comes last');
    check($s->progress->total === 3 && $s->progress->done === 1 && $s->progress->required === 2 && $s->progress->requiredDone === 0 && ! $s->progress->complete && $s->progress->currentKey === 'amount', 'progress is computed on the server, certify excluded');
    check($s->finalize?->disabled === true && $s->finalize->reason !== null && $s->finalization === null && $s->statementVersion === 'placeholder-1', 'Finalize is present but disabled with a reason until complete; the placeholder statement is sent');
    check($state('viewer')->finalize === null && $step($state('viewer'), 'review')->actions === [], 'a viewer sees the gate with no Finalize and no Confirm');
    check(refusal(fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $s->progress->revision, true)) !== null, 'outside a workspace the gate refuses');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $s->progress->revision, true))) === 'finalize_incomplete', 'an incomplete gate is refused');

    $in($workspace, fn () => $report->update(['amount' => 125000]));
    $s = $state();
    check($step($s, 'amount')->state === 'done' && $step($s, 'review')->state === 'active' && $step($s, 'review')->actions[0]->key === 'confirm-review', 'a passing check is done by whatever path made it pass; the confirmation is now current, with Confirm');
    check(refusal(fn () => $in($workspace, fn () => $gate->confirm($as('viewer'), 'report.finalize', $subject, 'review', $s->progress->revision))) === 'permission_denied', 'a viewer cannot confirm');
    check(refusal(fn () => $in($workspace, fn () => $gate->confirm($as('member'), 'report.finalize', $subject, 'amount', $s->progress->revision))) === 'finalize_step_not_confirmable', 'a check is not confirmable');
    check(refusal(fn () => $in($workspace, fn () => $gate->confirm($as('member'), 'report.finalize', $subject, 'review', 'stale'))) === 'finalize_stale' && $in($workspace, fn () => Confirmation::query()->count()) === 0, 'Confirm with an old revision is refused and writes nothing');
    $s = $in($workspace, fn () => $gate->confirm($as('member'), 'report.finalize', $subject, 'review', $s->progress->revision));
    check($step($s, 'review')->state === 'done' && $step($s, 'review')->confirmation->by === 'Member' && $s->progress->complete && $s->finalize === null && $state()->finalize?->disabled === false, 'a member confirms: the step is done, by them, and the gate is complete; Finalize is enabled for the owner and absent for the member, who cannot certify');
    check($in($workspace, fn () => Confirmation::query()->value('channel')) === 'web', 'the confirmation records the web channel');

    // Fingerprints: a quiet change keeps the confirmation, a printed change re-opens the step.
    $in($workspace, fn () => $report->update(['internal' => 'recalculated', 'note' => 'As seen. ']));
    check($step($state(), 'review')->state === 'done', 'a change to what does not print, or to whitespace the fingerprint normalises, keeps the confirmation');
    $before = $state()->progress->revision;
    DB::table('reports')->where('id', $report->id)->update(['amount' => 126000]);
    $s = $state();
    check($step($s, 'review')->state === 'active' && $step($s, 'review')->confirmation->stale && str_starts_with((string) $step($s, 'review')->reason, 'Changed since Member confirmed it on') && ! $s->progress->complete, 'a printed change by any path (here a raw query) re-opens the step with "changed since"');
    check($s->progress->revision !== $before, 'the revision changes with it');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $before, true))) === 'finalize_stale', 'Finalize with the old revision is refused');
    $s = $in($workspace, fn () => $gate->confirm($as('admin'), 'report.finalize', $subject, 'review', $s->progress->revision));
    check($in($workspace, fn () => Confirmation::query()->count()) === 2 && $step($s, 'review')->confirmation->by === 'Admin', 'confirming again adds a row and keeps the old one');

    // Fail closed: a required step broken between page load and submit.
    $loaded = $s->progress->revision;
    DB::table('reports')->where('id', $report->id)->update(['amount' => null]);
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $loaded, true))) === 'finalize_stale' && $in($workspace, fn () => Finalization::query()->count()) === 0, 'a step broken after the page loaded refuses Finalize, and nothing is written');
    DB::table('reports')->where('id', $report->id)->update(['amount' => 126000]);
    $s = $state();
    check($s->progress->revision === $loaded && $s->progress->complete, 'restoring the value restores the same revision and the confirmation');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $s->progress->revision, false))) === 'finalize_certify_required', 'Finalize without certified is refused');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('member'), 'report.finalize', $subject, $s->progress->revision, true))) === 'permission_denied', 'a member cannot certify');

    // The commit.
    $events = [];
    $lastExtras = null;
    Event::listen(Finalized::class, function (Finalized $e) use (&$events, &$lastExtras): void {
        $events[] = ['finalized', $e->flow, DB::transactionLevel()];
        $lastExtras = $e->extras;
    });
    Event::listen(Unfinalized::class, function (Unfinalized $e) use (&$events): void {
        $events[] = ['unfinalized', $e->flow, DB::transactionLevel()];
    });
    $statement = ['version' => 'acme-2', 'text' => 'I, the appraiser, certify this report.'];
    $s = $in($workspace, fn () => $gate->finalize($as('admin'), 'report.finalize', $subject, $s->progress->revision, true));
    $row = $in($workspace, fn () => Finalization::query()->sole());
    check($row->finalized_by === (int) $people['admin']->id && $row->channel === 'web' && $row->statement_version === 'acme-2' && $row->statement_hash === hash('sha256', 'I, the appraiser, certify this report.') && strlen($row->snapshot_hash) === 64 && $row->subject_key === $subject, 'finalizing records who, the channel, the workspace statement version and hash, and a snapshot hash');
    check($events === [['finalized', 'report.finalize', 0]], 'Finalized is dispatched once, after the commit');
    check($row->snapshot === ['customer.address' => '1 Main St', 'customer.name' => 'Robin Vale'] && array_keys($row->snapshot_hashes) === ['customer.address', 'customer.name'] && strlen($row->snapshot_hashes['customer.name']) === 64, 'finalizing keeps a frozen copy of exactly the declared printed fields, with a hash per key');
    check($in($workspace, fn () => $gate->drift('report.finalize', $report->fresh())) === [], 'no drift right after finalizing');
    DB::table('customers')->where('id', $customer)->update(['name' => 'Robin Vale-Okafor']);
    check($in($workspace, fn () => $gate->drift('report.finalize', $report->fresh())) === ['customer.name'] && $in($workspace, fn () => $gate->snapshot('report.finalize', $report->fresh()))['customer.name'] === 'Robin Vale', 'a related record edited after finalizing is named as drift, and the snapshot still prints what was certified');
    check($in($beta, fn () => $gate->snapshot('report.finalize', $report->fresh())) === null && $in($beta, fn () => $gate->drift('report.finalize', $report->fresh())) === [], 'another workspace reads neither the snapshot nor its drift');
    check((int) DB::table('reports')->where('id', $report->id)->value('lock_version') === 2, 'finalizing bumps lock_version, so an open tab\'s write is refused by the app\'s check');
    check($s->finalization?->by === 'Admin' && $s->finalization->channel === 'web' && $s->finalization->unfinalize === null && $s->finalize === null && $step($s, 'certify')->state === 'done', 'the banner record: by and channel; an admin has no Un-finalize; Finalize is gone');
    check($state()->finalization->unfinalize?->key === 'unfinalize', 'the owner is offered Un-finalize');
    check(collect($state()->steps)->every(fn ($st) => $st->actions === []), 'while finalized no step offers an action');
    check($in($workspace, fn () => $gate->isFinalized('report.finalize', $report->fresh())) && ! $in($beta, fn () => $gate->isFinalized('report.finalize', $other)), 'isFinalized answers for the app\'s own policies, per subject');
    check(refusal(fn () => $in($workspace, fn () => $gate->confirm($as('member'), 'report.finalize', $subject, 'review', $state()->progress->revision))) === 'finalize_finalized', 'confirming is refused while finalized');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $state()->progress->revision, true))) === 'finalize_finalized', 'finalizing twice is refused');
    DB::table('reports')->where('id', $report->id)->update(['internal' => 'payment logged']);
    check((string) DB::table('reports')->where('id', $report->id)->value('internal') === 'payment logged', 'work outside the finalized scope stays open (the kit locks nothing the app did not declare)');
    check(refusal(fn () => $in($workspace, fn () => $row->delete())) === 'finalize_history_is_kept', 'a finalization row cannot be deleted');

    // Un-finalize.
    $r = $state()->progress->revision;
    check(refusal(fn () => $in($workspace, fn () => $gate->unfinalize($as('admin'), 'report.finalize', $subject, $r, 'Wrong figure'))) === 'permission_denied', 'un-finalize is its own ability: an admin who certifies cannot');
    check(refusal(fn () => $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.finalize', $subject, $r, '  '))) === 'finalize_reason_required', 'un-finalize needs a reason');
    $fourEyes = true;
    $flows->get('report.finalize');
    $in($workspace, fn () => DB::table('kit_finalizations')->where('id', $row->id)->update(['finalized_by' => $people['owner']->id]));
    $r = $state()->progress->revision;
    check(refusal(fn () => $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.finalize', $subject, $r, 'Wrong figure'))) === 'finalize_unfinalize_refused', 'the optional four-eyes rule, when an app turns it on, refuses the finalizer');
    $fourEyes = false;
    $s = $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.finalize', $subject, $r, 'Wrong figure'));
    $row->refresh();
    check($row->unfinalized_by === (int) $people['owner']->id && $row->unfinalize_reason === 'Wrong figure' && $row->unfinalized_channel === 'web' && $s->finalization === null && $events[1] === ['unfinalized', 'report.finalize', 0], 'un-finalizing records who, why and the channel, keeps the row, and dispatches Unfinalized after the commit');
    check((int) DB::table('reports')->where('id', $report->id)->value('lock_version') === 3 && $step($s, 'review')->state === 'done' && $s->progress->complete, 'un-finalizing bumps lock_version; confirmations are kept and still hold while unchanged');
    check($in($workspace, fn () => $gate->snapshot('report.finalize', $report->fresh())) === null && $in($workspace, fn () => $gate->drift('report.finalize', $report->fresh())) === [] && $row->snapshot['customer.name'] === 'Robin Vale', 'once un-finalized the app prints live values again; the old row keeps its snapshot as history');
    DB::table('reports')->where('id', $report->id)->update(['note' => 'Revised.']);
    check($step($state(), 'review')->state === 'active', 'an edit after un-finalizing re-opens its step by itself');

    // Tenant isolation and the per-subject check.
    check(refusal(fn () => $in($beta, fn () => $gate->state($as('outsider'), 'report.finalize', $subject))) === 'finalize_subject_not_found', 'another workspace cannot read this gate, even by its key');
    check(refusal(fn () => $in($workspace, fn () => $gate->state($as('owner'), 'report.finalize', $beta->id.':'.$other->id))) === 'finalize_subject_not_found', 'nor can this workspace reach another\'s subject by key');
    check($in($beta, fn () => Confirmation::query()->count() + Finalization::query()->count()) === 0, 'confirmations and finalizations of one workspace are not read from another');
    check(refusal(fn () => $in($beta, fn () => (new Confirmation)->forceFill(['workspace_id' => $workspace->id, 'flow' => 'x', 'subject_key' => '1', 'step' => 's', 'fingerprint' => 'f', 'confirmed_by' => 1, 'channel' => 'web', 'confirmed_at' => now()])->save())) === 'workspace_mismatch' && Confirmation::withoutGlobalScopes()->where('flow', 'x')->count() === 0, 'a row cannot be written into another workspace');
    $secret = $in($workspace, fn () => Report::create(['workspace_id' => $workspace->id, 'title' => 'Secret', 'amount' => 1, 'secret' => true]));
    check(refusal(fn () => $in($workspace, fn () => $gate->state($as('admin'), 'report.finalize', $workspace->id.':'.$secret->id))) === 'finalize_subject_not_found' && $in($workspace, fn () => $gate->state($as('owner'), 'report.finalize', $workspace->id.':'.$secret->id))->subject === $workspace->id.':'.$secret->id, 'the flow\'s per-subject check hides a subject as not found');
    check(refusal(fn () => $in($workspace, fn () => $gate->state($as('owner'), 'nope', $subject))) === 'finalize_flow_unknown', 'an unknown flow is refused');
    check(refusal(fn () => $in($workspace, fn () => $gate->confirm($as('member'), 'report.finalize', $subject, 'review', $state()->progress->revision, 'sms'))) !== null, 'a channel other than web or mcp is refused');

    // HTTP: the JSON gate, a stale Confirm is 409 and writes nothing, a missing revision fails closed.
    $base = '/@acme/finalize/report.finalize/'.rawurlencode($subject);
    [$status, , $body] = $http('member', 'GET', $base);
    check($status === 200 && json_decode($body, true)['progress']['revision'] === $state('member')->progress->revision, 'GET answers the gate as JSON, with the revision inside progress');
    $count = $in($workspace, fn () => Confirmation::query()->count());
    [$status] = $http('member', 'POST', $base.'/steps/review/confirm', ['revision' => 'old']);
    [$missing] = $http('member', 'POST', $base.'/steps/review/confirm');
    check($status === 409 && $missing === 409 && $in($workspace, fn () => Confirmation::query()->count()) === $count, 'a stale or missing revision is 409 and writes nothing');
    $descriptor = collect($step($state('member'), 'review')->actions)->firstWhere('key', 'confirm-review');
    [$status, , $body] = $http('member', 'POST', parse_url($descriptor->url, PHP_URL_PATH).'?'.parse_url($descriptor->url, PHP_URL_QUERY));
    check($status === 200 && json_decode($body, true)['progress']['complete'] === true && str_contains($descriptor->url, 'revision='), 'the Confirm descriptor posts as it is, with no body: its URL carries the revision the viewer saw');
    [$status] = $http('member', 'POST', $base.'/finalize', ['revision' => $state()->progress->revision, 'certified' => true]);
    [$outsider] = $http('outsider', 'GET', $base);
    check($status === 403 && in_array($outsider, [403, 404], true), 'a member is refused Finalize over HTTP; an outsider cannot read the gate');

    // MCP: the same operations, recorded as the mcp channel.
    $s = $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $subject, $state()->progress->revision, true, 'mcp'));
    check($in($workspace, fn () => Finalization::query()->latest('id')->value('channel')) === 'mcp' && $s->finalization->channel === 'mcp', 'a finalization through an assistant is recorded as the mcp channel');
    check($in($workspace, fn () => $gate->snapshot('report.finalize', $report->fresh()))['customer.name'] === 'Robin Vale-Okafor', 'certifying again adopts the related record\'s new values');
    check(app(Registry::class)->capabilityFor('finalize.certify') === 'workspace.finalize' && (new ReflectionClass(FinalizeSubject::class))->getAttributes(Capability::class)[0]->newInstance()->destructive, 'the MCP finalize tool is registered on the capability and asks for confirmation in the chat');

    // A recap flow: one confirmation per section, with a non-blocking warning.
    $flows->register(Flow::define('report.recap')
        ->subject('report', fn (string $key, bool $lock): ?Report => Report::query()->where('workspace_id', Workspaces::required()->getKey())->whereKey((int) $key)->first())
        ->steps([Step::confirmation('photos', 'Photos reviewed', fn (Report $r): array => ['title' => $r->title])->warnings(fn (Report $r): array => ['No subject photos.'])])
        ->statement(fn (): array => ['version' => 'v1', 'text' => 'I certify the recap.']));
    $recap = (string) $report->id;
    $s = $in($workspace, fn () => $gate->state($as('owner'), 'report.recap', $recap));
    check($step($s, 'photos')->warnings[0]->message === 'No subject photos.' && $step($s, 'photos')->errors === [] && collect($step($s, 'photos')->actions)->contains('key', 'confirm-photos'), 'a warning shows in the step and Confirm stays offered');
    $s = $in($workspace, fn () => $gate->confirm($as('member'), 'report.recap', $recap, 'photos', $s->progress->revision));
    check($s->progress->complete && $step($s, 'photos')->state === 'done' && $step($s, 'photos')->warnings[0]->message === 'No subject photos.', 'warnings never hold the gate back: confirmed, it is complete with the warning still shown');

    // Declared extras (Flow::extras): an optional "what changed" note sent with the certify request. A flow without extras reads nothing more.
    $tool = new FinalizeSubject;
    $rulesOf = fn (): array => (new ReflectionMethod(FinalizeSubject::class, 'rules'))->invoke($tool);
    check(! array_key_exists('extra', $tool->schema(new JsonSchemaTypeFactory)) && ! array_key_exists('extra', $rulesOf()), 'without a flow that declares extras the MCP finalize tool takes exactly the arguments it took before');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.recap', $recap, $in($workspace, fn () => $gate->state($as('owner'), 'report.recap', $recap))->progress->revision, true, 'web', ['note' => 'x']))) === 'unknown_arguments' && $in($workspace, fn () => Finalization::query()->where('flow', 'report.recap')->count()) === 0, 'an extra on a flow that declares none is refused and nothing is written');
    check(refusal(fn () => Flow::define('x')->extras(['certified' => ['string']], fn () => [])) !== null && refusal(fn () => Flow::define('x')->extras(['What Changed' => ['string']], fn () => [])) !== null && refusal(fn () => Flow::define('x')->extras([], fn () => [])) !== null, 'an extra is a declared snake_case name, never one the certify request already carries');
    $applied = [];
    $flows->register(Flow::define('report.extras')
        ->subject('report', fn (string $key, bool $lock): ?Report => ctype_digit($key) ? Report::query()->where('workspace_id', Workspaces::required()->getKey())->whereKey((int) $key)->first() : null)
        ->steps([Step::check('amount', 'Amount entered', fn (Report $r) => $r->amount !== null)])
        ->statement(fn (): array => ['version' => 'v1', 'text' => 'I certify the revision.'])
        ->extras(['what_changed' => ['nullable', 'string', 'max:500']], fn ($schema): array => ['what_changed' => $schema->string()->description('Optional: what changed since the last version.')],
            function (Report $r, Finalization $f, array $extras, Principal $p) use (&$applied): void {
                $applied[] = [$r->id, $f->id, $extras, $p->actorId, DB::transactionLevel()];
            }));
    $schema = $tool->schema(new JsonSchemaTypeFactory);
    $other = fn (string $name, callable $schema) => Flow::define($name)->subject('report', fn () => null)->statement(fn () => ['version' => 'v', 'text' => 't'])->steps([Step::check('a', 'A', fn () => true)])->extras(['what_changed' => ['nullable', 'string']], $schema);
    check(refusal(fn () => $flows->register($other('x.missing', fn (): array => []))) !== null && $flows->find('x.missing') === null, 'a schema that leaves out a declared extra is refused at registration, so MCP never hides a field the gate accepts');
    check(refusal(fn () => $flows->register($other('x.conflict', fn ($s): array => ['what_changed' => $s->integer()]))) !== null && $flows->find('x.conflict') === null, 'a second flow declaring the same extra with a different schema is refused at registration');
    $flows->register($other('x.same', fn ($s): array => ['what_changed' => $s->string()->description('Optional: what changed since the last version.')]));
    check($flows->find('x.same') !== null, 'the same extra with the same schema in two flows is accepted');
    check(isset($schema['extra']) && array_keys($schema['extra']->toArray()['properties'] ?? []) === ['what_changed'] && $rulesOf()['extra'] === ['sometimes', 'array'], 'a flow that declares extras adds an optional `extra` object to the MCP finalize tool, described by the declared schema');
    $revised = $in($workspace, fn () => Report::create(['workspace_id' => $workspace->id, 'title' => 'Revised', 'amount' => 5, 'note' => '']));
    $rkey = (string) $revised->id;
    $rev = fn (): string => $in($workspace, fn () => $gate->state($as('owner'), 'report.extras', $rkey))->progress->revision;
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.extras', $rkey, $rev(), true, 'web', ['what_changed' => 'ok', 'stray' => 'x']))) === 'unknown_arguments', 'an undeclared extra is refused by the operation (fail closed), not passed through');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.extras', $rkey, $rev(), true, 'web', ['what_changed' => str_repeat('a', 501)]))) === 'validation' && $applied === [] && $in($workspace, fn () => Finalization::query()->where('flow', 'report.extras')->count()) === 0, 'a declared extra is validated by the flow\'s rules; an invalid one writes nothing');
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('member'), 'report.extras', $rkey, $rev(), true, 'web', ['what_changed' => str_repeat('a', 501)]))) === 'permission_denied', 'the gate\'s own checks run before the extras\' rules');
    $base = '/@acme/finalize/report.extras/'.$rkey;
    [$status, , $body] = $http('owner', 'POST', $base.'/finalize', ['revision' => $rev(), 'certified' => true, 'what_changed' => 'x', 'stray' => 'y']);
    check($status === 422 && str_contains($body, 'unknown_arguments') && $in($workspace, fn () => Finalization::query()->where('flow', 'report.extras')->count()) === 0, 'a JSON caller stays strict: an undeclared field is 422 and nothing is written');
    $app['session']->driver()->forget('errors');
    [$status] = $http('owner', 'POST', $base.'/finalize', ['revision' => $rev(), 'certified' => '1', 'what_changed' => str_repeat('a', 501), 'stray' => 'y'], inertia: true);
    $bag = $app['session']->driver()->get('errors');
    check($status === 302 && $bag !== null && $bag->getBag('default')->has('what_changed') && ! $bag->getBag('default')->has('stray') && $in($workspace, fn () => Finalization::query()->where('flow', 'report.extras')->count()) === 0, 'a page visit shows a declared extra\'s error inline on its field, and writes nothing');
    $events = [];
    [$status] = $http('owner', 'POST', $base.'/finalize', ['revision' => $rev(), 'certified' => '1', 'what_changed' => 'Corrected the floor area.', 'stray' => 'y'], inertia: true);
    $row = $in($workspace, fn () => Finalization::query()->where('flow', 'report.extras')->latest('id')->first());
    check($status === 302 && $row !== null && $applied === [[$revised->id, $row->id, ['what_changed' => 'Corrected the floor area.'], (int) $people['owner']->id, 1]], 'a page visit passes only the declared extras (a stray field is dropped) to the flow\'s apply, inside the finalize transaction');
    check($events === [['finalized', 'report.extras', 0]] && $lastExtras === ['what_changed' => 'Corrected the floor area.'], 'Finalized carries the validated extras, after the commit');
    $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.extras', $rkey, $rev(), 'Next version'));
    $mcp = fn (array $extra) => (new ReflectionMethod(FinalizeSubject::class, 'call'))->invoke($tool, $as('owner'), (int) $workspace->id, ['flow' => 'report.extras', 'subject' => $rkey, 'revision' => $rev(), 'certified' => true, 'extra' => $extra]);
    check(refusal(fn () => $in($workspace, fn () => $mcp(['what_changed' => str_repeat('a', 501)]))) === 'validation' && refusal(fn () => $in($workspace, fn () => $mcp(['other' => 'x']))) === 'unknown_arguments', 'over MCP an extra is validated by the same rules, and an undeclared one is refused');
    $applied = [];
    $result = $in($workspace, fn () => $mcp(['what_changed' => 'Added the garage.']));
    check($result['finalization']['channel'] === 'mcp' && $applied[0][2] === ['what_changed' => 'Added the garage.'], 'over MCP the declared extra reaches the flow\'s apply, recorded as the mcp channel');
    $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.extras', $rkey, $rev(), 'Again'));
    $applied = [];
    $in($workspace, fn () => $gate->finalize($as('owner'), 'report.extras', $rkey, $rev(), true));
    check($applied[0][2] === [], 'the extras stay optional: finalizing without them still runs');

    // FinalizesWithinTransaction: the subject bumps its own lock_version inside the gate's transaction (this flow declares no lockColumn, so only the
    // hook bumps it), and an extra apply that fails after the hook rolls the hook's write back.
    $failApply = false;
    $flows->register(Flow::define('report.guarded')
        ->subject('report', fn (string $key, bool $lock): ?GuardedReport => ctype_digit($key) ? GuardedReport::query()->where('workspace_id', Workspaces::required()->getKey())->whereKey((int) $key)->when($lock, fn ($q) => $q->lockForUpdate())->first() : null)
        ->steps([Step::check('amount', 'Amount entered', fn (GuardedReport $r) => $r->amount !== null)])
        ->statement(fn (): array => ['version' => 'v1', 'text' => 'I certify the guarded report.'])
        ->extras(['what_changed' => ['nullable', 'string']], fn ($schema): array => ['what_changed' => $schema->string()->description('Optional: what changed since the last version.')],
            function () use (&$failApply): void {
                if ($failApply) {
                    throw new RuntimeException('apply_failed');
                }
            }));
    check(GuardedReport::$calls === [], 'a subject without FinalizesWithinTransaction finalizes and un-finalizes as before: no hook ran for the plain report flows');
    $guarded = $in($workspace, fn () => Report::create(['workspace_id' => $workspace->id, 'title' => 'Guarded', 'amount' => 7, 'note' => '']));
    $gkey = (string) $guarded->id;
    $grev = fn (): string => $in($workspace, fn () => $gate->state($as('owner'), 'report.guarded', $gkey))->progress->revision;
    $gversion = fn (): int => (int) DB::table('reports')->where('id', $guarded->id)->value('lock_version');
    $gcount = fn (): int => $in($workspace, fn () => Finalization::query()->where('flow', 'report.guarded')->count());
    $events = [];
    $failApply = true;
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.guarded', $gkey, $grev(), true))) === 'apply_failed' && count(GuardedReport::$calls) === 1 && $gversion() === 1 && (string) DB::table('reports')->where('id', $guarded->id)->value('internal') === '' && $gcount() === 0 && $events === [], 'the hook runs inside the finalize transaction: a failure after it rolls its write back, with no finalization and no event');
    $failApply = false;
    GuardedReport::$calls = [];
    GuardedReport::$fail = true;
    check(refusal(fn () => $in($workspace, fn () => $gate->finalize($as('owner'), 'report.guarded', $gkey, $grev(), true))) === 'hook_refused' && $gversion() === 1 && $gcount() === 0 && $events === [], 'an exception in onFinalizing rolls finalize back (fail closed) and Finalized does not fire');
    GuardedReport::$fail = false;
    GuardedReport::$calls = [];
    $seen = $gversion();
    $in($workspace, fn () => $gate->finalize($as('owner'), 'report.guarded', $gkey, $grev(), true, 'web', ['what_changed' => 'Hooked.']));
    $row = $in($workspace, fn () => Finalization::query()->where('flow', 'report.guarded')->latest('id')->first());
    check(GuardedReport::$calls === [['finalizing', 'report.guarded', 1, (int) $people['owner']->id, (int) $row->id, ['what_changed' => 'Hooked.']]] && $gversion() === $seen + 1 && $events === [['finalized', 'report.guarded', 0]], 'onFinalizing gets the flow, the written finalization, the actor and the validated extras inside the transaction; Finalized fires after the commit');
    // The app's own stale-write guard: an update that carries the version the open tab loaded before finalizing matches no row.
    $staleWrite = fn (int $version): int => DB::table('reports')->where('id', $guarded->id)->where('lock_version', $version)->update(['note' => 'edited from an old tab', 'lock_version' => $version + 1]);
    check($staleWrite($seen) === 0 && (string) DB::table('reports')->where('id', $guarded->id)->value('note') === '', 'a write carrying the lock_version from before finalize is rejected, because the hook bumped it in the same transaction');
    GuardedReport::$calls = [];
    GuardedReport::$fail = true;
    check(refusal(fn () => $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.guarded', $gkey, $grev(), 'Reopen'))) === 'hook_refused' && $gversion() === $seen + 1 && $in($workspace, fn () => $gate->isFinalized('report.guarded', $guarded->fresh())) && count($events) === 1, 'an exception in onUnfinalizing rolls un-finalize back: still finalized, version unchanged, no Unfinalized');
    GuardedReport::$fail = false;
    GuardedReport::$calls = [];
    $before = $gversion();
    $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.guarded', $gkey, $grev(), 'Reopen'));
    check(GuardedReport::$calls === [['unfinalizing', 'report.guarded', 1, (int) $people['owner']->id, (int) $row->id, []]] && $gversion() === $before + 1 && $staleWrite($before) === 0 && $events[1] === ['unfinalized', 'report.guarded', 0], 'onUnfinalizing runs inside the un-finalize transaction and bumps the guard, so a write from before un-finalizing is rejected too');

    echo "Finalize checks on SQLite memory with real Concierge, spatie teams and Pennant; HTTP through router dispatch without the global middleware stack. The MCP tools are checked by registration, the finalize tool's schema, rules and call() (with extras), and the operation's mcp channel, not over the MCP transport. No browser: the kit's Vue pieces are not in this lineage yet.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
