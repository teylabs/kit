<?php

namespace Tey\Kit\Features\Core\Routing;

use Attribute;

/**
 * A controller whose writes carry no `workspace.ability:*` middleware because the operation it calls authorises the request itself: the same operation
 * an MCP tool calls with the request's Principal and workspace. Tey\Kit\Testing\RouteInvariants::guardedWrites() reads it, so a workspace write needs the
 * middleware or this declaration, and a new write can't ship unguarded by accident. The named class must exist.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AuthorizedBy
{
    /** @param class-string $operation the operation that authorises */
    public function __construct(public string $operation) {}
}
