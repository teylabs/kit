<?php

// The finalize feature: steps computed from real state, recorded confirmations and their invalidation by fingerprint, the certified commit and its
// refusals (stale revision, incomplete, uncertified), scoped read-only, un-finalize with a reason and the optional extra rule, lock_version, tenant
// isolation, permissions and the per-subject check, descriptors, the HTTP routes and the MCP channel. SQLite memory, real Concierge, spatie teams and
// Pennant, synthetic users.
use Carbon\CarbonImmutable;
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
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\NotificationServiceProvider;
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
use Illuminate\Support\Facades\URL;
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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Deliver\Deliveries;
use Tey\Kit\Features\Deliver\Delivery;
use Tey\Kit\Features\Deliver\DeliveryAttachment;
use Tey\Kit\Features\Deliver\DeliveryMail;
use Tey\Kit\Features\Deliver\Events\DeliveryBounced;
use Tey\Kit\Features\Deliver\Events\DeliveryCancelled;
use Tey\Kit\Features\Deliver\Http\CustomerLinkController;
use Tey\Kit\Features\Deliver\Integrations\Mcp\SendDelivery;
use Tey\Kit\Features\Deliver\Models\DeliveryRecipient;
use Tey\Kit\Features\Deliver\Models\DeliveryRecord;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Finalize\Events\Finalized;
use Tey\Kit\Features\Finalize\Flow;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;
use Tey\Kit\Features\Finalize\Step;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Mail\FluentMailMessage;
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

// The app's Notification: built on FluentMailMessage from what the kit hands it, the same for the preview and the send.
final class ReportReady extends BaseNotification
{
    public function __construct(private readonly DeliveryMail $mail) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): FluentMailMessage
    {
        $message = (new FluentMailMessage)->subject($this->mail->subject)->line($this->mail->message);
        $message->line($this->mail->attachment === 'linked' ? 'The report is too large to attach: open it from the link.' : 'The report is attached.');

        return $message->action('Open the report', $this->mail->link ?? '#');
    }
}

