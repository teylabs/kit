<?php

// Workspace mail identity over HTTP, on SQLite memory with synthetic users and a fake SendingDomains: no provider is called and no mail is sent.
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
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Mail\FluentMailMessage;
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
    $base = sys_get_temp_dir().'/kit-permissions-http-'.bin2hex(random_bytes(4));
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
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
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

    $call = function (string $who, string $method, string $path, array $input = [], bool $inertia = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $inertia ? ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_REFERER' => 'https://kit.test/@acme/settings/email'] : ['HTTP_ACCEPT' => 'application/json'];
        $request = Request::create('https://kit.test/@acme/settings/email'.$path, $method, $input, server: $server);
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
    // The identity a mail built in a workspace gets, via the one resolver every FluentMailMessage uses.
    $mail = function (Workspace $in, ?object $sender = null): array {
        return $in->execute(function () use ($sender): array {
            $message = new FluentMailMessage;
            if ($sender) {
                $message->sentBy($sender);
            }

            return ['from' => $message->from, 'reply_to' => $message->replyTo];
        });
    };

    // Defaults, with no row: the workspace's name, the platform address, replies to the sending member (or the platform's reply-to without one).
    $plain = (new FluentMailMessage)->from;
    httpCheck($plain === [] || $plain === null, 'outside a workspace a mail keeps the platform identity (nothing set on the message)');
    $m = $mail($workspace);
    httpCheck($m['from'] === ['platform@kit.test', 'Acme'] && $m['reply_to'] === [['support@kit.test', 'Kit support']], 'by default a workspace mail is from the workspace name at the platform address, replying to the platform');
    $m = $mail($workspace, $people['member']);
    httpCheck($m['reply_to'] === [['member@example.test', 'Member']], 'by default replies go to the member who sent it');

    [$status, $body] = $call('member', 'GET', '');
    httpCheck($status === 200 && $body['from_mode'] === 'platform' && $body['sender_name_default'] === 'Acme' && $body['effective_from'] === 'platform@kit.test' && $body['version'] === 1, 'a member can read Email sending, with its defaults');
    [$status] = $call('member', 'PUT', '', ['sender_name' => 'Hijack', 'from_mode' => 'platform', 'reply_to_mode' => 'member', 'expected_version' => 1]);
    httpCheck($status === 403 && $mail($workspace)['from'][1] === 'Acme', 'a member cannot change it, and nothing changes');
    [$status] = $call('outsider', 'GET', '');
    httpCheck($status === 404, 'a non-member cannot read it');

    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => "Acme\r\nBcc: x@evil.test", 'from_mode' => 'platform', 'reply_to_mode' => 'member', 'expected_version' => 1]);
    httpCheck($status === 422 && isset($body['errors']['sender_name']), 'a sender name with a line break (header injection) is refused');

    $fake->down = true;
    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => 'Acme Mail', 'from_mode' => 'domain', 'domain' => 'acme.example', 'reply_to_mode' => 'member', 'expected_version' => 1]);
    httpCheck($status === 422 && isset($body['errors']['domain']) && DB::table('workspace_mail_identities')->count() === 0, 'when the provider cannot add the domain the save is refused and nothing is stored');
    $fake->down = false;
    $fake->calls = [];

    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => 'Acme Mail', 'from_mode' => 'domain', 'domain' => 'Acme.Example', 'from_local' => 'reports', 'reply_to_mode' => 'member', 'expected_version' => 1]);
    httpCheck($status === 200 && $fake->calls === [['create', 'send.acme.example']] && $body['domain'] === 'send.acme.example' && $body['domain_status'] === 'pending' && count($body['dns_records']) === 2 && $body['version'] === 2, 'a custom domain is created at the provider on its send. subdomain, with its DNS records to copy');
    $m = $mail($workspace);
    httpCheck($m['from'] === ['platform@kit.test', 'Acme Mail'], 'until the domain is verified mail keeps the platform address, with the custom sender name');
    httpCheck($mail($other)['from'] === ['platform@kit.test', 'Beta'], 'another workspace\'s mail is untouched');

    [$status] = $call('admin', 'PUT', '', ['sender_name' => 'Stale', 'from_mode' => 'platform', 'reply_to_mode' => 'member', 'expected_version' => 1]);
    httpCheck($status === 409 && $mail($workspace)['from'][1] === 'Acme Mail', 'a stale save is refused with 409 and changes nothing');
    [$status, , $response] = $call('admin', 'PUT', '', ['sender_name' => 'Stale', 'from_mode' => 'platform', 'reply_to_mode' => 'member', 'expected_version' => 1], inertia: true);
    $bag = $app['session.store']->get('errors');
    httpCheck(in_array($status, [302, 303], true) && $bag && $bag->getBag('default')->has('stale_mail_identity'), 'on an Inertia visit a stale save goes back with errors.stale_mail_identity, like validation');

    $fake->next = 'pending';
    [$status, $body] = $call('admin', 'POST', '/domain-checks');
    httpCheck($status === 200 && end($fake->calls) === ['check', 'dom_1'] && $body['domain_status'] === 'pending' && $body['checked_at'] !== null, 'Check now asks the provider and records it is still pending');
    $fake->next = 'verified';
    [$status, $body] = $call('admin', 'POST', '/domain-checks');
    httpCheck($status === 200 && $body['domain_status'] === 'verified' && $body['effective_from'] === 'reports@send.acme.example', 'once verified the page says mail goes from the domain');
    httpCheck($mail($workspace)['from'] === ['reports@send.acme.example', 'Acme Mail'], 'and mail goes from it');
    [$status] = $call('member', 'POST', '/domain-checks');
    httpCheck($status === 403, 'a member cannot run Check now');

    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => '', 'from_mode' => 'domain', 'domain' => 'acme.example', 'from_local' => 'reports', 'reply_to_mode' => 'custom', 'reply_to_address' => 'desk@acme.example', 'expected_version' => 3 - 1]);
    httpCheck($status === 200 && count(array_filter($fake->calls, fn ($c) => $c[0] === 'create')) === 1, 'saving the same domain again does not create it twice');
    $m = $mail($workspace, $people['member']);
    httpCheck($m['from'] === ['reports@send.acme.example', 'Acme'] && $m['reply_to'] === [['desk@acme.example', 'Acme']], 'an empty sender name falls back to the workspace name; a custom reply-to wins over the member');
    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => '', 'from_mode' => 'platform', 'reply_to_mode' => 'fixed', 'expected_version' => $body['version']]);
    $m = $mail($workspace, $people['member']);
    httpCheck($status === 200 && $body['domain'] === 'send.acme.example' && $m['from'] === ['platform@kit.test', 'Acme'] && $m['reply_to'] === [['support@kit.test', 'Kit support']], 'switching back to the platform address keeps the domain for later; the fixed reply-to is the platform\'s');

    // The page posts only its own fields: a stray browser field is dropped and the save goes through; a bad value comes back inline on its field.
    $app['session.store']->forget('errors');
    [$status] = $call('admin', 'PUT', '', ['sender_name' => 'Acme Desk', 'from_mode' => 'platform', 'reply_to_mode' => 'fixed', 'expected_version' => $body['version'], 'stray_field' => 'x', 'remember' => '1'], inertia: true);
    httpCheck(in_array($status, [302, 303], true) && ! $app['session.store']->has('errors') && $mail($workspace)['from'][1] === 'Acme Desk', 'an Inertia save with a stray form field drops it and saves');
    $version = (int) DB::table('workspace_mail_identities')->where('workspace_id', $workspace->getKey())->value('version');
    [$status] = $call('admin', 'PUT', '', ['sender_name' => 'Acme Desk', 'from_mode' => 'platform', 'reply_to_mode' => 'custom', 'reply_to_address' => 'not-an-address', 'expected_version' => $version, 'stray_field' => 'x'], inertia: true);
    $bag = $app['session.store']->get('errors');
    httpCheck(in_array($status, [302, 303], true) && $bag && $bag->getBag('default')->has('reply_to_address') && ! $bag->getBag('default')->has('unknown_arguments'), 'a bad reply-to on an Inertia save goes back with errors.reply_to_address, not the 422 error page');
    $app['session.store']->forget('errors');
    $refused = null;
    try {
        $workspace->execute(fn () => $app->make(Tey\Kit\Features\Settings\Mail\WorkspaceMailIdentity::class)->update(new Tey\Kit\Features\Core\Principal((int) $people['admin']->getKey()), ['sender_name' => 'X', 'from_mode' => 'platform', 'reply_to_mode' => 'fixed', 'expected_version' => $version, 'stray_field' => 'x']));
    } catch (RuntimeException $error) {
        $refused = $error->getMessage();
    }
    httpCheck($refused === 'unknown_arguments', 'the operation itself still refuses an argument it does not name (unknown_arguments)');
    [$status, $body] = $call('admin', 'PUT', '', ['sender_name' => 'X', 'from_mode' => 'platform', 'reply_to_mode' => 'fixed', 'expected_version' => $version, 'stray_field' => 'x']);
    httpCheck($status === 422 && ($body['error'] ?? null) === 'unknown_arguments', 'a JSON caller sending an argument it does not name is still refused (422 unknown_arguments)');
    // The shared web mapper: a field-level operation error becomes a validation error on its field for a page visit; any other refusal, and any JSON request, is untouched.
    $mapped = function (Request $request, string $code, array $fields = []): string {
        try {
            Tey\Kit\Features\Core\Http\OperationForm::run($request, fn () => throw new RuntimeException($code), $fields);
        } catch (Illuminate\Validation\ValidationException $error) {
            return 'field:'.implode(',', array_keys($error->errors()));
        } catch (RuntimeException $error) {
            return 'code:'.$error->getMessage();
        }

        return 'none';
    };
    $visit = Request::create('https://kit.test/@acme/settings', 'PUT', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html']);
    $json = Request::create('https://kit.test/@acme/settings', 'PUT', server: ['HTTP_ACCEPT' => 'application/json']);
    httpCheck($mapped($visit, 'slug_taken') === 'field:slug' && $mapped($visit, 'slug_invalid') === 'field:slug', 'on a page visit slug_taken and slug_invalid become a validation error on slug');
    httpCheck($mapped($visit, 'stale_note', ['stale_note' => 'body']) === 'field:body', 'a form can name further field-level codes');
    httpCheck($mapped($visit, 'permission_denied') === 'code:permission_denied' && $mapped($visit, 'workspace_not_found') === 'code:workspace_not_found', 'permission_denied and workspace_not_found keep their own refusal');
    httpCheck($mapped($json, 'slug_taken') === 'code:slug_taken', 'a JSON request keeps the operation\'s code and status');

    httpCheck(Workspace::current() === null, 'no tenant context leaks out of the checks');
    echo "Workspace mail identity checks on SQLite memory: router dispatch, a fake provider, no mail sent.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
