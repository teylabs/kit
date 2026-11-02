<?php

// The workspace MCP server's name and instructions: the kit's by default, the host's from kit.mcp.server.name and .instructions. No HTTP, no transport traffic.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\WorkspaceServer;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function mcpServerCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $root = sys_get_temp_dir().'/kit-mcp-server-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    $context = function (): Laravel\Mcp\Server\ServerContext {
        $server = new WorkspaceServer(new FakeTransporter);
        (new ReflectionMethod($server, 'boot'))->invoke($server);

        return $server->createContext();
    };

    $default = $context();
    mcpServerCheck($default->implementation->name === 'Workspace operations' && str_starts_with($default->instructions, 'Read and update the approved workspace settings.'), 'by default the server keeps the kit\'s name and instructions');
    mcpServerCheck(config('kit.mcp.server') === ['name' => null, 'instructions' => null, 'page_size' => 50], 'the config keys exist: name and instructions unset, page_size 50');

    config(['kit.mcp.server.name' => 'Acme workspace', 'kit.mcp.server.instructions' => 'Manage Acme decks and their slides.']);
    $named = $context();
    mcpServerCheck($named->implementation->name === 'Acme workspace' && $named->instructions === 'Manage Acme decks and their slides.', 'the host names the server and words its instructions');

    config(['kit.mcp.server.name' => '  ', 'kit.mcp.server.instructions' => null]);
    $blank = $context();
    mcpServerCheck($blank->implementation->name === 'Workspace operations' && str_starts_with($blank->instructions, 'Read and update'), 'a blank or null value keeps the default');

    // One page holds every tool by default: thirty registered tools come back in one tools/list answer, with no cursor to follow.
    config(['kit.mcp.server.name' => null, 'kit.mcp.server.instructions' => null]);
    $tools = [];
    for ($i = 1; $i <= 30; $i++) {
        $class = 'KitPageTool'.$i;
        eval('final class '.$class.' extends Laravel\\Mcp\\Server\\Tool { protected string $name = "page_tool_'.$i.'"; protected string $description = "Tool '.$i.'"; public function schema(Illuminate\\Contracts\\JsonSchema\\JsonSchema $schema): array { return []; } public function handle(Laravel\\Mcp\\Request $request): Laravel\\Mcp\\Response { return Laravel\\Mcp\\Response::text("ok"); } }');
        $tools[] = $class;
    }
    // This minimal app adopts no feature, so nothing binds the Registry: hold one instance, as a host's FeaturesServiceProvider does.
    $app->instance(Registry::class, $registry = $app->make(Registry::class));
    $registry->tools($tools);
    $list = fn (Laravel\Mcp\Server\ServerContext $context): array => (new Laravel\Mcp\Server\Methods\ListTools)->handle(new Laravel\Mcp\Transport\JsonRpcRequest(1, 'tools/list', []), $context)->toArray()['result'];
    $page = $list($context());
    mcpServerCheck(count($page["tools"]) >= 30 && ! isset($page['nextCursor']) && in_array('page_tool_30', array_column($page['tools'], 'name'), true), 'by default tools/list returns all thirty tools in one page, with no nextCursor');
    config(['kit.mcp.server.page_size' => 10]);
    $page = $list($context());
    mcpServerCheck(count($page['tools']) === 10 && isset($page['nextCursor']), 'a host\'s page_size of 10 pages the list, with a cursor to follow');
    config(['kit.mcp.server.page_size' => 500]);
    mcpServerCheck($context()->perPage() === 50, 'a page_size above laravel/mcp\'s maximum is clamped to it (50)');
    config(['kit.mcp.server.page_size' => 'lots']);
    mcpServerCheck($context()->perPage() === 50, 'a page_size that is not a number falls back to 50');
    config(['kit.mcp.server.page_size' => 0]);
    mcpServerCheck($context()->perPage() === 1, 'a page_size below one is one');
    config(['kit.mcp.server.page_size' => 50]);

    echo "MCP server config checks: the server context only; no transport traffic.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
