<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The old workspace list URL, `/workspaces`, permanently moved to `/me/workspaces`. */
class MovedWorkspaceListController
{
    public function __invoke(): mixed
    {
        return redirect(WorkspaceUrl::chooser(), WorkspaceUrl::authOnly() ? 302 : 301);
    }
}
