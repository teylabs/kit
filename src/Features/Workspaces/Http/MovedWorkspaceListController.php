<?php

namespace Tey\Kit\Features\Workspaces\Http;

/** The old workspace list URL, `/workspaces`, permanently moved to `/me/workspaces`. */
class MovedWorkspaceListController
{
    public function __invoke(): mixed
    {
        return redirect('/me/workspaces', 301);
    }
}
