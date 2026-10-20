<?php

namespace Tey\Kit\Features\Mcp\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Settings/Connections/Page.vue: the user's delegated MCP connections to the workspace, and what can be approved. */
#[TypeScript]
final class ConnectionsViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: string, label: string}>  $tokens  authorised client tokens that can be approved for this workspace
     * @param  list<array{key: string, description: string, required: bool}>  $scopes
     * @param  list<array{id: int, revoked: bool, expires_at: ?string, endpoint: string, revoke_url: string}>  $connections
     */
    public function __construct(private string $title, private array $tokens, private array $scopes, private array $connections, private string $create_url, private string $intent_key) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: string, label: string}> */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /** @return list<array{key: string, description: string, required: bool}> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /** @return list<array{id: int, revoked: bool, expires_at: ?string, endpoint: string, revoke_url: string}> */
    public function connections(): array
    {
        return $this->connections;
    }

    public function create_url(): string
    {
        return $this->create_url;
    }

    public function intent_key(): string
    {
        return $this->intent_key;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }

}
