<?php

// The kit's request start runs first in `web`. A host booted as Laravel boots one (Application::configure, the framework's own `web` group with
// StartSession and SubstituteBindings, the real HTTP kernel), whose own middleware is appended to `web` before the kit boots: a tenant resolver that
// looks up a membership twice, and a share that wraps the kit's `shell` (reading Inertia::getShared('shell') in the middleware). Through
// $kernel->handle(): the second membership lookup in the host's middleware adds no query (the request memo is already on), after
// Inertia::flushShared() the host's wrapper captures the kit's shell, not null, and a route-model-binding 404 rendered by the exception handler after a
// flush still has the kit's shell. Then the host still wins (a `shell` it shares on the factory), and two requests in turn share no memo: a membership
// removed between them (with no Eloquent event to empty the memo) is gone on the second.
// Synthetic app in a temp directory, SQLite memory, synthetic users and workspace; no network, no browser.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\HasApiTokens;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\PrepareKitRequest;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\KitServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function orderCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class OrderUser extends Authenticatable implements Member
{
    use HasApiTokens, Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

// The kit's concierge defaults name the host's App\Models\User.
class_alias(OrderUser::class, 'App\Models\User');

/** The host's tenant resolver, in `web` ahead of the kit's late middleware: it looks the membership up twice, as a resolver and a policy would. */
class HostTenantResolver
{
    /** @var list<array{first: ?bool, second: int, active: ?bool}> */
    public static array $seen = [];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = OrderUser::query()->where('email', 'ada@example.test')->first();
        $workspace = Workspace::query()->where('slug', 'northwind')->first();
        $first = RequestMemo::membership($user, $workspace);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $second = RequestMemo::membership($user, $workspace);
        $count = $queries;
        self::$seen[] = ['first' => $first?->isActive(), 'second' => $count, 'active' => $second?->isActive()];

        return $next($request);
    }
}

/** A host share that wraps the kit's `shell`, reading it in the middleware, as a host adding its own badge to the kit's shell would. */
class HostShellWrapper
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->query('wrap') === '1') {
            $kit = Inertia::getShared('shell');
            Inertia::share('shell', fn (): array => ['kit' => $kit instanceof Closure ? $kit() : $kit, 'badge' => 'host']);
        }

        return $next($request);
    }
}

class OrderHostProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/page', fn () => Inertia::render('Probe'));
        Route::middleware('web')->get('/places/{workspace}', fn (Workspace $workspace) => Inertia::render('Probe', ['name' => $workspace->name]));
    }
}

