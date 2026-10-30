<?php

namespace Tey\Kit\Features\Mcp;

use Laravel\Mcp\Server;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\Methods\CallTool;

/** The workspace MCP server. A host names it for its own assistants with `kit.mcp.server.name` and `kit.mcp.server.instructions`; unset, the kit's defaults stand. */
final class WorkspaceServer extends Server
{
    protected string $name = 'Workspace operations';

    protected string $version = '0.1.0';

    protected string $instructions = 'Read and update the approved workspace settings. Writes require the current version and a stable intent key.';

    protected function boot(): void
    {
        $name = config('kit.mcp.server.name');
        $instructions = config('kit.mcp.server.instructions');
        if (is_string($name) && trim($name) !== '') {
            $this->name = $name;
        }
        if (is_string($instructions) && trim($instructions) !== '') {
            $this->instructions = $instructions;
        }
        // One page holds every tool by default: some assistants read the first list answer only, so laravel/mcp's 15 would hide the rest. Never above its maximum.
        $size = filter_var(config('kit.mcp.server.page_size', 50), FILTER_VALIDATE_INT);
        $this->defaultPaginationLength = max(1, min($size === false ? 50 : $size, $this->maxPaginationLength));
        $this->tools = app(Registry::class)->mcpTools();
        $this->addMethod('tools/call', CallTool::class);
    }
}
