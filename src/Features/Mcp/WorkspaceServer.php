<?php

namespace Tey\Kit\Features\Mcp;

use Laravel\Mcp\Server;
use Tey\Kit\Features\Core\Registry;

final class WorkspaceServer extends Server
{
    protected string $name = 'Workspace operations';

    protected string $version = '0.1.0';

    protected string $instructions = 'Read and update the approved workspace settings. Writes require the current version and a stable intent key.';

    protected function boot(): void
    {
        $this->tools = app(Registry::class)->mcpTools();
    }
}
