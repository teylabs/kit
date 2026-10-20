<?php

namespace Tey\Kit\Features\Mcp\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Connections/Index/Page.vue: the user's delegated MCP connections to the workspace, and what can be approved. */
#[TypeScript]
final class ConnectionsViewModel extends Data
{
    /**
     * @param  list<array{id: string, label: string}>  $tokens  authorised client tokens that can be approved for this workspace
     * @param  list<array{key: string, description: string, required: bool}>  $scopes
     * @param  list<array{id: int, revoked: bool, expires_at: ?string, endpoint: string, revoke_url: string}>  $connections
     */
    public function __construct(public string $title, public array $tokens, public array $scopes, public array $connections, public string $create_url, public string $intent_key) {}
}
