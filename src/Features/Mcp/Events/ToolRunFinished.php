<?php

namespace Tey\Kit\Features\Mcp\Events;

use Tey\Kit\Features\Core\Principal;

/** A queued MCP run finished: `done`, or `failed` with its refusal code. `ToolCalled` has already fired for a run whose `call()` ran. */
final class ToolRunFinished
{
    public function __construct(
        public readonly string $run,
        public readonly string $tool,
        public readonly Principal $principal,
        public readonly int $workspaceId,
        public readonly string $status,
        public readonly ?string $error,
    ) {}
}
