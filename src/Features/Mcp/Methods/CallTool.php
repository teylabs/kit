<?php

namespace Tey\Kit\Features\Mcp\Methods;

use Generator;
use Laravel\Mcp\Server\Methods\CallTool as BaseCallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tey\Kit\Features\Core\Registry;

/**
 * A call to a tool its capability leaves out of the list is refused as the tool would refuse it (`capability_unavailable` or `entitlement_unavailable`), not
 * reported as an unknown tool, so an assistant holding an old list learns why. Present tools, and names no feature registered, behave as laravel/mcp's.
 */
final class CallTool extends BaseCallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $name = $request->params['name'] ?? null;
        if (is_string($name) && ! $context->tools()->contains(fn ($tool): bool => $tool->name() === $name)) {
            foreach (app(Registry::class)->mcpTools() as $class) {
                $tool = app($class);
                if ($tool->name() === $name && method_exists($tool, 'unavailable') && ($reason = $tool->unavailable()) !== null) {
                    return JsonRpcResponse::result($request->id, ['content' => [['type' => 'text', 'text' => $reason]], 'isError' => true]);
                }
            }
        }

        return parent::handle($request, $context);
    }
}