try {
    $root = sys_get_temp_dir().'/kit-request-order-'.bin2hex(random_bytes(8));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.json', '{"name": "example/host", "autoload": {"psr-4": {"App\\\\": "app/"}}}');
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => ['core', 'workspaces', 'access', 'shell', 'session-ui'], 'models' => ['user' => OrderUser::class]], true).';');
    file_put_contents($root.'/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]];");
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['name' => 'Northwind Farms', 'env' => 'testing', 'debug' => false, 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/auth.php', '<?php return '.var_export(['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => OrderUser::class]]], true).';');
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => Monolog\\Handler\\NullHandler::class]]];");
    // The host's own middleware joins `web` in bootstrap/app.php, before the kit boots; a 404 renders as an Inertia page through the exception handler.
    $app = Application::configure($root)
        ->withMiddleware(fn ($middleware) => $middleware->web(append: [HostTenantResolver::class, HostShellWrapper::class]))
        ->withExceptions(fn ($exceptions) => $exceptions->render(fn (NotFoundHttpException $error, Request $request) => Inertia::render('Errors/NotFound')->toResponse($request)->setStatusCode(404)))
        ->withProviders([\Inertia\ServiceProvider::class, LaravelDataServiceProvider::class, KitServiceProvider::class, FeaturesServiceProvider::class, OrderHostProvider::class])
        ->create();
    $app->make(ConsoleKernel::class)->bootstrap();
    $kernel = $app->make(Kernel::class);
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    foreach ([...glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php'), ...glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php')] as $file) {
        (require $file)->up();
    }
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $ada = OrderUser::create(['name' => 'Ada Example', 'email' => 'ada@example.test', 'email_verified_at' => now(), 'password' => 'x']);
    $workspace = Workspace::create(['name' => 'Northwind', 'slug' => 'northwind']);
    ConciergeModels::membership()::query()->create(['workspace_id' => $workspace->id, 'user_id' => $ada->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

    $group = $app['router']->getMiddlewareGroups()['web'];
    orderCheck(($group[0] ?? null) === StartKitRequest::class && array_search(PrepareKitRequest::class, $group, true) > array_search(HostShellWrapper::class, $group, true), 'StartKitRequest is first in the router\'s `web` group, and PrepareKitRequest still follows the host\'s middleware ('.implode(', ', array_map('class_basename', $group)).')');
    orderCheck(($app['router']->middlewarePriority[0] ?? null) === StartKitRequest::class, 'StartKitRequest is first in the middleware priority list, so no host middleware sorts ahead of it');
    // Core registers it and session-ui must not again, under either name (the old session-ui name is an alias of core's class, so a string match alone misses it).
    $starts = fn (array $list): int => count(array_filter($list, fn (mixed $m): bool => is_string($m) && is_a($m, StartKitRequest::class, true)));
    orderCheck($starts($group) === 1 && $starts($app['router']->middlewarePriority) === 1, 'StartKitRequest is registered once with session-ui adopted: '.$starts($group).' in `web`, '.$starts($app['router']->middlewarePriority).' in the priority list');

    $page = function (string $path) use ($kernel): array {
        $request = Request::create('https://kit.test'.$path, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body) || ! isset($body['props'])) {
            throw new RuntimeException('GET '.$path.' answered '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 300));
        }

        return ['status' => $response->getStatusCode(), 'component' => $body['component'] ?? null, 'props' => $body['props']];
    };

    // 1. The request memo is on before the host's own middleware runs.
    $page('/page');
    $seen = HostTenantResolver::$seen[0] ?? null;
    orderCheck($seen !== null && $seen['first'] === true && $seen['active'] === true && $seen['second'] === 0, 'memo: the host\'s tenant resolver, ahead of the kit\'s late middleware, finds the membership twice and the second lookup adds no query (queries: '.json_encode($seen['second'] ?? null).')');

    // 2. After a flush, a host share that wraps the kit's `shell` captures the kit's shell, not null.
    Inertia::flushShared();
    $wrapped = $page('/page?wrap=1')['props'];
    orderCheck(($wrapped['shell']['badge'] ?? null) === 'host' && ($wrapped['shell']['kit']['kind'] ?? null) === 'guest', 'wrap: after Inertia::flushShared(), the host\'s share wrapping Inertia::getShared(\'shell\') holds the kit\'s guest shell, not null ('.json_encode($wrapped['shell'] ?? null).')');

    // 3. A route-model-binding 404 (SubstituteBindings) rendered by the exception handler after a flush still has the kit's shell.
    Inertia::flushShared();
    $missing = $page('/places/999');
    orderCheck($missing['status'] === 404 && $missing['component'] === 'Errors/NotFound' && ($missing['props']['shell']['kind'] ?? null) === 'guest' && isset($missing['props']['kitUi']), 'binding 404: after a flush, the exception handler\'s 404 page still has the kit\'s shell and kitUi (status '.$missing['status'].', shell: '.json_encode($missing['props']['shell'] ?? null).')');
    $found = $page('/places/'.$workspace->id);
    orderCheck($found['status'] === 200 && ($found['props']['name'] ?? null) === 'Northwind', 'binding: a workspace that exists still binds and renders');

    // 4. The host still wins, and two requests in turn share no memo.
    Inertia::flushShared();
    Inertia::share('shell', fn (): array => ['kind' => 'host shell']);
    $host = $page('/page')['props'];
    orderCheck(($host['shell'] ?? null) === ['kind' => 'host shell'] && isset($host['kitUi'], $host['kitPassword']), 'host precedence: a `shell` the host shared on the factory before the request keeps the host\'s value, and the kit fills in the rest');
    Inertia::flushShared();
    HostTenantResolver::$seen = [];
    $page('/page');
    DB::table('concierge_memberships')->where('user_id', $ada->id)->delete();
    $page('/page');
    orderCheck((HostTenantResolver::$seen[0]['active'] ?? null) === true && array_key_exists('active', HostTenantResolver::$seen[1] ?? []) && HostTenantResolver::$seen[1]['active'] === null, 'no leak: a membership removed between two requests (no Eloquent event) is gone on the second, not answered from the first\'s memo ('.json_encode(HostTenantResolver::$seen).')');

    echo "Request order: a synthetic host through the real HTTP kernel on SQLite memory. No Octane worker, browser or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
