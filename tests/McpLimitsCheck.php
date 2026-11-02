<?php

// /mcp storm guards: the per-token rate and in-flight caps answer 429 with Retry-After and free their slots; tools/list is reused per connection. Array cache, no HTTP.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Http\Middleware\LimitMcpRequests;
use Tey\Kit\Features\Mcp\Methods\ListTools;
use Tey\Kit\Features\Mcp\WorkspaceServer;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function mcpLimitsCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $root = sys_get_temp_dir().'/kit-mcp-limits-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    config(['cache.default' => 'array']);

    mcpLimitsCheck(config('kit.mcp.limits') === ['per_minute' => 120, 'per_token' => 4, 'total' => 0, 'retry_after' => 2, 'hold_seconds' => 30] && config('kit.mcp.list_cache_seconds') === 60, 'the defaults: 120 a minute and 4 in flight per token, no app-wide cap, 60s list reuse');

    $guard = new LimitMcpRequests;
    $request = fn (string $ip = '10.0.0.1'): Request => Request::create('/mcp', 'POST', server: ['REMOTE_ADDR' => $ip]);
    // A request in flight holds its slot while $next runs: nest calls to hold several at once.
    $nest = function (int $depth, string $ip = '10.0.0.1') use (&$nest, $guard, $request) {
        return $guard->handle($request($ip), fn () => $depth <= 1 ? response('ok') : $nest($depth - 1, $ip));
    };
    mcpLimitsCheck($nest(4)->getStatusCode() === 200, 'four requests in flight on one token pass');
    $fifth = $nest(5);
    mcpLimitsCheck($fifth->getStatusCode() === 429 && $fifth->headers->get('Retry-After') === '2' && json_decode($fifth->getContent(), true)['error']['code'] === -32000, 'a fifth in flight gets a JSON-RPC 429 with Retry-After 2');
    mcpLimitsCheck($nest(4)->getStatusCode() === 200, 'slots free when requests end, refused or not');
    $other = $guard->handle($request(), fn () => $guard->handle($request(), fn () => $guard->handle($request(), fn () => $guard->handle($request(), fn () => $nest(1, '10.0.0.3')))));
    mcpLimitsCheck($other->getStatusCode() === 200, 'the cap is per token: with one token full, another still gets through');

    config(['kit.mcp.limits.total' => 2, 'kit.mcp.limits.per_token' => 0]);
    $total = $guard->handle($request('10.0.0.4'), fn () => $guard->handle($request('10.0.0.5'), fn () => $guard->handle($request('10.0.0.6'), fn () => response('ok'))));
    mcpLimitsCheck($total->getStatusCode() === 429, 'the app-wide cap holds across tokens');
    config(['kit.mcp.limits.total' => 0, 'kit.mcp.limits.per_token' => 4]);

    $thrown = null;
    try {
        $guard->handle($request('10.0.0.7'), fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException $e) {
        $thrown = $e;
    }
    mcpLimitsCheck($thrown !== null && $nest(4, '10.0.0.7')->getStatusCode() === 200, 'a request that throws still frees its slot');

    config(['kit.mcp.limits.per_minute' => 3]);
    $codes = array_map(fn () => $guard->handle($request('10.0.0.8'), fn () => response('ok'))->getStatusCode(), range(1, 4));
    $last = $guard->handle($request('10.0.0.8'), fn () => response('ok'));
    mcpLimitsCheck($codes === [200, 200, 200, 429] && (int) $last->headers->get('Retry-After') >= 1, 'over per_minute a token gets 429 with a Retry-After of at least a second');

    // tools/list: reused per connection, rebuilt for another connection, not cached without a principal or with 0 seconds.
    $app->instance(Registry::class, $registry = $app->make(Registry::class));
    eval('final class KitLimitsTool extends Laravel\\Mcp\\Server\\Tool { public static int $listed = 0; protected string $name = "limits_tool"; protected string $description = "x"; public function schema(Illuminate\\Contracts\\JsonSchema\\JsonSchema $schema): array { return []; } public function shouldRegister(): bool { self::$listed++; return true; } public function handle(Laravel\\Mcp\\Request $request): Laravel\\Mcp\\Response { return Laravel\\Mcp\\Response::text("ok"); } }');
    $registry->tools(['KitLimitsTool']);
    $list = function (?Principal $principal) use ($app): array {
        $server = new WorkspaceServer(new FakeTransporter);
        (new ReflectionMethod($server, 'boot'))->invoke($server);
        $app['request']->attributes->set('mcp.principal', $principal);

        return (new ListTools)->handle(new JsonRpcRequest(1, 'tools/list', []), $server->createContext())->toArray()['result'];
    };
    $first = $list(new Principal(1, 10));
    $before = KitLimitsTool::$listed;
    $again = $list(new Principal(1, 10));
    mcpLimitsCheck($again === $first && KitLimitsTool::$listed === $before && in_array('limits_tool', array_column($first['tools'], 'name'), true), 'a connection\'s second tools/list is the cached answer, with no tool gated again');
    $list(new Principal(1, 11));
    mcpLimitsCheck(KitLimitsTool::$listed > $before, 'another connection builds its own list');
    $before = KitLimitsTool::$listed;
    $list(null);
    mcpLimitsCheck(KitLimitsTool::$listed > $before, 'without a bound connection the list is not cached');
    config(['kit.mcp.list_cache_seconds' => 0]);
    $before = KitLimitsTool::$listed;
    $list(new Principal(1, 10));
    mcpLimitsCheck(KitLimitsTool::$listed > $before, 'list_cache_seconds 0 turns the reuse off');

    echo "MCP limits checks: the middleware and list method only; no HTTP, no transport traffic.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