// A transport whose provider never answers: the send may or may not have left.
final class BoomTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        throw new RuntimeException('provider timed out');
    }

    public function __toString(): string
    {
        return 'boom';
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
    $base = sys_get_temp_dir().'/kit-deliver-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    // Blade's mail components ask the app for its namespace.
    file_put_contents($base.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
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
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui', 'mcp', 'notes', 'finalize', 'deliver'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true, 'workspace.finalize' => true, 'workspace.deliver' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array'], 'boom' => ['transport' => 'boom']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit'], 'markdown' => ['theme' => 'default', 'paths' => []]],
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
        BusServiceProvider::class, MailServiceProvider::class, NotificationServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class,
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

    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('report_id');
        $table->string('name');
        $table->string('email');
        $table->string('role')->default('to');
        $table->boolean('active')->default(true);
    });
    app('mail.manager')->extend('boom', fn () => new BoomTransport);
    $sent = fn (): array => app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->all();

    // The finalize flow the delivery is gated on: one check, and a snapshot of the customer's printed name.
    $flows = $app->make(Flows::class);
    $flows->register(Flow::define('report.finalize')
        ->subject('report', fn (string $key, bool $lock): ?Report => Report::query()->where('workspace_id', Workspaces::required()->getKey())->whereKey((int) $key)->when($lock, fn ($q) => $q->lockForUpdate())->first())
        ->steps([Step::check('amount', 'Amount entered', fn (Report $r) => $r->amount !== null)])
        ->statement(fn (): array => ['version' => 'v1', 'text' => 'I certify this report.'])
        ->snapshot(fn (Report $r): array => ['customer.name' => DB::table('contacts')->where('report_id', $r->id)->orderBy('id')->value('name')]));

    $gate = app(FinalizeGate::class);
    $big = false;
    $version = 'v1';
    $deliveries = $app->make(Deliveries::class);
    check(refusal(fn () => $deliveries->register(Delivery::define('x')->subject('report', fn () => null)->notification(fn () => null)->recipients(fn () => []))) !== null, 'a delivery needs a gate: finalizeFlow() or gate()');
    $deliveries->register(Delivery::define('report.deliver')
        ->subject('report', fn (string $key, bool $lock): ?Report => Report::query()->where('workspace_id', Workspaces::required()->getKey())->whereKey((int) $key)->when($lock, fn ($q) => $q->lockForUpdate())->first())
        ->notification(fn (Report $r, DeliveryMail $mail) => new ReportReady($mail))
        // The printed name comes from the finalize snapshot while finalized, never from the live contact.
        ->attachment(function (Report $r) use (&$big, $gate): DeliveryAttachment {
            $name = $gate->snapshot('report.finalize', $r)['customer.name'] ?? DB::table('contacts')->where('report_id', $r->id)->value('name');

            return new DeliveryAttachment('report-'.$r->id.'.pdf', 'application/pdf', '%PDF report for '.$name.($big ? str_repeat('x', 2048) : ''));
        })
        ->maxAttachmentBytes(1024)
        ->recipients(fn (Report $r): array => DB::table('contacts')->where('report_id', $r->id)->where('active', true)->get()->map(fn ($c): array => ['address' => $c->email, 'name' => $c->name, 'role' => $c->role])->all())
        ->internalCopies(fn (): array => ['office@example.test'])
        ->defaults(fn (Report $r): array => ['subject' => 'Your report: '.$r->title, 'message' => 'Here is your report.'])
        ->finalizeFlow('report.finalize')
        ->version(function () use (&$version): string {
            return $version;
        })
        ->linkDays(14)
        ->url(fn (Report $r): string => '/reports/'.$r->id));

    $desk = app(DeliveryDesk::class);
    $k = fn (): string => bin2hex(random_bytes(8));
    $report = $in($workspace, fn () => Report::create(['workspace_id' => $workspace->id, 'title' => 'Main St', 'amount' => 100]));
    $key = (string) $report->id;
    DB::table('contacts')->insert([
        ['report_id' => $report->id, 'name' => 'Robin Vale', 'email' => 'robin@client.test', 'role' => 'to', 'active' => true],
        ['report_id' => $report->id, 'name' => 'Sam Lee', 'email' => 'sam@client.test', 'role' => 'cc', 'active' => true],
        ['report_id' => $report->id, 'name' => 'Old Contact', 'email' => 'old@client.test', 'role' => 'to', 'active' => false],
    ]);
    $state = fn (string $who = 'owner') => $in($workspace, fn () => $desk->state($as($who), 'report.deliver', $key));
    $draft = ['to' => ['Robin@Client.test'], 'cc' => ['sam@client.test'], 'bcc' => ['office@example.test'], 'subject' => 'Your report: Main St', 'message' => 'Here is <b>your</b> report.'];
    $rows = fn (): int => $in($workspace, fn () => DeliveryRecord::query()->count());

    // Gated on finalize.
    $s = $state();
    check(! $s->sendable && $s->reason === 'Finalize it first.' && $s->send?->disabled === true && $s->send->reason === 'Finalize it first.', 'before finalizing, Send is offered disabled with the gate\'s reason');
    check(array_column($s->recipientOptions, 'address') === ['robin@client.test', 'sam@client.test'] && $s->internalCopies === ['office@example.test'] && $s->defaultSubject === 'Your report: Main St', 'the state carries the live contacts (not the deactivated one), the internal copies and the defaults');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, $draft, $k()))) === 'deliver_gated' && $rows() === 0, 'sending before finalizing is refused and writes nothing');
    $fs = $in($workspace, fn () => $gate->state($as('owner'), 'report.finalize', $key));
    $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $key, $fs->progress->revision, true));

    // Preview is the send.
    $s = $state();
    $preview = $in($workspace, fn () => $desk->preview($as('viewer'), 'report.deliver', $key, $draft));
    check($s->sendable && $preview->subject === 'Your report: Main St' && str_contains($preview->html, 'Here is &lt;b&gt;your&lt;/b&gt; report.') && ! str_contains($preview->html, '<b>your</b>'), 'the preview renders the app\'s Notification, with the edited message escaped');
    check($preview->attachment === 'attached' && $preview->attachmentName === 'report-'.$report->id.'.pdf' && str_contains($preview->html, 'The report is attached.') && str_contains($preview->html, 'href="#"') && $rows() === 0 && $sent() === [], 'the preview says the document is attached, its button goes nowhere, and nothing is written or sent');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'to' => ['old@client.test']], $k()))) === 'deliver_recipient_unknown' && $rows() === 0, 'a contact who was deactivated is refused, not silently dropped');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'to' => []], $k()))) === 'deliver_recipients_required', 'a send needs at least one To');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'to' => [], 'to_other' => ['not-an-email']], $k()))) === 'deliver_address_invalid' && $rows() === 0, 'a typed To must be an email address');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'to' => ['old@client.test'], 'to_other' => ['new@client.test']], $k()))) === 'deliver_recipient_unknown', 'a typed address never launders a deactivated contact picked as a contact');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('viewer'), 'report.deliver', $key, $s->revision, $draft, $k()))) === 'permission_denied', 'a viewer may preview but not send');
    check(refusal(fn () => $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, 'old', $draft, $k()))) === 'deliver_stale' && $rows() === 0, 'a stale revision is refused and writes nothing');

    // Send now.
    $claim = $k();
    $s = $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, $draft, $claim));
    $messages = $sent();
    $email = $messages[0]->getOriginalMessage();
    $record = $in($workspace, fn () => DeliveryRecord::query()->sole());
    $html = (string) $email->getHtmlBody();
    // The customer link differs between preview (`#`) and send: in the button's href and in the subcopy's visible fallback link.
    // Links differ, and so does the fallback line under the button: the sent email has one for its real link, the preview ("#") has none.
    $strip = fn (string $h): string => preg_replace(['/<table class="subcopy"[\s\S]*?<\/table>/', '/href="[^"]*"/', '/>(?:https?:\/\/[^<]*|#)<\/a>/'], ['', 'href=""', '></a>'], $h);
    check(count($messages) === 1 && array_map(fn ($a) => $a->getAddress(), $email->getTo()) === ['robin@client.test'] && array_map(fn ($a) => $a->getAddress(), $email->getCc()) === ['sam@client.test'] && array_map(fn ($a) => $a->getAddress(), $email->getBcc()) === ['office@example.test'], 'one email: To and Cc are the chosen contacts, the internal copy is Bcc');
    check($strip($html) === $strip($preview->html) && $email->getSubject() === $preview->subject, 'the sent email is the preview, but for the customer link');
    check(count($email->getAttachments()) === 1 && str_contains($email->getAttachments()[0]->getBody(), 'Robin Vale') && $record->attachment === 'attached', 'the document is attached');
    check($record->state === 'sent' && $record->provider_message_id === $messages[0]->getMessageId() && $record->finalization_id !== null && $record->version === 'v1' && $record->channel === 'web', 'the delivery records sent, the provider message id, the finalization and version it sent, and the channel');
    check(strlen((string) $record->token_hash) === 64 && $record->token_expires_at->diffInDays(now(), true) > 13 && ! str_contains(json_encode(DB::table('kit_deliveries')->get()), substr((string) preg_replace('/.*\/d\/\d+\/([a-f0-9]{64}).*/s', '$1', $html), 0, 64)), 'the customer link\'s token is stored only as a hash, for the definition\'s 14 days');
    check($in($workspace, fn () => DeliveryRecipient::query()->orderBy('id')->pluck('status', 'address')->all()) === ['robin@client.test' => 'sent', 'sam@client.test' => 'sent', 'office@example.test' => 'sent'], 'each address is recorded, with its role, as sent');
    check($s->deliveries[0]->state === 'sent' && $s->deliveries[0]->stillFinal === true && $s->deliveries[0]->linkActive && collect($s->deliveries[0]->actions)->pluck('key')->all() === ['resend-'.$record->id, 'revoke-'.$record->id], 'the history shows it sent, still final, with its link live, and offers Resend and Revoke link');
    $again = $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, 'whatever', $draft, $claim));
    check(count($sent()) === 1 && $rows() === 1, 'the same claim key again is the same request: no second email');

    // The customer's link.
    preg_match('/href="(https:\/\/kit\.test\/d\/[^"]+)"/', $html, $match);
    $link = html_entity_decode($match[1] ?? '');
    // The link's refusals, from the controller itself: the harness has no root view to render a 404 page with.
    $refused = function (string $url) use ($app): ?int {
        $request = Request::create($url);
        $app->instance('request', $request);
        preg_match('#/d/(\d+)/([^?]+)#', $url, $parts);
        try {
            (new CustomerLinkController)->show($request, (int) ($parts[1] ?? 0), (string) ($parts[2] ?? ''));
        } catch (HttpExceptionInterface $error) {
            return $error->getStatusCode();
        }

        return null;
    };
    $guest = function (string $url) use ($app): array {
        Auth::guard('web')->forgetUser();
        $request = Request::create($url, 'GET');
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(ExceptionHandler::class)->render($request, $error);
        }

        return [$response->getStatusCode(), $response];
    };
    [$status, $response] = $guest($link);
    check($status === 200 && str_contains((string) $response->getContent(), '%PDF report for Robin Vale') && str_contains((string) $response->headers->get('Cache-Control'), 'no-store') && $response->headers->get('X-Robots-Tag') === 'noindex, nofollow' && $response->headers->get('Referrer-Policy') === 'no-referrer', 'the signed link shows the document, never cached, not indexed, sending no referrer');
    check($in($workspace, fn () => DeliveryRecord::query()->value('first_opened_at')) !== null, 'the first open is recorded (no tracking pixel: the email itself is not tracked)');
    DB::table('contacts')->where('email', 'robin@client.test')->update(['name' => 'Robin Vale-Okafor']);
    [, $response] = $guest($link);
    check(str_contains((string) $response->getContent(), 'Robin Vale') && ! str_contains((string) $response->getContent(), 'Okafor'), 'after the contact is renamed, the document still prints the finalized name');
    // A wrong token behind a valid signature, and the right token with its signature stripped.
    $tampered = $refused(URL::temporarySignedRoute('kit.deliver.view', now()->addDay(), ['delivery' => $record->id, 'token' => str_repeat('a', 64)]));
    $unsigned = $refused(preg_replace('/\?.*/', '', $link));
    check($tampered === 404 && $unsigned === 404, 'a wrong token, even correctly signed, or a link without its signature is a 404');

    // Provider webhooks.
    $bounces = [];
    Event::listen(DeliveryBounced::class, function (DeliveryBounced $e) use (&$bounces): void {
        $bounces[] = $e->recipient->address;
    });
    check($desk->recordEvent($record->provider_message_id, 'SAM@client.test', 'bounced', 'ev-1') && $in($workspace, fn () => DeliveryRecipient::query()->where('address', 'sam@client.test')->value('status')) === 'bounced' && $bounces === ['sam@client.test'], 'a bounce is recorded for that address and dispatches DeliveryBounced, so the app can tell the sender');
    check(! $desk->recordEvent($record->provider_message_id, 'sam@client.test', 'bounced', 'ev-1') && count($bounces) === 1, 'the same webhook twice is recorded once');
    check($desk->recordEvent($record->provider_message_id, 'sam@client.test', 'delivered', 'ev-2') && $in($workspace, fn () => DeliveryRecipient::query()->where('address', 'sam@client.test')->value('status')) === 'bounced', 'a late "delivered" does not undo a bounce');
    check(! $desk->recordEvent('<nobody@kit>', 'sam@client.test', 'delivered', 'ev-3') && ! $desk->recordEvent($record->provider_message_id, 'stranger@client.test', 'delivered', 'ev-4'), 'an unknown message or an address not on it changes nothing');

    // Revoke, and resend.
    $s = $state();
    $in($workspace, fn () => $desk->revoke($as('owner'), 'report.deliver', $key, $record->id, $s->revision));
    $revoked = $refused($link);
    check($revoked === 404, 'a revoked link is a 404');
    $s = $state();
    $s = $in($workspace, fn () => $desk->resend($as('admin'), 'report.deliver', $key, $record->id, $s->revision, $draft, $k(), channel: 'mcp'));
    $second = $in($workspace, fn () => DeliveryRecord::query()->latest('id')->first());
    check($second->resent_from_id === $record->id && $second->state === 'sent' && $second->channel === 'mcp' && count($sent()) === 2, 'resend is a new delivery pointing at the original, here through an assistant (mcp channel)');

    // A test send.
    $s = $state();
    $in($workspace, fn () => $desk->send($as('admin'), 'report.deliver', $key, $s->revision, ['subject' => 'Your report'], $k(), test: true));
    $test = $sent()[2]->getOriginalMessage();
    $testRow = $in($workspace, fn () => DeliveryRecord::query()->latest('id')->first());
    check(array_map(fn ($a) => $a->getAddress(), $test->getTo()) === ['admin@example.test'] && $test->getCc() === [] && $test->getBcc() === [] && str_starts_with((string) $test->getSubject(), '[Test] ') && $testRow->test && $testRow->token_hash === null, 'a test goes only to the sender, marked as a test, with no customer link');
    check(! collect($state()->deliveries)->firstWhere('id', $testRow->id)->actions, 'a test is never resent or revoked');

    // A document too large to attach is linked.
    $big = true;
    $s = $state();
    $preview = $in($workspace, fn () => $desk->preview($as('owner'), 'report.deliver', $key, $draft));
    $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, $draft, $k()));
    check($preview->attachment === 'linked' && str_contains($preview->html, 'too large to attach') && $sent()[3]->getOriginalMessage()->getAttachments() === [] && $in($workspace, fn () => DeliveryRecord::query()->latest('id')->value('attachment')) === 'linked', 'a document larger than the cap is linked, not attached, in the preview and the send alike');
    $big = false;

    // A provider that never answers: unknown, and never sent again.
    $config->set('mail.default', 'boom');
    $s = $state();
    $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, $draft, $k()));
    $config->set('mail.default', 'array');
    $boom = $in($workspace, fn () => DeliveryRecord::query()->latest('id')->first());
    check($boom->state === 'unknown' && str_contains((string) $boom->error, 'did not confirm') && collect($state()->deliveries)->firstWhere('id', $boom->id)->note !== null, 'a send the provider never confirmed is unknown, with a note for a person');
    $desk->sweep(CarbonImmutable::now()->addDay());
    check($in($workspace, fn () => DeliveryRecord::query()->find($boom->id)->state) === 'unknown' && count($sent()) === 4, 'the sweep never sends an unknown one again');

    // Scheduled sends.
    $cancels = [];
    Event::listen(DeliveryCancelled::class, function (DeliveryCancelled $e) use (&$cancels): void {
        $cancels[] = [$e->reason, $e->automatic];
    });
    $schedule = function (string $when) use ($state, $in, $workspace, $desk, $as, $key, $draft, $k): DeliveryRecord {
        $s = $state();
        $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'send_at' => $when], $k()));

        return $in($workspace, fn () => DeliveryRecord::query()->latest('id')->first());
    };
    check(refusal(fn () => $schedule(CarbonImmutable::now()->subHour()->toIso8601String())) === 'deliver_send_at_invalid', 'a send time in the past is refused');
    $later = $schedule(CarbonImmutable::now()->addHour()->toIso8601String());
    check($later->state === 'scheduled' && $later->token_hash === null && count($sent()) === 4 && collect($state()->deliveries)->firstWhere('id', $later->id)->actions[0]->key === 'cancel-'.$later->id, 'a scheduled send is a row: nothing leaves, and it can be cancelled');
    check($desk->sweep()['sent'] === 0 && count($sent()) === 4, 'the sweep leaves it until it is due');
    check($desk->sweep(CarbonImmutable::now()->addHours(2))['sent'] === 1 && $desk->sweep(CarbonImmutable::now()->addHours(2))['sent'] === 0 && count($sent()) === 5 && $in($workspace, fn () => DeliveryRecord::query()->find($later->id)->state) === 'sent', 'when due the sweep claims and sends it once; a second sweep finds nothing to claim');
    DB::table('kit_deliveries')->where('id', $later->id)->update(['state' => 'scheduled']);
    $claimed = DB::table('kit_deliveries')->where('id', $later->id)->where('state', 'scheduled')->update(['state' => 'sending', 'claimed_at' => now()]);
    $lost = DB::table('kit_deliveries')->where('id', $later->id)->where('state', 'scheduled')->update(['state' => 'sending', 'claimed_at' => now()]);
    check($claimed === 1 && $lost === 0, 'two workers claiming the same due row: only one wins');
    DB::table('kit_deliveries')->where('id', $later->id)->update(['claimed_at' => CarbonImmutable::now()->subMinutes(30)]);
    check($desk->sweep()['unknown'] === 1 && $in($workspace, fn () => DeliveryRecord::query()->find($later->id)->state) === 'unknown' && count($sent()) === 5, 'a row stuck mid-send becomes unknown and is not sent again');

    $changed = $schedule(CarbonImmutable::now()->addHour()->toIso8601String());
    $version = 'v2';
    $desk->sweep(CarbonImmutable::now()->addHours(2));
    $version = 'v1';
    check($in($workspace, fn () => DeliveryRecord::query()->find($changed->id)->state) === 'cancelled' && end($cancels) === ['The document changed after it was scheduled.', true] && count($sent()) === 5, 'a document that changed before its time cancels the send, and DeliveryCancelled says why');

    $leaver = $schedule(CarbonImmutable::now()->addHour()->toIso8601String());
    $roles = app(PermissionRegistrar::class)->getRoleClass();
    // The sender leaves the workspace before the time (their membership is suspended); a fresh request, as the scheduler's own process has.
    $membership = fn (MembershipStatus $status) => ConciergeModels::membership()::query()->where('workspace_id', $workspace->id)->where('user_id', $people['owner']->id)->update(['status' => $status]);
    $membership(MembershipStatus::Suspended);
    $app->instance('request', Request::create('https://kit.test'));
    $desk->sweep(CarbonImmutable::now()->addHours(2));
    $membership(MembershipStatus::Active);
    $app->instance('request', Request::create('https://kit.test'));
    check($in($workspace, fn () => DeliveryRecord::query()->find($leaver->id)->state) === 'cancelled' && end($cancels) === ['The person who scheduled it can no longer send.', true], 'a sender who lost access before the time cancels it, so the app can tell the owner and them');

    $manual = $schedule(CarbonImmutable::now()->addHour()->toIso8601String());
    $s = $in($workspace, fn () => $desk->cancel($as('admin'), 'report.deliver', $key, $manual->id, $state()->revision));
    check(collect($s->deliveries)->firstWhere('id', $manual->id)->state === 'cancelled' && end($cancels) === ['Cancelled.', false], 'a person cancels a scheduled send');
    check(refusal(fn () => $in($workspace, fn () => $desk->cancel($as('admin'), 'report.deliver', $key, $record->id, $state()->revision))) === 'deliver_not_cancellable', 'a sent delivery cannot be cancelled');

    $pending = $schedule(CarbonImmutable::now()->addHour()->toIso8601String());
    $fs = $in($workspace, fn () => $gate->state($as('owner'), 'report.finalize', $key));
    $in($workspace, fn () => $gate->unfinalize($as('owner'), 'report.finalize', $key, $fs->progress->revision, 'Wrong figure'));
    check($in($workspace, fn () => DeliveryRecord::query()->find($pending->id)->state) === 'cancelled' && end($cancels) === ['Un-finalized before it was sent.', true], 'un-finalizing cancels the subject\'s scheduled sends');
    $s = $state();
    check(! $s->sendable && collect($s->deliveries)->firstWhere('id', $record->id)->stillFinal === false, 'and the history shows what was sent is no longer final');

    // A customer whose contact has no email on file: a typed To address is sent to, and recorded.
    $fs = $in($workspace, fn () => $gate->state($as('owner'), 'report.finalize', $key));
    $in($workspace, fn () => $gate->finalize($as('owner'), 'report.finalize', $key, $fs->progress->revision, true));
    $before = count($sent());
    $s = $state();
    $in($workspace, fn () => $desk->send($as('owner'), 'report.deliver', $key, $s->revision, [...$draft, 'to' => [], 'cc' => [], 'to_other' => ['Pat@Elsewhere.test']], $k()));
    $typed = $sent()[$before]->getOriginalMessage();
    check(array_map(fn ($a) => $a->getAddress(), $typed->getTo()) === ['pat@elsewhere.test'] && $in($workspace, fn () => DeliveryRecipient::query()->where('address', 'pat@elsewhere.test')->value('role')) === 'to', 'a typed To address (any valid email) is sent to and recorded as To');

    // Tenant isolation.
    check(refusal(fn () => $in($beta, fn () => $desk->state($as('outsider'), 'report.deliver', $key))) === 'deliver_subject_not_found' && $in($beta, fn () => DeliveryRecord::query()->count() + DeliveryRecipient::query()->count()) === 0, 'another workspace reads neither the subject nor its deliveries');
    check(refusal(fn () => $in($beta, fn () => (new DeliveryRecord)->forceFill(['workspace_id' => $workspace->id, 'definition' => 'x', 'subject_key' => '1', 'state' => 'sent', 'claim_key' => 'z', 'created_by' => 1, 'channel' => 'web', 'created_at' => now(), 'subject_line' => 's', 'message' => 'm'])->save())) === 'workspace_mismatch', 'a delivery cannot be written into another workspace');
    check(refusal(fn () => $in($workspace, fn () => $record->delete())) === 'deliver_history_is_kept', 'a delivery row cannot be deleted');

    // HTTP and MCP.
    $base = '/@acme/deliver/report.deliver/'.$key;
    [$status, , $body] = $http('viewer', 'GET', $base);
    [$previewStatus, , $previewBody] = $http('viewer', 'POST', $base.'/preview', $draft);
    [$sendStatus] = $http('viewer', 'POST', $base.'/send', [...$draft, 'revision' => $state()->revision, 'claim_key' => $k()]);
    [$staleStatus] = $http('owner', 'POST', $base.'/send', [...$draft, 'revision' => 'old', 'claim_key' => $k()]);
    [$pdfStatus, $pdf] = $http('viewer', 'GET', $base.'/attachment');
    check($status === 200 && json_decode($body, true)['revision'] === $state('viewer')->revision && $previewStatus === 200 && str_contains(json_decode($previewBody, true)['html'], 'Here is &lt;b&gt;your&lt;/b&gt; report.'), 'GET answers the state and POST preview the email, as JSON');
    check($sendStatus === 403 && $staleStatus === 409 && $pdfStatus === 200 && str_contains((string) $pdf->headers->get('Cache-Control'), 'no-store'), 'over HTTP a viewer cannot send, a stale send is 409, and the document for the preview is never cached');
    check(app(Registry::class)->capabilityFor('deliver.send') === 'workspace.deliver' && (new ReflectionClass(SendDelivery::class))->getAttributes(Capability::class)[0]->newInstance()->destructive, 'the MCP send tool is on the capability and asks for confirmation in the chat');

    echo "Deliver checks on SQLite memory with real Concierge, spatie teams and Pennant, the array mailer and a failing transport; HTTP through router dispatch without the global middleware stack. MCP tools are checked by registration and the operation's mcp channel, not over the MCP transport. The Vue pieces are checked in the browser harness (?deliver), not against this server. Provider webhooks are exercised through recordEvent(); mapping a real provider's payload is the app's.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
