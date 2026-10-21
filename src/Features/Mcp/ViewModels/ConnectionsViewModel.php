<?php

namespace Tey\Kit\Features\Mcp\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Mcp\Data\AssistantClient;

/** Props for pages/Workspace/Settings/Connections/Page.vue: the user's delegated MCP connections to the workspace, and what can be approved. */
#[TypeScript]
final class ConnectionsViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: string, label: string}>  $pending  authorised assistants waiting for this workspace's approval
     * @param  list<array{key: string, description: string, required: bool}>  $scopes
     * @param  list<AssistantClient>  $assistants  the "add an assistant" cards
     */
    public function __construct(private string $title, private array $pending, private array $scopes, private TableData $table, private ?Action $approve, private string $intent_key, private string $server_url, private array $assistants) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{id: string, label: string}> */
    public function pending(): array
    {
        return $this->pending;
    }

    /** @return list<array{key: string, description: string, required: bool}> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /** The connected assistants: name, who connected it, when, when it expires, and Revoke. */
    public function table(): TableData
    {
        return $this->table;
    }

    /** Approve a waiting assistant for this workspace; absent when none is waiting. */
    public function approve(): ?Action
    {
        return $this->approve;
    }

    public function intent_key(): string
    {
        return $this->intent_key;
    }

    /** The address assistants connect to. */
    public function server_url(): string
    {
        return $this->server_url;
    }

    /** @return list<AssistantClient> */
    public function assistants(): array
    {
        return $this->assistants;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}
