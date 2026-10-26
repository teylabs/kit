<?php

namespace Tey\Kit\Features\Mcp;

use Laravel\Mcp\Server;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Methods\CallTool;

final class WorkspaceServer extends Server
{
    protected string $name = 'Workspace operations';

    protected string $version = '0.1.0';

    protected string $instructions = 'Read and update the approved workspace settings. Writes require the current version and a stable intent key.';

    protected function boot(): void
    {
        $this->tools = app(Registry::class)->mcpTools();
        $this->addMethod('tools/call', CallTool::class);
    }
}
