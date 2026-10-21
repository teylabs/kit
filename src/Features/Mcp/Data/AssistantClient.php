<?php

namespace Tey\Kit\Features\Mcp\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** One "add an AI assistant" card: a one-click install (`install`, a deep link the assistant's app opens), a command to copy, or numbered steps; all built on the server from the MCP address. */
#[TypeScript]
final class AssistantClient extends Data
{
    /** @param  list<string>  $steps */
    public function __construct(public string $key, public string $label, public string $summary, public ?Action $install, public ?string $command, public array $steps) {}
}
