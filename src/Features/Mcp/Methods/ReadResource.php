<?php

namespace Tey\Kit\Features\Mcp\Methods;

use Generator;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource as BaseReadResource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tey\Kit\Features\Core\Registry;

/**
 * A read of a resource its capability leaves out of the list is refused as the resource would refuse it (`capability_unavailable` or `entitlement_unavailable`), not
 * reported as an unknown resource, so an assistant holding an old list learns why. Listed resources, and URIs nothing registered, behave as laravel/mcp's.
 */
final class ReadResource extends BaseReadResource
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $uri = $request->get('uri');
        if (is_string($uri) && ! $context->resources()->contains(fn ($resource): bool => $resource->uri() === $uri)) {
            foreach (app(Registry::class)->mcpResourceInstances() as $resource) {
                if ($resource->uri() === $uri && method_exists($resource, 'unavailable') && ($reason = $resource->unavailable()) !== null) {
                    throw new JsonRpcException($reason, ErrorCode::INVALID_PARAMS->value, $request->id);
                }
            }
        }

        return parent::handle($request, $context);
    }
}
