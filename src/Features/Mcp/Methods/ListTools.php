<?php

namespace Tey\Kit\Features\Mcp\Methods;

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Server\Methods\ListTools as BaseListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tey\Kit\Features\Core\Principal;

/**
 * tools/list, reused per connection for kit.mcp.list_cache_seconds, so an assistant's reconnect storm does not rebuild every tool's schema and gate each time.
 * Keyed by the connection (which names one workspace) and the page asked for. A call still checks its tool's gate, so a list stale by a permission change only lists.
 */
final class ListTools extends BaseListTools
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $seconds = (int) config('kit.mcp.list_cache_seconds', 60);
        $principal = request()->attributes->get('mcp.principal');
        if ($seconds <= 0 || ! $principal instanceof Principal || $principal->connectionId === null) {
            return parent::handle($request, $context);
        }
        $key = 'kit-mcp-tools|'.$principal->connectionId.'|'.md5(json_encode([$request->cursor(), $request->get('per_page')]));
        $result = Cache::remember($key, $seconds, fn (): array => parent::handle($request, $context)->toArray()['result'] ?? []);

        return JsonRpcResponse::result($request->id, $result);
    }
}
