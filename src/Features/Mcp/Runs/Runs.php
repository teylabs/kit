<?php

namespace Tey\Kit\Features\Mcp\Runs;

/** Queued MCP runs: their states and settings (`kit.mcp.run_ttl`, `kit.mcp.run_poll_after`). */
final class Runs
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    /** How long a run and its result can be read, in seconds: `kit.mcp.run_ttl`, 24 hours by default, at least one minute. */
    public static function ttl(): int
    {
        return max(60, (int) config('kit.mcp.run_ttl', 86400));
    }

    /** How long the assistant is told to wait before reading a run: `kit.mcp.run_poll_after`, 5 seconds by default, at least one. */
    public static function pollAfter(): int
    {
        return max(1, (int) config('kit.mcp.run_poll_after', 5));
    }
}
