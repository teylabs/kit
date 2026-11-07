<?php

namespace Tey\Kit\Features\Core\Mcp;

/**
 * An MCP tool that is only served beside other tools (reading queued runs needs a queued tool): Registry::mcpTools() leaves it out unless
 * served() says yes. Static and request-free, so the server's list, its calls, consent, queued jobs and an app's own index (`/llms.txt`) agree.
 */
interface ServedBeside
{
    /** @param  list<class-string>  $tools  every other tool the app serves */
    public static function served(array $tools): bool;
}
