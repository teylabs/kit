<?php

namespace Tey\Kit\Features\Workspaces\Http;

use Illuminate\Http\Request;

/** The old `/tenant-invitations/accept`, permanently moved to `/me/invitations`. A POST keeps its method and body (308); a GET is a 301. */
class MovedInvitationController
{
    public function __invoke(Request $request): mixed
    {
        return redirect('/me/invitations', $request->isMethod('GET') ? 301 : 308);
    }
}
