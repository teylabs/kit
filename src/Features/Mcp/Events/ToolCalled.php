<?php

namespace Tey\Kit\Features\Mcp\Events;

use Tey\Kit\Features\Core\Principal;

/**
 * A CapabilityTool's `call()` ran and returned. Dispatched inside the same transaction as the call's stored intent (when it carries an `intent_key`), so a host listener
 * can write its own per-write audit that commits or rolls back with the call (a racing retry that loses the intent's unique index rolls back too). Without an `intent_key` there is no kit transaction: it fires right after `call()` returns. Never dispatched for a refusal, a `confirmation_required` prompt or an intent replay.
 */
final class ToolCalled
{
    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $arguments  the validated arguments
     * @param  array<string, mixed>  $result  call()'s result
     */
    public function __construct(
        public readonly string $tool,
        public readonly string $class,
        public readonly Principal $principal,
        public readonly int $workspaceId,
        public readonly array $arguments,
        public readonly array $result,
    ) {}
}
