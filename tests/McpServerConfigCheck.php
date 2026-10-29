<?php

// The workspace MCP server's name and instructions: the kit's by default, the host's from kit.mcp.server.name and .instructions. No HTTP, no transport traffic.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Laravel\Mcp\Server\Transport\FakeTransporter;
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
    mcpServerCheck(config('kit.mcp.server') === ['name' => null, 'instructions' => null], 'the config keys exist, unset');

    config(['kit.mcp.server.name' => 'Acme workspace', 'kit.mcp.server.instructions' => 'Manage Acme decks and their slides.']);
    $named = $context();
    mcpServerCheck($named->implementation->name === 'Acme workspace' && $named->instructions === 'Manage Acme decks and their slides.', 'the host names the server and words its instructions');

    config(['kit.mcp.server.name' => '  ', 'kit.mcp.server.instructions' => null]);
    $blank = $context();
    mcpServerCheck($blank->implementation->name === 'Workspace operations' && str_starts_with($blank->instructions, 'Read and update'), 'a blank or null value keeps the default');

    echo "MCP server config checks: the server context only; no transport traffic.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
