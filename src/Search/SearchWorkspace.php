<?php

namespace Tey\Kit\Search;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\SessionUi\Search\Searches;

/**
 * Global search over MCP (`workspace_search`): the same registered providers, the same gating and the same per-person limit as the search page, so an
 * assistant finds exactly what the person would. Read only. It spans the mcp and session-ui features, so it lives outside both and the composition root
 * registers it when both are adopted.
 */
#[Capability('workspace.search')]
#[IsReadOnly]
final class SearchWorkspace extends CapabilityTool
{
    public const ABILITY = Searches::ABILITY;

    protected string $description = 'Search the workspace: each kind of record you can see that matches the query, up to 25 of each, best first.';

    public function schema(JsonSchema $schema): array
    {
        return ['q' => $schema->string()->description('What to look for, 1 to '.Searches::MAX_TERM.' characters.')->required()];
    }

    protected function rules(): array
    {
        return ['q' => ['required', 'string', 'min:1', 'max:'.Searches::MAX_TERM]];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        app(Access::class)->authorize($principal, self::ABILITY);
        Searches::throttle($principal);
        $found = app(Searches::class)->run($principal, $workspace, (string) $validated['q'], 25);

        return ['query' => trim((string) $validated['q']), 'groups' => array_map(fn (array $group): array => [...$group, 'results' => array_map(fn ($result) => $result->toArray(), $group['results'])], $found['groups']), 'partial' => $found['failed'] !== []];
    }
}
