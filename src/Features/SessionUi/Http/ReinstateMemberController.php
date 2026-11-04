<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Reinstates a suspended member with the roles they held. Concierge's rules decide who may, and refuse a removed member. */
#[AuthorizedBy(ManagePeople::class)]
class ReinstateMemberController
{
    public function __invoke(Request $request, int $workspace, int $membership): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $result = ManagePeople::make()->reinstate($user, $membership);

        return $request->expectsJson() ? response()->json(['id' => $result->getKey(), 'status' => 'active']) : redirect(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
    }
}
