<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;
use RuntimeException;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The old `/workspaces/{id or slug}/...` URL, permanently moved to `/@{slug}/...`. */
class MovedWorkspacePageController
{
    public function __invoke(Request $request, string $workspace, ?string $path = null): mixed
    {
        $found = Workspaces::query()->where('slug', $workspace)->when(ctype_digit($workspace), fn ($query) => $query->orWhere('id', (int) $workspace))->first() ?? throw new RuntimeException('workspace_not_found');
        $query = $request->getQueryString();

        // By id, so the slug is looked up among the person's own workspaces: a non-member is refused (workspace_not_found) and never learns the new address.
        return redirect(WorkspaceUrl::full((int) $found->getKey(), (string) $path).($query === null ? '' : '?'.$query), 301);
    }
}
